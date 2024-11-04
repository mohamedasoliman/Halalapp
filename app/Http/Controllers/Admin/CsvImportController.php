<?php

namespace App\Http\Controllers;
use App\Models\ProductModel\Product;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\Request;
use League\Csv\Reader;
class CsvImportController extends Controller
{
    public function showForm()
    {
        return view('import_form');
    }


    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|mimes:csv,txt',
        ]);

        if ($request->hasFile('csv_file')) {
            $file = $request->file('csv_file');

            // Process the CSV file and insert data into the database using Eloquent ORM
            $csv = Reader::createFromPath($file->getPathname(), 'r');
            $csv->setHeaderOffset(0);

            foreach ($csv->getRecords() as $record) {
                // Transform and save each record as a new Product
                Product::create([
                    'product_name' => $record['Product Name'],
                    'barcode' => $record['Product Barcode'],
                    'product_image' => $record['Product image'], // Assuming this column contains image URLs
                    'food_status' => $record['Food Status'],
                    'certification_status' => $record['Certification Status'],
                    'notes' => $record['Notes'],
                    'ingredient' => $record['Ingredients'],

                ]);
            }

            // Redirect back with success message
            return Redirect::route('import.form')->with('success', 'CSV file imported successfully.');
        }
}
}
