<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\PrioritisationRequest;
use App\Models\RequestWatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use League\Csv\Reader;

class MigratePrioritisationCsv extends Command
{
    protected $signature = 'requests:migrate-csv
        {prio_csv : Path to Prioritisation_Requests.csv}
        {--brands-csv= : Path to Contacted_Brands.csv}';

    protected $description = 'One-time migration from CSV files to database tables';

    public function handle(): int
    {
        // 1. Import brands if provided
        if ($brandsCsvPath = $this->option('brands-csv')) {
            $this->importBrands($brandsCsvPath);
        }

        // 2. Import prioritisation requests
        $this->importRequests($this->argument('prio_csv'));

        return 0;
    }

    private function importBrands(string $path): void
    {
        if (! file_exists($path)) {
            $this->error("Brands CSV not found: {$path}");

            return;
        }

        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);
        $records = $csv->getRecords();

        $count = 0;
        foreach ($records as $row) {
            $name = trim($row['Brand'] ?? '');
            if (empty($name)) {
                continue;
            }

            $response = match (strtolower(trim($row['Response'] ?? ''))) {
                'halal' => 'halal',
                'not halal' => 'not_halal',
                'partial' => 'partial',
                default => null,
            };

            $email = trim($row['Email'] ?? '');
            $contactType = str_starts_with($email, 'http') ? 'form' : 'email';
            if (str_starts_with($email, 'http')) {
                $email = null;
            }

            Brand::updateOrCreate(
                ['name' => $name],
                [
                    'email' => $email ?: null,
                    'contact_type' => $contactType,
                    'response' => $response,
                    'response_scope' => $response ? ($response === 'partial' ? 'partial' : 'blanket') : null,
                    'notes' => trim($row['Notes'] ?? '') ?: null,
                    'last_contacted_at' => $this->parseDate($row['Date Contacted'] ?? ''),
                ]
            );
            $count++;
        }

        $this->info("Imported {$count} brands.");
    }

    private function importRequests(string $path): void
    {
        if (! file_exists($path)) {
            $this->error("Prioritisation CSV not found: {$path}");

            return;
        }

        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);
        $records = $csv->getRecords();

        $count = 0;
        $watcherCount = 0;
        $seenBarcodes = [];

        foreach ($records as $row) {
            $barcode = trim($row['Barcode'] ?? '');
            if (empty($barcode)) {
                continue;
            }

            $status = $this->mapStatus(trim($row['Status'] ?? ''));
            $userEmail = trim($row['Email'] ?? '');
            $isAnonymous = empty($userEmail) || str_contains(strtolower($userEmail), 'noreply') || strtolower($userEmail) === 'anonymous';

            // If we've already seen this barcode, add as watcher instead
            if (isset($seenBarcodes[$barcode])) {
                if (! $isAnonymous) {
                    RequestWatcher::firstOrCreate(
                        ['request_id' => $seenBarcodes[$barcode], 'user_email' => $userEmail],
                        ['user_name' => trim($row['Requested By'] ?? '') ?: null]
                    );
                    $watcherCount++;
                }

                continue;
            }

            $request = PrioritisationRequest::create([
                'barcode' => $barcode,
                'product_name' => trim($row['Product Name'] ?? '') ?: null,
                'brand_name' => trim($row['Brand Contacted'] ?? '') ?: null,
                'user_email' => $isAnonymous ? null : $userEmail,
                'user_name' => trim($row['Requested By'] ?? '') ?: null,
                'type' => strtolower(trim($row['Source'] ?? '')) === 'barcode' ? 'new_product' : 'prioritise',
                'status' => $status,
                'notes' => trim($row['Notes'] ?? '') ?: null,
                'source' => 'csv_import',
                'created_at' => $this->parseDate($row['Date Received'] ?? '') ?? now(),
            ]);

            $seenBarcodes[$barcode] = $request->id;

            if (! $isAnonymous) {
                RequestWatcher::create([
                    'request_id' => $request->id,
                    'user_email' => $userEmail,
                    'user_name' => trim($row['Requested By'] ?? '') ?: null,
                ]);
                $watcherCount++;
            }

            $count++;
        }

        $this->info("Imported {$count} requests and {$watcherCount} watchers.");
    }

    private function mapStatus(string $csvStatus): string
    {
        return match (strtolower($csvStatus)) {
            'pending' => 'pending',
            'contacted' => 'contacted',
            'resolved' => 'resolved',
            'partial' => 'contacted',
            'not found', 'no brand found', 'no contact' => 'pending',
            'awaiting info', 'research needed' => 'pending',
            default => 'pending',
        };
    }

    private function parseDate(string $date): ?Carbon
    {
        $date = trim($date);
        if (empty($date)) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Exception) {
            return null;
        }
    }
}
