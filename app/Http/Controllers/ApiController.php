<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\ProductSearchRequest;
use App\Models\ProductModel\Product;
use App\Support\HalalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ApiController extends Controller
{
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
     * @return \Illuminate\Http\Response
     */
    public function allListing(ProductSearchRequest $request)
    {
        try {
            // Force pagination: default 50, max 100
            $perPage = min((int) ($request->get('per_page', 50)), 100);

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

                // Fuzzy search implementation with multiple matching strategies
                $query = Product::select('products.*', 'product_name as fruit_name', 'product_image as fruit_image')
                    ->selectRaw('
                        (CASE
                            WHEN product_name = ? THEN 100
                            WHEN product_name LIKE ? THEN 95
                            WHEN product_name LIKE ? THEN 90
                            WHEN product_name LIKE ? THEN 85
                            WHEN SOUNDEX(product_name) = SOUNDEX(?) THEN 80
                            WHEN Barcode = ? THEN 75
                            WHEN Barcode LIKE ? THEN 70
                            WHEN category LIKE ? THEN 65
                            WHEN ingredient LIKE ? THEN 60
                            WHEN notes LIKE ? THEN 55
                            WHEN SOUNDEX(category) = SOUNDEX(?) THEN 50
                            WHEN SOUNDEX(ingredient) = SOUNDEX(?) THEN 45
                            ELSE 0
                        END) as relevance_score
                    ', [
                        $searchTerm,                    // Exact match
                        $searchTerm.'%',              // Starts with
                        '%'.$searchTerm.'%',        // Contains
                        '%'.$searchTerm,              // Ends with
                        $searchTerm,                    // Sounds like (SOUNDEX)
                        $searchTerm,                    // Exact barcode
                        '%'.$searchTerm.'%',        // Barcode contains
                        '%'.$searchTerm.'%',        // Category contains
                        '%'.$searchTerm.'%',        // Ingredient contains
                        '%'.$searchTerm.'%',        // Notes contains
                        $searchTerm,                    // Category sounds like
                        $searchTerm,                     // Ingredient sounds like
                    ])
                    ->where(function ($q) use ($searchTerm, $assistantSearch) {
                        if ($assistantSearch) {
                            $words = preg_split('/\s+/u', mb_strtolower($searchTerm)) ?: [];
                            foreach (array_unique($words) as $word) {
                                if (mb_strlen($word) >= 2) {
                                    $q->where('product_name', 'LIKE', '%'.$word.'%');
                                }
                            }

                            return;
                        }

                        $q->where('product_name', 'LIKE', '%'.$searchTerm.'%')
                            ->orWhere('Barcode', 'LIKE', '%'.$searchTerm.'%')
                            ->orWhere('category', 'LIKE', '%'.$searchTerm.'%')
                            ->orWhere('ingredient', 'LIKE', '%'.$searchTerm.'%')
                            ->orWhere('notes', 'LIKE', '%'.$searchTerm.'%')
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
                $cacheKey = "products:v{$ver}:list:{$cacheStatus}:{$filterKey}:{$perPage}:".($request->get('page', 1));

                $data = Cache::remember($cacheKey, 600, function () use ($halalFilter, $statusFilter, $flavour, $perPage) {
                    $query = Product::select('products.*', 'product_name as fruit_name', 'product_image as fruit_image')
                        ->where('status', 1);

                    if ($statusFilter !== null) {
                        $query->where('halal_status', $statusFilter);
                    } elseif ($halalFilter) {
                        $query->where('halal_status', 0);
                    }

                    $this->applyAssistantProductFilters($query, $flavour);

                    $products = $query->paginate($perPage);
                    $items = $products->items();
                    foreach ($items as $key => $value) {
                        $items[$key]['url'] = $this->getProductImageUrl($value['product_image']);
                    }

                    return [
                        'status' => 'success',
                        'alldata' => $items,
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
                'alldata' => $products->items(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ];

            foreach ($data['alldata'] as $key => $value) {
                $data['alldata'][$key]['url'] = $this->getProductImageUrl($value['product_image']);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
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
                $query->where('product_name', 'LIKE', '%'.$word.'%')
                    ->orWhere('ingredient', 'LIKE', '%'.$word.'%')
                    ->orWhere('notes', 'LIKE', '%'.$word.'%');
            });
        }
    }

    public function allListingBarcode(Request $request)
    {
        try {
            // Force pagination: default 50, max 100
            $perPage = min((int) ($request->get('per_page', 50)), 100);

            if (! empty($request->search)) {
                $searchTerm = trim($request->search);

                // Try exact barcode match first (uses index)
                $query = Product::select('products.*', 'product_name as fruit_name', 'product_image as fruit_image')
                    ->matchingBarcode($searchTerm)
                    ->where('status', 1)
                    ->orderBy('product_name');

                $products = $query->paginate($perPage);
            } else {
                $query = Product::select('products.*', 'product_name as fruit_name', 'product_image as fruit_image')
                    ->where('status', 1);

                $products = $query->paginate($perPage);
            }
            $data = [
                'status' => 'success',
                'alldata' => $products->items(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ];

            foreach ($data['alldata'] as $key => $value) {
                $data['alldata'][$key]['url'] = $this->getProductImageUrl($value['product_image']);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
