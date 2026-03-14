<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\PrioritiseRequest;
use App\Models\Brand;
use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
use App\Models\RequestWatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PrioritisationController extends Controller
{
    public function store(PrioritiseRequest $request)
    {
        try {
            $barcode = $request->barcode;
            $type = $request->type ?? 'prioritise';
            $userEmail = $request->user_email;
            $isAnonymous = empty($userEmail) || str_contains(strtolower($userEmail), 'noreply');

            // 1. Check if product already has a verdict (lookup by barcode, fallback to ID)
            $product = Product::where('Barcode', $barcode)->first();
            if (!$product && is_numeric($barcode)) {
                $product = Product::find($barcode);
                // Use the actual barcode from the product if found by ID
                if ($product && $product->Barcode) {
                    $barcode = $product->Barcode;
                }
            }
            if ($product && in_array($product->halal_status, ['0', '1', 0, 1])) {
                return response()->json([
                    'already_resolved' => true,
                    'halal_status' => (int) $product->halal_status,
                    'product_name' => $product->product_name,
                    'notes' => $product->notes,
                    'message' => $product->halal_status == 0 ? 'This product is Halal' : 'This product is Not Halal',
                ]);
            }

            // 2. Check for existing active request with same barcode
            $existingRequest = PrioritisationRequest::where('barcode', $barcode)
                ->active()
                ->first();

            if ($existingRequest) {
                // Add this user as a watcher if they provided an email
                if (!$isAnonymous) {
                    RequestWatcher::firstOrCreate(
                        ['request_id' => $existingRequest->id, 'user_email' => $userEmail],
                        ['user_name' => $request->user_name]
                    );
                }

                // Fill in missing info if the new request has more data
                $updates = [];
                if (empty($existingRequest->product_name) && $request->product_name) {
                    $updates['product_name'] = $request->product_name;
                }
                if (empty($existingRequest->brand_name) && $request->brand_name) {
                    $updates['brand_name'] = $request->brand_name;
                }
                if (!empty($updates)) {
                    $existingRequest->update($updates);
                }

                return response()->json([
                    'already_requested' => true,
                    'status' => $existingRequest->status,
                    'message' => 'This product has already been requested. You will be notified when we have an update.',
                ]);
            }

            // 3. Gather product info
            $productName = $request->product_name;
            $brandName = $request->brand_name;

            // Server-side Open Food Facts fallback
            if (empty($productName) || empty($brandName)) {
                $offData = $this->lookupOpenFoodFacts($barcode);
                if ($offData) {
                    $productName = $productName ?: $offData['product_name'];
                    $brandName = $brandName ?: $offData['brand'];
                }
            }

            // Fall back to existing product data if available
            if ($product) {
                $productName = $productName ?: $product->product_name;
            }

            // 4. Store photo if provided
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('prioritisation_photos');
            }

            // 5. Determine initial status based on brand
            $status = 'pending';
            if (!empty($brandName)) {
                $brand = Brand::where('name', 'LIKE', $brandName)->first();
                if ($brand) {
                    if ($brand->email && $brand->contact_type === 'email') {
                        if ($brand->response) {
                            $status = 'ready_for_review';
                        } else {
                            $status = 'ready_for_outreach';
                        }
                    }
                    // Check if brand was already contacted recently
                    if ($brand->last_contacted_at && !$brand->response) {
                        $status = 'contacted';
                    }
                }
            }

            // 6. Create the request
            $prioRequest = PrioritisationRequest::create([
                'barcode' => $barcode,
                'product_name' => $productName,
                'brand_name' => $brandName,
                'user_email' => $isAnonymous ? null : $userEmail,
                'user_name' => $request->user_name,
                'photo_path' => $photoPath,
                'type' => $type,
                'status' => $status,
                'source' => 'app',
            ]);

            // 7. Add user as watcher
            if (!$isAnonymous) {
                RequestWatcher::create([
                    'request_id' => $prioRequest->id,
                    'user_email' => $userEmail,
                    'user_name' => $request->user_name,
                ]);
            }

            // 8. If new product, add to products table so future scans find it
            if ($type === 'new_product' && !$product && $productName) {
                Product::create([
                    'product_name' => $productName,
                    'Barcode' => $barcode,
                    'halal_status' => null,
                    'status' => 1,
                    'category' => '',
                ]);
            }

            return response()->json([
                'message' => 'Your request has been submitted. We will look into this product.',
                'request_id' => $prioRequest->id,
                'status' => $status,
            ]);
        } catch (\Exception $e) {
            Log::error('Prioritisation request failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Request submitted.',
            ]);
        }
    }

    private function lookupOpenFoodFacts(string $barcode): ?array
    {
        try {
            $response = Http::timeout(5)
                ->get("https://world.openfoodfacts.org/api/v2/product/{$barcode}.json", [
                    'fields' => 'product_name,brands',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? 0) == 1) {
                    $product = $data['product'] ?? [];
                    $productName = $product['product_name'] ?? null;
                    $brand = $product['brands'] ?? null;

                    if ($productName || $brand) {
                        return [
                            'product_name' => $productName,
                            'brand' => $brand,
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::debug('Open Food Facts lookup failed for ' . $barcode . ': ' . $e->getMessage());
        }

        return null;
    }
}
