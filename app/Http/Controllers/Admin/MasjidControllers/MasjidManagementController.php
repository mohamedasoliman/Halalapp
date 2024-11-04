<?php

namespace App\Http\Controllers\Admin\MasjidControllers;

use League\Csv\Reader;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use App\Models\MasjidModel\Masjid;
use App\DataTables\MasjidDataTable;
use App\Http\Controllers\Controller;

class MasjidManagementController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Masjid::select(
                'id',
                'Masjid_name',
                'Address',
                'Area_id',
                'Area_name',
                'Website',
                'Fajar',
                'Duhur',
                'Asr',
                'Maghrib',
                'Ishaa',
                'Jumaa',
                'Longitude',
                'Latitude'
            );

            return DataTables::of($data)
                ->make(true);
        }

        return view('admin.masjid.masjid_index');


    }

    public function store(Request $request)
    {
        try {

            $validatedData = $request->validate([
                'masjidName' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'areaid' => 'required|numeric',
                'areaname' => 'required|string|max:255',
                'website' => 'nullable|string|max:255',
                'fajar' => 'required|string|max:255',
                'duhur' => 'required|string|max:255',
                'asr' => 'required|string|max:255',
                'maghrib' => 'required|string|max:255',
                'ishaa' => 'required|string|max:255',
                'jumaa' => 'required|string|max:255',
                'logitude' => 'required|string|max:255',
                'latitude' => 'required|string|max:255',
            ]);

            $masjid = new Masjid();
            $masjid->Masjid_name = $validatedData['masjidName'];
            $masjid->Address = $validatedData['address'];
            $masjid->Area_id = $validatedData['areaid'];
            $masjid->Area_name = $validatedData['areaname'];
            $masjid->Website = $validatedData['website'];
            $masjid->Fajar = $validatedData['fajar'];
            $masjid->Duhur = $validatedData['duhur'];
            $masjid->Asr = $validatedData['asr'];
            $masjid->Maghrib = $validatedData['maghrib'];
            $masjid->Ishaa = $validatedData['ishaa'];
            $masjid->Jumaa = $validatedData['jumaa'];
            $masjid->Longitude = $validatedData['logitude'];
            $masjid->Latitude = $validatedData['latitude'];

            // Save the new masjid record to the database
            $masjid->save();

            return response()->json(['success' => true]);
        }
        catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $id = Masjid::find($id);
        if (!$id) {
            return response()->json(['success' => false, 'message' => 'id not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $id]);
        // return view('admin.masjid.masjidmodel');
    }

    public function update(Request $request, $id)
    {

        $masjid = Masjid::find($id);

        if (!$masjid) {
            return response()->json(['success' => false, 'message' => 'Masjid not found'], 404);
        }

        $masjid->update([
            'Masjid_name' => $request->input('editMasjidName'),
            'Address' => $request->input('editAddress'),
            'Area_id' => $request->input('editAreaId'),
            'Area_name' => $request->input('editAreaName'),
            'Website' => $request->input('editWebsite'),
            'Fajar' => $request->input('editFajar'),
            'Duhur' => $request->input('editDuhur'),
            'Asr' => $request->input('editAsr'),
            'Maghrib' => $request->input('editMaghrib'),
            'Ishaa' => $request->input('editIshaa'),
            'Jumaa' => $request->input('editJumaa'),
            'Longitude' => $request->input('editLongitude'),
            'Latitude' => $request->input('editLatitude'),
        ]);

        return response()->json(['success' => true, 'message' => 'Masjid updated successfully']);
    }
    public function delete(Request $request, $id)
    {

        $masjid = Masjid::find($id);

        if ($masjid) {
            $masjid->delete();
            return response()->json(['success' => true]);
        } else {
            return response()->json(['success' => false, 'message' => 'id not found.']);
        }
    }

    public function deleteall()
    {
        try {
            Masjid::truncate();

            return response()->json(['success'=>true]);
        } catch (\Exception $e) {
           return response()->json(['success'=>false,'message'=>$e->getMessage()]);
        }
    }

    //import csv file function
    public function import(Request $request)
    {
        if ($request->hasFile('csv_file')) {
            $path = $request->file('csv_file')->getRealPath();
            $csv = Reader::createFromPath($path);
            $csv->setHeaderOffset(0);

            foreach ($csv->getRecords() as $record) {
                Masjid::create([
                    'Masjid_name' => $record['Masjid Name'],
                    'Address' => $record['Address'],
                    'Area_id' => $record['Area ID'],
                    'Area_name' => $record['Area Name'],
                    'Website' => $record['Website'],
                    'Fajar' => $record['Fajr'],
                    'Duhur' => $record['Duhur'],
                    'Asr' => $record['Asr'],
                    'Maghrib' => $record['Maghrib'],
                    'Ishaa' => $record['Isha'],
                    'Jumaa' => $record['Jumaa'],
                    'Latitude' => $record['Latitude'],
                    'Longitude' => $record['Longitude'],
                ]);
            }

            return redirect()->back()->with('success', 'CSV data imported successfully.');
        }

        return redirect()->back()->with('error', 'Please select a valid CSV file.');
    }

    public function apishow(Request $request)
    {
        //get the column name from the request
        $column_name = $request->all();

        $apikey = $request->header('X-API-Key');//header key
        $appkey = config('app.key');//app key in env file

        //prepare the query
        $query = Masjid::query();

        if (!empty($column_name)) {
            foreach ($column_name as $key => $value) {
                // Check if the column exists in the "resturants" table
                if (Schema::hasColumn('masjids', $key)) {
                    $query->where($key, $value);
                }
            }
        }

        $data = $query->get();

        if ($data->isEmpty()) {
            return response()->json(['message'=>'No Record Found'],401);
        }

        if ($apikey != $appkey) {
            return response()->json(['You are Unauthorized'],400);
        }

        return response()->json(['data'=>$data],200);
    }
}
