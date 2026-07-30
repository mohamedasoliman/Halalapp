<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\PrioritiseRequest;
use App\Models\Brand;
use App\Models\BrandCommunication;
use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
use App\Models\RequestWatcher;
use App\Support\ProductBarcode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PrioritisationController extends Controller
{
    public function store(PrioritiseRequest $request)
    {
        try {
            $barcode = ProductBarcode::canonical($request->barcode);
            $type = $request->type ?? 'prioritise';
            $userEmail = $request->user_email;
            $isAnonymous = empty($userEmail) || str_contains(strtolower($userEmail), 'noreply');

            // 1. Check if product already has a verdict (lookup by barcode, fallback to ID)
            $product = Product::matchingBarcode($barcode)->first();
            if (! $product && is_numeric($barcode)) {
                $product = Product::find($barcode);
                // Use the actual barcode from the product if found by ID
                if ($product && $product->Barcode) {
                    $barcode = $product->Barcode;
                }
            }
            if ($product) {
                $barcode = (string) $product->Barcode;
            }
            if (! $product && strlen($barcode) < 8) {
                return response()->json([
                    'message' => 'A valid product barcode is required.',
                ], 422);
            }
            if ($product && ($product->halal_status === '0' || $product->halal_status === '1')) {
                return response()->json([
                    'already_resolved' => true,
                    'halal_status' => (int) $product->halal_status,
                    'product_name' => $product->product_name,
                    'notes' => $product->notes,
                    'message' => $product->halal_status == 0 ? 'This product is Halal' : 'This product is Not Halal',
                ]);
            }

            // 2. Check for existing active request with same barcode
            $existingRequest = PrioritisationRequest::matchingBarcode($barcode)
                ->active()
                ->first();

            if ($existingRequest) {
                // Add this user as a watcher if they provided an email
                if (! $isAnonymous) {
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
                if (! empty($updates)) {
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
            if (! empty($brandName)) {
                $brand = Brand::where('name', 'LIKE', $brandName)->first();
                if ($brand) {
                    if ($brand->email && $brand->contact_type === 'email') {
                        if ($brand->response && $brand->response_scope === 'blanket') {
                            // Brand gave a blanket response (all products) — ready for review
                            $status = 'ready_for_review';
                        } else {
                            // Check if this specific barcode was already mentioned in an outreach
                            $barcodeAlreadySent = BrandCommunication::where('brand_id', $brand->id)
                                ->where('direction', 'outbound')
                                ->whereJsonContains('barcodes_mentioned', $barcode)
                                ->exists();

                            if ($barcodeAlreadySent) {
                                $status = 'contacted'; // This exact product was already asked about
                            } else {
                                $status = 'ready_for_outreach'; // New product for this brand — need to email again
                            }
                        }
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
            if (! $isAnonymous) {
                RequestWatcher::create([
                    'request_id' => $prioRequest->id,
                    'user_email' => $userEmail,
                    'user_name' => $request->user_name,
                ]);
            }

            return response()->json([
                'message' => 'Your request has been submitted. We will look into this product.',
                'request_id' => $prioRequest->id,
                'status' => $status,
            ]);
        } catch (\Exception $e) {
            Log::error('Prioritisation request failed: '.$e->getMessage());

            return response()->json([
                'message' => 'Request submitted.',
            ]);
        }
    }

    public function checkStatus(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids) || ! is_array($ids)) {
            return response()->json(['resolved' => []]);
        }

        // Limit to 50 IDs per request
        $ids = array_slice($ids, 0, 50);

        $resolved = PrioritisationRequest::whereIn('id', $ids)
            ->where('status', 'resolved')
            ->get(['id', 'barcode', 'product_name', 'resolved_status'])
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'product_name' => $r->product_name ?? 'Unknown Product',
                    'halal_status' => $r->resolved_status,
                    'status_label' => $r->resolved_status === 0 ? 'Halal' : 'Not Halal',
                ];
            });

        return response()->json(['resolved' => $resolved]);
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
            Log::debug('Open Food Facts lookup failed for '.$barcode.': '.$e->getMessage());
        }

        return null;
    }
}
