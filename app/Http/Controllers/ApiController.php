<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\BarcodeLookupRequest;
use App\Http\Requests\Api\ProductSearchRequest;
use App\Http\Requests\Api\StrictProductSearchRequest;
use App\Models\ProductModel\Product;
use App\Support\HalalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApiController extends Controller
{
    /**
     * Only these columns are needed to build the public mobile response.
     *
     * Barcode, barcode_key, proof, timestamps and other internal fields are
     * intentionally excluded. A catalogue client must never receive them.
     */
    private const PUBLIC_PRODUCT_COLUMNS = [
        'id',
        'product_name',
        'brand',
        'product_image',
        'halal_status',
        'Certification_Status',
        'category',
        'country',
        'ingredient',
        'notes',
    ];

    /**
     * Get the full URL for a product image.
     * Supports both local filenames and external URLs.
     *
     * @param  string|null  $image
     * @return string
     */
    private function getProductImageUrl($image)
    {
        if (empty($image)) {
            return asset('public/upload/product_images/default.png');
        }

        // If it's already a full URL, return as-is
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        // Otherwise, it's a local filename - prepend the local path
        return asset('public/upload/product_images/'.$image);
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function allListing(ProductSearchRequest $request)
    {
        try {
            // Force bounded pagination: default 25, max 50.
            $perPage = min(max((int) $request->get('per_page', 25), 1), 50);

            // Check if halal_only filter is requested
            $halalOnly = $request->get('halal_only');
            $statusFilter = (string) $request->get('halal_status', '');
            $statusFilter = in_array($statusFilter, HalalStatus::values(), true)
                ? $statusFilter
                : null;
            $flavour = $request->string('flavour')->toString();
            $assistantSearch = $request->boolean('assistant_search');

            if (! empty($request->search)) {
                $searchTerm = trim($request->search);
                $escapedSearchTerm = $this->escapeLike($searchTerm);

                // Fuzzy search implementation with multiple matching strategies
                $query = $this->publicProductQuery()
                    ->selectRaw('
                        (CASE
                            WHEN product_name = ? THEN 100
                            WHEN product_name LIKE ? ESCAPE \'!\' THEN 95
                            WHEN product_name LIKE ? ESCAPE \'!\' THEN 90
                            WHEN product_name LIKE ? ESCAPE \'!\' THEN 85
                            WHEN SOUNDEX(product_name) = SOUNDEX(?) THEN 80
                            WHEN Barcode = ? THEN 75
                            WHEN category LIKE ? ESCAPE \'!\' THEN 65
                            WHEN ingredient LIKE ? ESCAPE \'!\' THEN 60
                            WHEN notes LIKE ? ESCAPE \'!\' THEN 55
                            WHEN SOUNDEX(category) = SOUNDEX(?) THEN 50
                            WHEN SOUNDEX(ingredient) = SOUNDEX(?) THEN 45
                            ELSE 0
                        END) as relevance_score
                    ', [
                        $searchTerm,                    // Exact match
                        $escapedSearchTerm.'%',        // Starts with
                        '%'.$escapedSearchTerm.'%',    // Contains
                        '%'.$escapedSearchTerm,        // Ends with
                        $searchTerm,                    // Sounds like (SOUNDEX)
                        $searchTerm,                    // Exact barcode
                        '%'.$escapedSearchTerm.'%',    // Category contains
                        '%'.$escapedSearchTerm.'%',    // Ingredient contains
                        '%'.$escapedSearchTerm.'%',    // Notes contains
                        $searchTerm,                    // Category sounds like
                        $searchTerm,                     // Ingredient sounds like
                    ])
                    ->where(function ($q) use ($searchTerm, $escapedSearchTerm, $assistantSearch) {
                        if ($assistantSearch) {
                            $words = preg_split('/\s+/u', mb_strtolower($searchTerm)) ?: [];
                            foreach (array_unique($words) as $word) {
                                if (mb_strlen($word) >= 2) {
                                    $q->whereRaw(
                                        "product_name LIKE ? ESCAPE '!'",
                                        ['%'.$this->escapeLike($word).'%']
                                    );
                                }
                            }

                            return;
                        }

                        $contains = '%'.$escapedSearchTerm.'%';
                        $q->whereRaw("product_name LIKE ? ESCAPE '!'", [$contains])
                            ->orWhere('Barcode', $searchTerm)
                            ->orWhereRaw("category LIKE ? ESCAPE '!'", [$contains])
                            ->orWhereRaw("ingredient LIKE ? ESCAPE '!'", [$contains])
                            ->orWhereRaw("notes LIKE ? ESCAPE '!'", [$contains])
                            ->orWhereRaw('SOUNDEX(product_name) = SOUNDEX(?)', [$searchTerm])
                            ->orWhereRaw('SOUNDEX(category) = SOUNDEX(?)', [$searchTerm])
                            ->orWhereRaw('SOUNDEX(ingredient) = SOUNDEX(?)', [$searchTerm]);
                    })
                    ->where('status', 1);

                // Add halal filter if requested
                if ($statusFilter !== null) {
                    $query->where('halal_status', $statusFilter);
                } elseif ($halalOnly == '1' || $halalOnly == 'true') {
                    $query->where('halal_status', 0);
                }

                $this->applyAssistantProductFilters($query, $flavour);

                // Order by relevance score first, then alphabetically
                $query->orderByDesc('relevance_score')
                    ->orderBy('product_name');
            } else {
                // No search term — cacheable listing
                $halalFilter = ($halalOnly == '1' || $halalOnly == 'true') ? 1 : 0;
                $ver = Cache::get('products_cache_version', 1);
                $cacheStatus = $statusFilter ?? ($halalFilter ? HalalStatus::HALAL : 'all');
                $filterKey = sha1($flavour);
                $cacheKey = "products:v{$ver}:public-v2:{$cacheStatus}:{$filterKey}:{$perPage}:".($request->get('page', 1));

                $data = Cache::remember($cacheKey, 600, function () use ($halalFilter, $statusFilter, $flavour, $perPage) {
                    $query = $this->publicProductQuery()
                        ->where('status', 1);

                    if ($statusFilter !== null) {
                        $query->where('halal_status', $statusFilter);
                    } elseif ($halalFilter) {
                        $query->where('halal_status', 0);
                    }

                    $this->applyAssistantProductFilters($query, $flavour);

                    $products = $query->paginate($perPage);

                    return [
                        'status' => 'success',
                        'alldata' => $this->serializeProducts($products->items()),
                        'current_page' => $products->currentPage(),
                        'last_page' => $products->lastPage(),
                        'per_page' => $products->perPage(),
                        'total' => $products->total(),
                    ];
                });

                return response()->json($data);
            }

            // Fetch search results (not cached — too varied)
            $products = $query->paginate($perPage);
            $data = [
                'status' => 'success',
                'alldata' => $this->serializeProducts($products->items()),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ];

            return response()->json($data);
        } catch (Throwable $exception) {
            Log::error('Product catalogue request failed.', [
                'exception' => $exception,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to process the product request.',
            ], 500);
        }
    }

    public function searchProducts(StrictProductSearchRequest $request)
    {
        return $this->allListing($request);
    }

    private function applyAssistantProductFilters(
        Builder $query,
        string $flavour,
    ): void {
        $ignoredWords = ['and', 'flavour', 'flavor', 'flavoured', 'flavored'];
        $words = preg_split('/\s+/u', mb_strtolower(trim($flavour))) ?: [];
        foreach (array_unique($words) as $word) {
            if (mb_strlen($word) < 2 || in_array($word, $ignoredWords, true)) {
                continue;
            }

            $query->where(function (Builder $query) use ($word) {
                $contains = '%'.$this->escapeLike($word).'%';
                $query->whereRaw("product_name LIKE ? ESCAPE '!'", [$contains])
                    ->orWhereRaw("ingredient LIKE ? ESCAPE '!'", [$contains])
                    ->orWhereRaw("notes LIKE ? ESCAPE '!'", [$contains]);
            });
        }
    }

    public function allListingBarcode(BarcodeLookupRequest $request)
    {
        try {
            $searchTerm = trim((string) $request->validated('search'));
            $product = $this->publicProductQuery()
                ->matchingBarcode($searchTerm)
                ->where('status', 1)
                ->orderBy('product_name')
                ->first();

            $items = $product ? $this->serializeProducts([$product]) : [];
            $data = [
                'status' => 'success',
                'alldata' => $items,
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 1,
                'total' => count($items),
            ];

            return response()->json($data);
        } catch (Throwable $exception) {
            Log::error('Barcode lookup request failed.', [
                'exception' => $exception,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to process the barcode request.',
            ], 500);
        }
    }

    private function publicProductQuery(): Builder
    {
        return Product::query()->select(self::PUBLIC_PRODUCT_COLUMNS);
    }

    /**
     * @param  iterable<int, Product>  $products
     * @return array<int, array<string, int|string|null>>
     */
    private function serializeProducts(iterable $products): array
    {
        $serialized = [];

        foreach ($products as $product) {
            $serialized[] = [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'fruit_name' => $product->product_name,
                'brand' => $product->brand,
                'product_image' => $product->product_image,
                'fruit_image' => $product->product_image,
                'halal_status' => (string) $product->halal_status,
                'Certification_Status' => $product->Certification_Status,
                'category' => $product->category,
                'country' => $product->country,
                'ingredient' => $product->ingredient,
                'notes' => $product->notes,
                'url' => $this->getProductImageUrl($product->product_image),
            ];
        }

        return $serialized;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $value
        );
    }
}
