<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\PrioritiseRequest;
use App\Models\Brand;
use App\Models\BrandCommunication;
use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
use App\Models\RequestWatcher;
use App\Support\ProductBarcode;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PrioritisationController extends Controller
{
    public function store(PrioritiseRequest $request)
    {
        $storedPhotos = [];

        try {
            $storedPhotos = $this->storeUploadedPhotos($request);

            try {
                $result = $this->persistSubmission($request, $storedPhotos);
            } catch (QueryException $exception) {
                if (! $this->isActiveBarcodeCollision($exception)) {
                    throw $exception;
                }

                // An unknown barcode has no product row to serialize on. If another
                // request won the unique-key race, retry once and enrich that row.
                $result = $this->persistSubmission($request, $storedPhotos);
            }

            if (! $result['keep_photos']) {
                $this->deleteStoredPhotos($storedPhotos);
                $storedPhotos = [];
            }

            return response()->json($result['payload'], $result['status_code']);
        } catch (Throwable $e) {
            $this->deleteStoredPhotos($storedPhotos);
            Log::error('Prioritisation request failed', [
                'exception_type' => $e::class,
            ]);

            return response()->json([
                'message' => 'We could not save this request. Please try again.',
                'code' => 'REQUEST_FAILED',
            ], 503);
        }
    }

    private function persistSubmission(PrioritiseRequest $request, array $storedPhotos): array
    {
        return DB::transaction(function () use ($request, $storedPhotos) {
            $barcode = ProductBarcode::canonical($request->barcode);
            $product = Product::matchingBarcode($barcode)->lockForUpdate()->first();
            if (! $product && ctype_digit($barcode)) {
                $product = Product::query()->lockForUpdate()->find($barcode);
            }
            if ($product) {
                $barcode = (string) $product->Barcode;
            }

            if (ProductBarcode::key($barcode) === null || (! $product && strlen($barcode) < 8)) {
                return [
                    'payload' => ['message' => 'A valid product barcode is required.'],
                    'status_code' => 422,
                    'keep_photos' => false,
                ];
            }

            if ($product && in_array((string) $product->halal_status, ['0', '1'], true)) {
                return [
                    'payload' => [
                        'already_resolved' => true,
                        'halal_status' => (int) $product->halal_status,
                        'product_name' => $product->product_name,
                        'notes' => $product->notes,
                        'message' => (string) $product->halal_status === '0'
                            ? 'This product is Halal'
                            : 'This product is Not Halal',
                    ],
                    'status_code' => 200,
                    'keep_photos' => false,
                ];
            }

            $incomingType = $this->effectiveType((string) ($request->type ?? 'prioritise'), $product !== null);
            $userEmail = strtolower(trim((string) $request->user_email));
            $isAnonymous = $userEmail === '' || str_contains($userEmail, 'noreply');
            $productName = filled($product?->product_name)
                ? trim((string) $product->product_name)
                : (filled($request->product_name) ? trim((string) $request->product_name) : null);
            $brandName = filled($product?->brand)
                ? trim((string) $product->brand)
                : (filled($request->brand_name) ? trim((string) $request->brand_name) : null);

            $existingRequest = PrioritisationRequest::matchingBarcode($barcode)
                ->active()
                ->lockForUpdate()
                ->first();

            if ($existingRequest) {
                $mergedType = $this->mergedType((string) $existingRequest->type, $incomingType, $product !== null);
                $updates = [];
                if (filled($product?->product_name) && $existingRequest->product_name !== $productName) {
                    $updates['product_name'] = $productName;
                } elseif (blank($existingRequest->product_name) && filled($productName)) {
                    $updates['product_name'] = $productName;
                }
                if (filled($product?->brand) && $existingRequest->brand_name !== $brandName) {
                    $updates['brand_name'] = $brandName;
                } elseif (blank($existingRequest->brand_name) && filled($brandName)) {
                    $updates['brand_name'] = $brandName;
                }
                if ($mergedType !== $existingRequest->type) {
                    $updates['type'] = $mergedType;
                }
                if ($existingRequest->status === 'pending' && $mergedType === 'prioritise') {
                    $updates['status'] = $this->initialStatus($brandName, $barcode);
                }
                if (blank($existingRequest->photo_path) && $storedPhotos !== []) {
                    $updates['photo_path'] = $storedPhotos[0]['path'];
                }
                if ($updates !== []) {
                    $existingRequest->update($updates);
                }

                $this->addWatcher($existingRequest, $isAnonymous ? null : $userEmail, $request->user_name);
                $this->attachPhotos($existingRequest, $storedPhotos);
                $existingRequest->refresh();

                return [
                    'payload' => [
                        'already_requested' => true,
                        'request_id' => $existingRequest->id,
                        'status' => $existingRequest->status,
                        'message' => 'This product has already been requested. You will be notified when we have an update.',
                    ],
                    'status_code' => 200,
                    'keep_photos' => true,
                ];
            }

            $status = $incomingType === 'prioritise'
                ? $this->initialStatus($brandName, $barcode)
                : 'pending';
            $prioRequest = PrioritisationRequest::create([
                'barcode' => $barcode,
                'product_name' => $productName,
                'brand_name' => $brandName,
                'user_email' => $isAnonymous ? null : $userEmail,
                'user_name' => $request->user_name,
                'photo_path' => $storedPhotos[0]['path'] ?? null,
                'type' => $incomingType,
                'status' => $status,
                'source' => 'app',
            ]);

            $this->addWatcher($prioRequest, $isAnonymous ? null : $userEmail, $request->user_name);
            $this->attachPhotos($prioRequest, $storedPhotos);

            return [
                'payload' => [
                    'message' => 'Your request has been submitted. We will look into this product.',
                    'request_id' => $prioRequest->id,
                    'status' => $status,
                ],
                'status_code' => 200,
                'keep_photos' => true,
            ];
        }, 3);
    }

    private function effectiveType(string $requestedType, bool $hasProduct): string
    {
        if ($requestedType === 'silent') {
            return 'silent';
        }

        return $hasProduct ? 'prioritise' : 'new_product';
    }

    private function mergedType(string $existingType, string $incomingType, bool $hasProduct): string
    {
        if ($existingType === 'prioritise') {
            return 'prioritise';
        }
        if ($hasProduct && $incomingType === 'prioritise') {
            return 'prioritise';
        }
        if ($existingType === 'new_product' || $incomingType === 'new_product') {
            return 'new_product';
        }

        return 'silent';
    }

    private function initialStatus(?string $brandName, string $barcode): string
    {
        if (blank($brandName)) {
            return 'pending';
        }

        $brand = Brand::where('name', 'LIKE', $brandName)->first();
        if (! $brand || ! $brand->email || $brand->contact_type !== 'email') {
            return 'pending';
        }
        if ($brand->response && $brand->response_scope === 'blanket') {
            return 'ready_for_review';
        }

        return BrandCommunication::where('brand_id', $brand->id)
            ->where('direction', 'outbound')
            ->whereJsonContains('barcodes_mentioned', $barcode)
            ->exists()
                ? 'contacted'
                : 'ready_for_outreach';
    }

    private function addWatcher(PrioritisationRequest $request, ?string $email, ?string $name): void
    {
        if ($email === null) {
            return;
        }

        $watcher = RequestWatcher::query()
            ->where('request_id', $request->id)
            ->whereRaw('LOWER(user_email) = ?', [$email])
            ->lockForUpdate()
            ->first();
        if (! $watcher) {
            $watcher = RequestWatcher::create([
                'request_id' => $request->id,
                'user_email' => $email,
                'user_name' => $name,
            ]);
        }
        if (blank($watcher->user_name) && filled($name)) {
            $watcher->update(['user_name' => $name]);
        }
    }

    private function attachPhotos(PrioritisationRequest $request, array $storedPhotos): void
    {
        foreach ($storedPhotos as $photo) {
            $request->photos()->create($photo + ['source' => 'app']);
        }
    }

    private function storeUploadedPhotos(PrioritiseRequest $request): array
    {
        $files = [];
        if ($request->hasFile('photo')) {
            $files[] = $request->file('photo');
        }
        foreach ((array) $request->file('photos', []) as $file) {
            if ($file instanceof UploadedFile) {
                $files[] = $file;
            }
        }

        $stored = [];
        try {
            foreach ($files as $file) {
                $path = $file->store('prioritisation_photos', 'local');
                if (! is_string($path) || trim($path) === '') {
                    throw new \RuntimeException('The prioritisation photo could not be stored.');
                }
                $stored[] = [
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                ];
            }
        } catch (Throwable $exception) {
            $this->deleteStoredPhotos($stored);

            throw $exception;
        }

        return $stored;
    }

    private function deleteStoredPhotos(array $storedPhotos): void
    {
        $paths = collect($storedPhotos)->pluck('path')->filter()->all();
        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }
    }

    private function isActiveBarcodeCollision(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($sqlState, ['23000', '23505'], true)
            && (str_contains($message, 'prioritisation_requests_active_barcode_key_unique')
                || str_contains($message, 'prioritisation_requests.active_barcode_key'));
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
