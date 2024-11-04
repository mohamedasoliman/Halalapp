<?php

namespace App\Http\Controllers\Admin\ResturantControllers;

use League\Csv\Reader;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\ResturantModel\Resturant;
use Illuminate\Support\Facades\Storage;

class ResturantManagementController extends Controller
{
    // function to show data using datatable plugins
    public function index(Request $request)
    {
        if($request->ajax()){
            $data = Resturant::select(
                'id',
                'Resturant_name',
                'Description',
                'Website',
                'Logo',
                'Image_1',
                'Image_2',
                'Image_3',
                'Image_4',
                'Image_5',
                'Image_6',
                'Phone',
                'Address',
                'Longitude',
                'Latitude'
            );

            return DataTables::of($data)
                ->make(true);
        }
        return view('admin.resturant.resturant_index');
    }

    // function to store data
    public function store(Request $request)
    {
        $request->validate([
            'logo' => 'image|mimes:jpeg,png,jpg,gif',
            'image1' => 'image|mimes:jpeg,png,jpg,gif',
            'image2' => 'image|mimes:jpeg,png,jpg,gif',
            'image3' => 'image|mimes:jpeg,png,jpg,gif',
            'image4' => 'image|mimes:jpeg,png,jpg,gif',
            'image5' => 'image|mimes:jpeg,png,jpg,gif',
            'image6' => 'image|mimes:jpeg,png,jpg,gif',
            'longitude' => 'required',
            'latitude' => 'required',

        ]);
        $resturant = new Resturant();
        $resturant->Resturant_name = $request->input('resturantName');
        $resturant->Description = $request->input('description');
        $resturant->Website = $request->input('website');
        $resturant->Phone = $request->input('phone');
        $resturant->Address = $request->input('address');
        $resturant->Longitude = $request->input('longitude');
        $resturant->Latitude = $request->input('latitude');

        // Store Logo
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $logoname = $logo->getClientOriginalName();
            $logo->move('public_html/upload/resturant', $logoname);
            $resturant->Logo = $logoname;
        }

        // Store Images 1 to 6
        for ($i = 1; $i <= 6; $i++) {
            $fieldName = 'image' . $i;
            if ($request->hasFile($fieldName)) {
                $image = $request->file($fieldName);
                $imageName = $image->getClientOriginalName();
                $image->move('public_html/upload/resturant', $imageName);
                $resturant->{'Image_' . $i} = $imageName;
                //  Storage::disk('public')->setVisibility('upload/resturant/' . $imageName, 'public');
            }
        }

        $resturant->save();

        return redirect()->back();
    }

    // function to edit data of any record
    public function edit($id)
    {
        $id = Resturant::find($id);
        if (!$id) {
            return response()->json(['success' => false, 'message' => 'id not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $id]);
    }

    // function to update that specific record
    public function update(Request $request, $id) {
        $request->validate([
            'logo' => 'image|mimes:jpeg,png,jpg,gif',
            'image1' => 'image|mimes:jpeg,png,jpg,gif',
            'image2' => 'image|mimes:jpeg,png,jpg,gif',
            'image3' => 'image|mimes:jpeg,png,jpg,gif',
            'image4' => 'image|mimes:jpeg,png,jpg,gif',
            'image5' => 'image|mimes:jpeg,png,jpg,gif',
            'image6' => 'image|mimes:jpeg,png,jpg,gif',

        ]);
        $restaurant = Resturant::find($id);

        if (!$restaurant) {
            return response()->json(['success' => false, 'error' => 'Record not found']);
        }

        $restaurant->Resturant_name = $request->input('editresturantName');
        $restaurant->Description = $request->input('editDescription');
        $restaurant->Website = $request->input('editWebsite');
        $restaurant->Phone = $request->input('editPhone');
        $restaurant->Address = $request->input('editAddress');
        $restaurant->Longitude = $request->input('editLongitude');
        $restaurant->Latitude = $request->input('editLatitude');

        // Handle the 'Logo' file separately
        if ($request->hasFile('Logo')) {
            $logoName = $this->uploadFile($request->file('Logo'));
            $restaurant->Logo = $logoName;
        }

        // Handle Images1 to Images6
        $images = ['Image_1', 'Image_2', 'Image_3', 'Image_4', 'Image_5', 'Image_6'];
        foreach ($images as $imageField) {
            if ($request->hasFile($imageField)) {
                $imageName = $this->uploadFile($request->file($imageField));
                $restaurant->$imageField = $imageName;
            }
        }

        $restaurant->save();

        return response()->json(['success' => true, 'message' => 'Restaurant updated successfully']);
    }
    // private function to handle file upload
    private function uploadFile($file) {
        $fileName = $file->getClientOriginalName();
        $file->move('public_html/upload/resturant', $fileName);
        return $fileName;
    }




    // function to delete all the records
    public function deleteall(){
        try {
            Resturant::Truncate();
            return response()->json(['success'=>true]);
        } catch (\Throwable $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()]);
        }
    }

    // function to delete any specific record
    public function delete(Request $request,$id){
        $data = Resturant::find($id);

        if ($data) {
            $data->delete();
            return response()->json(['success'=>true]);
        }else
        {
            return response()->json(['success'=>false,'message'=>'id not found']);
        }
    }

    // function to import csv data
    public function import(Request $request)
    {
        try {
            if ($request->hasFile('csv_file')) {
                $path = $request->file('csv_file')->getRealPath();
                $csv = Reader::createFromPath($path);
                $csv->setHeaderOffset(0);

                foreach ($csv->getRecords() as $record) {
                    // Modify this code to match your database schema
                    Resturant::create([
                        'Resturant_name' => $record['Name'],
                        'Description' => $record['Descriptions'],
                        'Website' => $record['Website'],
                        'Logo' => $record['Logo'],
                        'Image_1' => $record['Image 1'],
                        'Image_2' => $record['Image 2'],
                        'Image_3' => $record['Image 3'],
                        'Image_4' => $record['Image 4'],
                        'Image_5' => $record['Image 5'],
                        'Image_6' => $record['Image 6'],
                        'Phone' => $record['Phone'],
                        'Address' => $record['Address'],
                        'Longitude' => $record['Longitude'],
                        'Latitude' => $record['Latitude'],
                    ]);
                }

                return redirect()->back()->with('success', 'CSV data imported successfully.');
            }

            return redirect()->back()->with('error', 'Please select a valid CSV file.');
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('CSV import error: ' . $e->getMessage());
            // dd($e);

            // Handle the error gracefully and provide a user-friendly message
            return redirect()->back()->with('error', 'An error occurred while importing CSV data.');
        }
    }

    //function to show all data in api
    public function api(Request $request)
    {
        $resturant = Resturant::all();

        $apikey = $request->header('X-API-Key');
        $appkey = config('app.key');

        if ($apikey != $appkey) {
            return response()->json(['message'=>'You are unauthorized']);
        }else{
            return response()->json(['data'=>$resturant]);
        }

    }

}
