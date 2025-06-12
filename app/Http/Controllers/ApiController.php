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
                $searchTerm = $request->search;
                
                // Implement fuzzy search with relevance scoring for product name only
                $query = Product::select('products.*', 'product_name as fruit_name', 'product_image as fruit_image')
                    ->selectRaw('
                        (CASE 
                            WHEN product_name LIKE ? THEN 100
                            WHEN product_name LIKE ? THEN 90
                            WHEN product_name LIKE ? THEN 80
                            ELSE 0
                        END) as relevance_score
                    ', [
                        $searchTerm,                    // Exact match
                        $searchTerm . '%',              // Starts with
                        '%' . $searchTerm . '%'         // Contains
                    ])
                    ->where('product_name', 'LIKE', '%' . $searchTerm . '%')
                    ->where('status', 1);
                    
                // Add halal filter if requested
                if ($halalOnly == '1' || $halalOnly == 'true') {
                    $query->where('halal_status', 0);
                }
                
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
            foreach ($products as $key => $value) {
                $products[$key]['url'] = asset('public/upload/product_images/' . $value->product_image);
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
                $query = Product::select('products.*', 'product_name as fruit_name', 'product_image as fruit_image')->
                where('Barcode', 'LIKE', "%{$request->search}%")->where('status', 1);
            } else {
                $query = Product::select('products.*', 'product_name as fruit_name', 'product_image as fruit_image')->where('status', 1);
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
            foreach ($products as $key => $value) {
                $products[$key]['url'] = asset('public/upload/product_images/' . $value->product_image);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json($e->getMessage());
        }
    }
}
