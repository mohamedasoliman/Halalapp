<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\User;
use Session;
use Validator;
use DB;
use DataTables;
use App\Models\ProductModel\Product;
use Illuminate\Support\Facades\Hash;
use Image;

class MobileAppFoodController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return Product::paginate(3);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $city = Product::findOrFail($id);
        return $city;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $messages = [
            'food_name.required' => 'Please enter Food name',
        ];

        // Validate the request
        // $validatedData = $request->validate([
        //     'food_name' => 'required',
        //     'food_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Example validation for the image
        // ], $messages);

        $originalImage = $request->file('food_image');
        $imageName = '';

        if ($originalImage) {
            // Generate a unique filename
            $imageName = time() . '_' . $originalImage->getClientOriginalName();

            // Define the path for storing the uploaded image
            $path = dirname(base_path()) . "/public_html/public/upload/fruit/";

            // Create the directory if it doesn't exist
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }

            // Move the uploaded file to the specified directory
            $originalImage->move($path, $imageName);
        }

        // Store product data in the database
        Product::create([
            'product_name' => $request->food_name,
            'product_image' => $imageName,
            'halal_status' => (!empty($request->food_status)) ? $request->food_status : 0,
            'Certification_Status' => $request->certification_status,
            'notes' => $request->notes,
            'ingredient' => $request->ingredient,
            'Barcode' => $request->barcode,
            'status' => 1,
        ]);

        return response()->json(['status' => 1]);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $messages = [
            'food_name.required' => 'Please enter Food name',
        ];

        // Validate the request (uncomment this if you want to enforce validation)
        /*$validatedData = $request->validate([
        'food_name' => 'required',
    ], $messages);*/

        // Prepare data for update
        $data = [
            'product_name' => $request->food_name,
            'halal_status' => (!empty($request->food_status)) ? $request->food_status : 0,
            'Certification_Status' => $request->certification_status,
            'notes' => $request->notes,
            'ingredient' => $request->ingredient,
            'status' => $request->status ? $request->status : 0,
            'Barcode' => $request->barcode,
        ];

        $originalImage = $request->file('food_image');

        // Check if a new image is uploaded
        if ($originalImage) {
            // Generate a unique filename
            $imageName = time() . '_' . $originalImage->getClientOriginalName();

            // Define the path for storing the uploaded image
            $originalPath = public_path('/upload/fruit/');

            // Create the directory if it doesn't exist
            if (!file_exists($originalPath)) {
                mkdir($originalPath, 0777, true);
            }

            // Move the uploaded file to the specified directory
            $originalImage->move($originalPath, $imageName);

            // Update the product image in the data array
            $data["product_image"] = $imageName;
        }

        // Update the product record in the database
        Product::where('id', $id)->update($data);

        return response()->json(['status' => 1]);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $Product = Product::find($id);
        $Product->delete();

        return response()->json(['status' => 1, 'messages' => 'Food delete successfully']);
    }
}
