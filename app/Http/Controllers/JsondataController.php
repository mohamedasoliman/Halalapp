<?php

namespace App\Http\Controllers;

use App\Models\json2;
use App\Models\jsondata;
use App\Models\jsonmeta;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class JsondataController extends Controller
{
    public function index()
    {
        return view('admin.JsonData.create');
    }

    public function show()
    {
        $data = jsondata::all();
        return response()->json($data);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string',
            'description' => 'required|string'
        ]);


        $data1 = jsondata::create([
            'Name' => $validatedData['name'],
            'slug' => Str::slug($validatedData['name']),
            'Description' => $validatedData['description']
        ]);

        return redirect()->back()->with('success', 'JSON Data Added');;
    }

    public function allJsonDataAdmin($id)
    {
        try {
            // Eager load only json2 and jsonmeta related to the jsondata with the given ID
            $jsondata = jsondata::with(['json2.jsonmeta'])->find($id);

            // Initialize an array to hold all meta data rows and keys
            $metaDataRows = [];
            $allMetaKeys = []; // To track unique meta keys

            // Check if jsondata is found
            if (!$jsondata) {
                return response()->json(['message' => 'JSON data not found'], 404);
            }

            // Loop through each json2 record
            foreach ($jsondata->json2 as $json2) {
                // Create an array for this json2 entry, showing json2_id
                $rowData = [
                    'json2_id' => $json2->id, // Add json2 ID here
                ];

                // Loop through related jsonmeta records to fill in the row data
                foreach ($json2->jsonmeta as $meta) {
                    $rowData[$meta->meta_key] = $meta->meta_value; // Set the actual value from jsonmeta
                    $allMetaKeys[$meta->meta_key] = true; // Track all unique meta keys
                }

                // Fill missing meta keys with an empty string
                foreach (array_keys($allMetaKeys) as $key) {
                    if (!isset($rowData[$key])) {
                        $rowData[$key] = ''; // Default value for missing keys
                    }
                }

                // Add this row's data to the main array
                $metaDataRows[] = $rowData;
            }

            // Return the view with only json2.jsonmeta and the json2 ID
            return view('admin.JsonData.show', compact('metaDataRows', 'allMetaKeys', 'jsondata'));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    public function DeleteallJsonDataAdmin($id)
    {
        try {
            $jsondata = jsondata::with(['json2.jsonmeta'])->find($id);
            if ($jsondata) {
                // Delete all related records in the json2 table
                foreach ($jsondata->json2 as $json2) {
                    foreach ($json2->jsonmeta as $meta) {
                        $meta->delete();
                    }
                    $json2->delete();
                }
                $jsondata->delete();
                return response()->json(['success' => 'JSON Data Deleted', 200]);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage());
        }
    }

    public function DeleteJsonDataAdmin($id)
    {
        try {
            $json2 = json2::with('jsonmeta')->find($id);
            if ($json2) {
                // Delete all related records in the json2 table
                foreach ($json2->jsonmeta as $meta) {
                    $meta->delete();
                }
                $json2->delete();
            }
            // return redirect()->back();
            return response()->json(['success' => 'JSON Data Deleted', 200]);
        } catch (\Exception $e) {
            return response()->json($e->getMessage());
        }
    }


    // controller function for api route
    public function allJsonData(Request $request, $id)
    {
        // Find jsondata and eager load related json2 and jsonmeta records
        $jsondata = jsondata::with(['json2.jsonmeta'])->find($id);

        $apikey = $request->header('X-API-Key'); //header key
        $appkey = config('app.key'); //app key in env file

        if ($apikey != $appkey) {
            return response()->json(['You are Unauthorized'], 400);
        }

        // Initialize the output array
        $output = [];

        // Copy over the basic jsondata fields
        $output['id'] = $jsondata->id;
        $output['Name'] = $jsondata->Name;
        $output['slug'] = $jsondata->slug;
        $output['Description'] = $jsondata->Description;

        // Initialize the json2 array
        $output['json2'] = [];

        // Loop through each json2 record
        foreach ($jsondata->json2 as $json2) {
            // Create an associative array for json2 data, including id
            $json2Data = [
                'id' => $json2->id, // Add json2 id here
            ];

            // Loop through jsonmeta and add key-value pairs directly to json2 data
            foreach ($json2->jsonmeta as $meta) {
                $json2Data[$meta->meta_key] = $meta->meta_value;
            }

            // Add the json2 data (with id and combined meta) to the output
            $output['json2'][] = $json2Data;
        }

        // Return the transformed jsondata without created_at and updated_at
        return response()->json($output);
    }


    public function allJsonDataApi(Request $request, $id)
    {
        try {

            // Check if the request has any data
            if (empty($request->all())) {
                return response()->json(['message' => 'No data provided'], 400);
            }
            // Validate that 'metadata' is required and it should be an array
            $validatedData = $request->validate([
                '*' => 'required',
            ]);

            $apikey = $request->header('X-API-Key'); //header key
            $appkey = config('app.key'); //app key in env file

            if ($apikey != $appkey) {
                return response()->json(['message' => 'You are Unauthorized'], 400);
            }

            // Create a new record in json2 linked to jsondata
            $data2 = json2::create([
                'jsondata_id' => $id,
            ]);

            // Loop through the metadata array and insert each key-value pair
            foreach ($validatedData as $key => $value) {
                jsonmeta::create([
                    'json2_id' => $data2->id, // Link the new data to json2
                    'meta_key' => $key,
                    'meta_value' => $value,
                ]);
            }

            return response()->json(['success' => 'Inserted Successfully'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
}
