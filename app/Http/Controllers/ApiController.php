<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductModel\Product;

class ApiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function allListing(Request $request)
    {
        try {
            // Check if per_page is provided
            $perPage = $request->get('per_page');
            
            // Check if halal_only filter is requested
            $halalOnly = $request->get('halal_only');

            if (!empty($request->search)) {
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
                        $searchTerm . '%',              // Starts with
                        '%' . $searchTerm . '%',        // Contains
                        '%' . $searchTerm,              // Ends with
                        $searchTerm,                    // Sounds like (SOUNDEX)
                        $searchTerm,                    // Exact barcode
                        '%' . $searchTerm . '%',        // Barcode contains
                        '%' . $searchTerm . '%',        // Category contains
                        '%' . $searchTerm . '%',        // Ingredient contains
                        '%' . $searchTerm . '%',        // Notes contains
                        $searchTerm,                    // Category sounds like
                        $searchTerm                     // Ingredient sounds like
                    ])
                    ->where(function($q) use ($searchTerm) {
                        $q->where('product_name', 'LIKE', '%' . $searchTerm . '%')
                          ->orWhere('Barcode', 'LIKE', '%' . $searchTerm . '%')
                          ->orWhere('category', 'LIKE', '%' . $searchTerm . '%')
                          ->orWhere('ingredient', 'LIKE', '%' . $searchTerm . '%')
                          ->orWhere('notes', 'LIKE', '%' . $searchTerm . '%')
                          ->orWhereRaw('SOUNDEX(product_name) = SOUNDEX(?)', [$searchTerm])
                          ->orWhereRaw('SOUNDEX(category) = SOUNDEX(?)', [$searchTerm])
                          ->orWhereRaw('SOUNDEX(ingredient) = SOUNDEX(?)', [$searchTerm]);
                    })
                    ->where('status', 1);
                    
                // Add halal filter if requested
                if ($halalOnly == '1' || $halalOnly == 'true') {
                    $query->where('halal_status', 0);
                }
                
                // Order by relevance score first, then alphabetically
                $query->orderByDesc('relevance_score')
                      ->orderBy('product_name');
            } else {
                $query = Product::select('products.*', 'product_name as fruit_name', 'product_image as fruit_image')
                    ->where('status', 1);
                    
                // Add halal filter if requested
                if ($halalOnly == '1' || $halalOnly == 'true') {
                    $query->where('halal_status', 0);
                }
            }

            // Fetch results
            if ($perPage) {
                // Paginate the results
                $products = $query->paginate($perPage);
                $data = [
                    'status' => 'success',
                    'alldata' => $products->items(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total()
                ];
            } else {
                // Get all results without pagination
                $products = $query->get();
                $data = [
                    'status' => 'success',
                    'alldata' => $products,
                    'total' => $products->count()
                ];
            }

            // Add URL to each item
            if ($perPage) {
                foreach ($data['alldata'] as $key => $value) {
                    $data['alldata'][$key]['url'] = asset('public/upload/product_images/' . $value['product_image']);
                }
            } else {
                foreach ($products as $key => $value) {
                    $products[$key]['url'] = asset('public/upload/product_images/' . $value->product_image);
                }
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function allListingBarcode(Request $request)
    {
        try {
            // Check if per_page is provided
            $perPage = $request->get('per_page');

            if (!empty($request->search)) {
                $searchTerm = trim($request->search);
                
                // Exact barcode match only - no fuzzy search for barcodes
                $query = Product::select('products.*', 'product_name as fruit_name', 'product_image as fruit_image')
                    ->where('Barcode', $searchTerm)
                    ->where('status', 1)
                    ->orderBy('product_name');
            } else {
                $query = Product::select('products.*', 'product_name as fruit_name', 'product_image as fruit_image')
                    ->where('status', 1);
            }

            // Fetch results
            if ($perPage) {
                // Paginate the results
                $products = $query->paginate($perPage);
                $data = [
                    'status' => 'success',
                    'alldata' => $products->items(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total()
                ];
            } else {
                // Get all results without pagination
                $products = $query->get();
                $data = [
                    'status' => 'success',
                    'alldata' => $products,
                    'total' => $products->count()
                ];
            }

            // Add URL to each item
            if ($perPage) {
                foreach ($data['alldata'] as $key => $value) {
                    $data['alldata'][$key]['url'] = asset('public/upload/product_images/' . $value['product_image']);
                }
            } else {
                foreach ($products as $key => $value) {
                    $products[$key]['url'] = asset('public/upload/product_images/' . $value->product_image);
                }
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
