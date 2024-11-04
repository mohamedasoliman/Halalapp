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

            if (!empty($request->search)) {
                $query = Product::select('products.*', 'product_name as fruit_name', 'product_image as fruit_image')
                    ->where('product_name', 'LIKE', "%{$request->search}%")
                    ->where('status', 1);
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
            foreach ($products as $key => $value) {
                $products[$key]['url'] = asset('public/upload/product_images/' . $value->product_image);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json($e->getMessage());
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
