<?php

namespace App\Http\Controllers\Admin\ProductController;

use DB;
use Session;
use App\User;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;
use App\Models\ProductModel\Product;
use App\Support\ProductBarcode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;
use League\Csv\Reader;
use League\Csv\Writer;


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

                $skippedDuplicates = 0;
                foreach ($csv->getRecords() as $record) {
                    $rawBarcode = ! empty($record['Barcode']) ? $record['Barcode'] : '0';
                    $barcode = ProductBarcode::canonical($rawBarcode);
                    if (Product::matchingBarcode($barcode)->exists()) {
                        $skippedDuplicates++;
                        continue;
                    }

                    Product::create([
                        'product_name' => $record['Product Name'] ?? 'Unnamed Product',
                        'Barcode' => $barcode,
                        'product_image' => $record['Product Image'] ?? null,
                        'halal_status' => (isset($record['Halal Status']) && $record['Halal Status'] !== '') ? $record['Halal Status'] : 2,
                        'Certification_Status' => !empty($record['Certification Status']) ? $record['Certification Status'] : '_',
                        'category' => $record['Category'] ?? null,
                        'notes' => $record['Notes'] ?? null,
                        'ingredient' => $record['Ingredients'] ?? null,
                    ]);
                }
                
                Cache::increment('products_cache_version');
                return redirect()->back()->with(
                    'success',
                    "CSV file imported successfully. Skipped {$skippedDuplicates} duplicate barcode(s)."
                );
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

    /**
     * Export all products to CSV file.
     * Uses the same column format as import for easy round-trip editing.
     */
    public function export()
    {
        try {
            $products = Product::orderBy('id', 'ASC')->get();

            // Create CSV writer
            $csv = Writer::createFromString('');

            // Add header row (matching import format)
            $csv->insertOne([
                'Product Name',
                'Product Image',
                'Barcode',
                'Halal Status',
                'Certification Status',
                'Category',
                'Notes',
                'Ingredients'
            ]);

            // Add product rows
            foreach ($products as $product) {
                $csv->insertOne([
                    $product->product_name,
                    $product->product_image,
                    $product->Barcode,
                    $product->halal_status,
                    $product->Certification_Status,
                    $product->category,
                    $product->notes,
                    $product->ingredient
                ]);
            }

            // Generate filename with date
            $filename = 'products_export_' . date('Y-m-d_His') . '.csv';

            // Return CSV download
            return response($csv->toString(), 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to export CSV: ' . $e->getMessage());
        }
    }

    public function deleteByCategory(Request $request)
    {
        $category = $request->input('category');

        // Add your logic here to delete products in the selected category
        Product::where('category', $category)->delete();

        Cache::increment('products_cache_version');

        return response()->json(['status' => 1, 'message' => 'Products deleted successfully']);
    }

    public function deleteAllProducts(Request $request)
    {
        // Delete all products
        Product::truncate();

        Cache::increment('products_cache_version');

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
                    // Support both local filenames and external URLs
                    $image = $row->product_image;
                    if (!empty($image) && (str_starts_with($image, 'http://') || str_starts_with($image, 'https://'))) {
                        $url = $image;
                    } else {
                        $url = asset('public/upload/product_images/' . $image);
                    }
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
            'halal_status' => 'nullable|in:0,1,2',
            'Barcode' => 'required|string|max:20',
        ], $messages);

        $barcode = ProductBarcode::canonical($request->Barcode);
        if (Product::matchingBarcode($barcode)->exists()) {
            throw ValidationException::withMessages([
                'Barcode' => 'A product with this barcode already exists.',
            ]);
        }

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
            'halal_status' => $request->filled('halal_status') ? $request->halal_status : '2',
            'status' => 1,
            'Barcode' => $barcode,
            'Certification_Status' => $request->Certification_Status,
            'category' => $request->category,
            'notes' => $request->notes,
            'ingredient' => $request->ingredient,
        ]);

        Cache::increment('products_cache_version');

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
            'halal_status' => 'nullable|in:0,1,2',
            'Barcode' => 'required|string|max:20',
        ], $messages);

        $barcode = ProductBarcode::canonical($request->Barcode);
        if (Product::matchingBarcode($barcode)->where('id', '!=', $categoryId)->exists()) {
            throw ValidationException::withMessages([
                'Barcode' => 'A product with this barcode already exists.',
            ]);
        }

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
            'status' => $request->status ? $request->status : 0,
            'Barcode' => $barcode,
            'Certification_Status' => $request->Certification_Status,
            'category' => $request->category,
            'notes' => $request->notes,
            'ingredient' => $request->ingredient,
        ];

        if ($request->filled('halal_status')) {
            $updateData['halal_status'] = $request->halal_status;
        }

        // If a new image was uploaded, add it to the update data
        if (!empty($originalImage)) {
            $updateData['product_image'] = $imageName;
        }

        // Update the product in the database
        Product::where('id', $categoryId)->update($updateData);

        Cache::increment('products_cache_version');

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

        Cache::increment('products_cache_version');

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

        Cache::increment('products_cache_version');

        return response()->json(['status' => TRUE, 'message' => 'Product status change successfully.']);
    }
}
