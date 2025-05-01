<?php

namespace App\Http\Controllers\Admin\ProductController;

use DB;
use Session;
use App\User;
use Validator;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;
use App\Models\ProductModel\Product;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;
use League\Csv\Reader;


class ProductController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!userRoleCheck([1])) {
                return redirect()->route('admin.dashboard');
            }
            return $next($request);
        });
    }
    public function showForm()
    {
        return view('admin.products.import_form');
    }

    public function import(Request $request)
    {
        try {
            $request->validate([
                'csv_file' => 'required|mimes:csv,txt',
            ]);

            if ($request->hasFile('csv_file')) {
                $file = $request->file('csv_file');
                $path = $file->getRealPath();
                
                // Use League\CSV like the Masjid importer
                $csv = Reader::createFromPath($path);
                $csv->setHeaderOffset(0);

                foreach ($csv->getRecords() as $record) {
                    Product::create([
                        'product_name' => $record['Product Name'] ?? 'Unnamed Product',
                        'Barcode' => !empty($record['Barcode']) ? $record['Barcode'] : '0',
                        'product_image' => $record['Product Image'] ?? null,
                        'halal_status' => !empty($record['Halal Status']) ? $record['Halal Status'] : 2,
                        'Certification_Status' => !empty($record['Certification Status']) ? $record['Certification Status'] : '',
                        'category' => $record['Category'] ?? null,
                        'notes' => $record['Notes'] ?? null,
                        'ingredient' => $record['Ingredients'] ?? null,
                    ]);
                }
                
                return redirect()->back()->with('success', 'CSV file imported successfully.');
            } else {
                return redirect()->back()->with('error', 'Please select a valid CSV file.');
            }
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('CSV import error: ' . $e->getMessage());
            
            // Return a user-friendly error message
            return redirect()->back()->with('error', 'An error occurred while importing the CSV file: ' . $e->getMessage());
        }
    }

    public function deleteByCategory(Request $request)
    {
        $category = $request->input('category');

        // Add your logic here to delete products in the selected category
        Product::where('category', $category)->delete();

        // Return a response indicating the success or failure of the operation
        return response()->json(['status' => 1, 'message' => 'Products deleted successfully']);
    }

    public function deleteAllProducts(Request $request)
    {
        // Delete all products
        Product::truncate();

        return response()->json(['status' => 1, 'message' => 'All products have been deleted.']);
    }






    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */







    public function index(Request $request)
    {

        if ($request->ajax()) {
            $Product = Product::orderBy('status', 'DESC')->orderBy('id', 'DESC')->get();
            return DataTables::of($Product)
                ->addIndexColumn()
                ->addColumn('status', function ($user) {
                    if ($user->status == '1') {
                        return "<label data-id='" . $user->id . "' class='label label-info status-update status_list'>Active</label>";
                    } else {
                        return "<label data-id='" . $user->id . "' class='label label-danger status-update status_list'>Not Active</label>";
                    }
                })
                ->addColumn('halal_status', function ($user) {
                    if ($user->halal_status == '1') {
                        return "<label data-id='" . $user->id . "' class='label label-danger'>Not Halal</label>";
                    } else if ($user->halal_status == '0') {
                        return "<label data-id='" . $user->id . "' class='label label-success'>Halal</label>";
                    } else {
                        return "<label data-id='" . $user->id . "' class='label label-warning'>Not Sure</label>";
                    }
                })
                ->editColumn('product_image', function ($row) {
                    $url = asset('public/upload/product_images/' . $row->product_image);
                    return '<img src="' . $url . '" border="0" width="40" class="img-rounded" align="center" />';
                })
                ->addColumn('action', function ($user) {
                    $data = '<a href="javascript:;" onclick="editproductModel(' . $user->id . ')" class="btn btn-outline-warning" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Edit Category"><i class="icofont icofont-edit"></i></a>
                <button type="button" class="btn btn-outline-danger" onclick="deleteproductModel(' . $user->id . ')" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Delete Category"><i class="icofont icofont-trash"></i>
                </button> ';
                    return $data;
                })
                ->rawColumns(['action', 'status', 'halal_status', 'product_image', 'Barcode', 'Certification_Status', 'category', 'notes', 'ingredient'])
                ->make(true);
        } else {
            $cat = new Product();
            $categories = $cat->getAllUniqueCategories();

            return view('admin.products.index', compact('categories'));
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
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
            'product_name.required' => 'Please enter Product name',
        ];

        $validatedData = $request->validate([
            'product_name' => 'required',
            'product_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Validate image input
        ], $messages);

        $originalImage = $request->file('product_image');
        $imageName = '';

        if ($originalImage) {
            // Generate a unique name for the image
            $imageName = time() . '_' . $originalImage->getClientOriginalName();

            // Define the upload path
            $path = dirname(base_path()) . "/public_html/public/upload/product_images/";
            // dd($path);

            // Create the directory if it doesn't exist
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }

            // Move the uploaded image to the desired path
            $originalImage->move($path, $imageName);
        }

        Product::create([
            'product_name' => $request->product_name, // Make sure to use the correct input name
            'product_image' => $imageName,
            'halal_status' => $request->halal_status,
            'status' => 1,
            'Barcode' => $request->Barcode,
            'Certification_Status' => $request->Certification_Status,
            'category' => $request->category,
            'notes' => $request->notes,
            'ingredient' => $request->ingredient,
        ]);

        return json_encode([
            'status' => 1
        ]);
    }



    public function show($id)
    {
        //
    }

    public function edit($id)
    {
        $Product = Product::find($id);
        $mode = 'Edit';

        $editData = view('admin.products.edit', compact('Product', 'mode'))->render();
        return json_encode([
            'status' => 1,
            'data' => $editData
        ]);
    }

    public function update(Request $request)
    {
        $categoryId = $request->update_id;

        $messages = [
            'product_name.required' => 'Please enter Product name',
        ];

        $validatedData = $request->validate([
            'product_name' => 'required',
        ], $messages);

        $originalImage = $request->file('product_image');
        $imageName = '';

        if ($originalImage) {
            // Generate a unique name for the image
            $imageName = time() . '_' . $originalImage->getClientOriginalName();
            // Define the upload path
            $path = dirname(base_path()) . "/public_html/public/upload/product_images/";
            // dd($path);

            // Create the directory if it doesn't exist
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }

            // Move the uploaded image to the desired path
            $originalImage->move($path, $imageName);
        }

        // Prepare the update data
        $updateData = [
            'product_name' => $request->product_name, // Ensure you're using the correct field name
            'halal_status' => $request->halal_status,
            'status' => $request->status ? $request->status : 0,
            'Barcode' => $request->Barcode,
            'Certification_Status' => $request->Certification_Status,
            'category' => $request->category,
            'notes' => $request->notes,
            'ingredient' => $request->ingredient,
        ];

        // If a new image was uploaded, add it to the update data
        if (!empty($originalImage)) {
            $updateData['product_image'] = $imageName;
        }

        // Update the product in the database
        Product::where('id', $categoryId)->update($updateData);

        return json_encode(['status' => 1]);
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

        return response()->json(['status' => 1, 'messages' => 'Product deleted successfully']);
    }

    public function MainCategoryDelete(Request $request)
    {

        $Product = Product::find($request->id);
        $Product->delete();

        Session::flash('error', 'Product deleted successfully!');
        return redirect()->route('maincategory.index');
    }

    public function checkUniqueCityName(Request $request)
    {

        $name = $request->categoryName;
        $id = $request->categoryID;
        if (!empty($id)) {
            $Product = Product::where('id', '!=', $id)->where('city_name', $name)->get();
            if ($Product->count()) {
                return json_encode([
                    'msg' => 'true'
                ]);
            } else {
                return json_encode([
                    'msg' => 'false'
                ]);
            }
        } else {
            $Product = Product::where('city_name', $name)->get();
            if ($Product->count()) {
                return json_encode([
                    'msg' => 'true'
                ]);
            } else {
                return json_encode([
                    'msg' => 'false'
                ]);
            }
        }
    }

    public function statusUpdate($id)
    {
        $Product = Product::find($id);
        if ($Product->status == 1) {
            $status = 0;
        } else {
            $status = 1;
        }
        Product::where('id', $id)->update([
            'status' => $status,
        ]);

        return response()->json(['status' => TRUE, 'message' => 'Product status change successfully.']);
    }
}
