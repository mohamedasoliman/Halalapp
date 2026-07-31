<?php

namespace App\Console\Commands;

use App\Support\ProductBarcode;
use App\Support\ProductCatalogueRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportProductCatalogue extends Command
{
    protected $signature = 'products:import-catalogue
        {file : Sanitized NDJSON catalogue file}
        {--limit= : Maximum number of accepted new records}
        {--chunk=500 : Number of rows per transaction}
        {--images= : Directory containing normalized product images to validate}
        {--commit : Insert records; without this option the command is preview-only}';

    protected $description = 'Preview or import sanitized product identities as active unreviewed products';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file) || ! is_readable($file)) {
            $this->error("Catalogue file is not readable: {$file}");

            return self::FAILURE;
        }

        foreach (['brand', 'country'] as $column) {
            if (! Schema::hasColumn('products', $column)) {
                $this->error("The products.{$column} column is missing. Run migrations first.");

                return self::FAILURE;
            }
        }

        $commit = (bool) $this->option('commit');
        $limit = $this->option('limit') === null ? null : max((int) $this->option('limit'), 0);
        $chunkSize = min(max((int) $this->option('chunk'), 1), 2000);
        $imageDirectory = $this->option('images');
        if (is_string($imageDirectory) && $imageDirectory !== '') {
            if (! is_dir($imageDirectory) || ! is_readable($imageDirectory)) {
                $this->error("Image directory is not readable: {$imageDirectory}");

                return self::FAILURE;
            }
            $imageDirectory = rtrim($imageDirectory, DIRECTORY_SEPARATOR);
        } else {
            $imageDirectory = null;
        }
        $existingKeys = $this->existingBarcodeKeys();
        $inputKeys = [];
        $rows = [];
        $accepted = 0;
        $inserted = 0;
        $duplicates = 0;
        $invalid = 0;
        $invalidImages = 0;

        $input = fopen($file, 'rb');
        if ($input === false) {
            $this->error("Unable to open catalogue file: {$file}");

            return self::FAILURE;
        }

        while (($line = fgets($input)) !== false) {
            $decoded = json_decode($line, true);
            $record = is_array($decoded)
                ? ProductCatalogueRecord::fromImportRow($decoded)
                : null;
            if ($record === null) {
                $invalid++;

                continue;
            }
            if ($record['product_image'] !== null && $imageDirectory !== null) {
                $imagePath = $imageDirectory.DIRECTORY_SEPARATOR.$record['product_image'];
                if (! ProductCatalogueRecord::isUsableImageFile($imagePath)) {
                    $record['product_image'] = null;
                    $invalidImages++;
                }
            }

            $key = ProductBarcode::key($record['barcode']);
            if ($key === null || isset($existingKeys[$key]) || isset($inputKeys[$key])) {
                $duplicates++;

                continue;
            }

            $inputKeys[$key] = true;
            $accepted++;
            $rows[] = [
                'product_name' => $record['product_name'],
                'brand' => $record['brand'],
                'product_image' => $record['product_image'],
                'proof' => null,
                'status' => 1,
                'halal_status' => '2',
                'Barcode' => $record['barcode'],
                'Certification_Status' => '_',
                'category' => $record['category'],
                'country' => $record['country'],
                'notes' => null,
                'ingredient' => $record['ingredient'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($rows) >= $chunkSize) {
                $inserted += $this->insertRows($rows, $commit);
                $rows = [];
            }

            if ($limit !== null && $accepted >= $limit) {
                break;
            }
        }
        fclose($input);

        if ($rows !== []) {
            $inserted += $this->insertRows($rows, $commit);
        }

        if ($commit && $inserted > 0) {
            Cache::increment('products_cache_version');
        }

        $this->info($commit ? 'Catalogue import complete.' : 'Catalogue preview complete; no database changes were made.');
        $this->table(
            [
                'Accepted',
                $commit ? 'Inserted' : 'Would insert',
                'Duplicates',
                'Invalid/unusable',
                'Rejected images',
                'Halal status',
            ],
            [[$accepted, $commit ? $inserted : $accepted, $duplicates, $invalid, $invalidImages, '2 (Unreviewed)']]
        );

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertRows(array $rows, bool $commit): int
    {
        if (! $commit) {
            return 0;
        }

        return DB::transaction(fn () => DB::table('products')->insertOrIgnore($rows));
    }

    /**
     * Include soft-deleted rows so an old identity is never silently recreated.
     *
     * @return array<string, true>
     */
    private function existingBarcodeKeys(): array
    {
        $keys = [];
        DB::table('products')
            ->orderBy('id')
            ->select(['id', 'Barcode'])
            ->chunkById(2000, function ($products) use (&$keys) {
                foreach ($products as $product) {
                    $key = ProductBarcode::key((string) $product->Barcode);
                    if ($key !== null) {
                        $keys[$key] = true;
                    }
                }
            }, 'id');

        return $keys;
    }
}
