<?php

namespace App\Console\Commands;

use App\Models\ProductModel\Product;
use App\Support\ProductBarcode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use League\Csv\Reader;

class ReplaceProducts extends Command
{
    protected $signature = 'products:replace {csv_file : Path to the CSV file to import}';
    protected $description = 'Delete all products and import fresh data from a CSV file';

    public function handle()
    {
        $csvPath = $this->argument('csv_file');

        if (!file_exists($csvPath)) {
            $this->error("File not found: {$csvPath}");
            return 1;
        }

        Product::truncate();

        $csv = Reader::createFromPath($csvPath);
        $csv->setHeaderOffset(0);

        $imported = 0;
        $errors = 0;

        foreach ($csv->getRecords() as $record) {
            try {
                $rawBarcode = ! empty($record['Barcode']) ? $record['Barcode'] : '0';
                Product::create([
                    'product_name' => $record['Product Name'] ?? 'Unnamed Product',
                    'Barcode' => ProductBarcode::canonical($rawBarcode),
                    'product_image' => $record['Product Image'] ?? null,
                    'halal_status' => (isset($record['Halal Status']) && $record['Halal Status'] !== '') ? $record['Halal Status'] : 2,
                    'Certification_Status' => !empty($record['Certification Status']) ? $record['Certification Status'] : '_',
                    'category' => $record['Category'] ?? null,
                    'notes' => $record['Notes'] ?? null,
                    'ingredient' => $record['Ingredients'] ?? null,
                    'status' => 1,
                ]);
                $imported++;
            } catch (\Exception $e) {
                $errors++;
                $this->warn("Row {$imported}: {$e->getMessage()}");
            }
        }

        Cache::increment('products_cache_version');

        $this->info("Done! Imported: {$imported}, Errors: {$errors}");

        return 0;
    }
}
