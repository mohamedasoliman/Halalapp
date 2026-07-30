<?php

namespace App\Console\Commands;

use App\Support\ProductBarcode;
use App\Support\ProductCatalogueRecord;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FetchProductCatalogue extends Command
{
    protected $signature = 'products:fetch-catalogue
        {barcodes : Text file containing one barcode per line}
        {output : Destination NDJSON file}
        {--images= : Directory for normalized local product images}
        {--limit= : Maximum number of barcodes to request}
        {--batch-size=5 : Concurrent catalogue requests, maximum 10}
        {--delay-ms=500 : Pause between batches}
        {--resume : Append while skipping barcodes already in the output}';

    protected $description = 'Fetch and sanitize product identities for the guarded catalogue importer';

    private const API_ENDPOINT = 'https://admin.mustakshif.com/api/V4/product/search';

    public function handle(): int
    {
        $token = trim((string) getenv('CATALOGUE_API_TOKEN'));
        if ($token === '') {
            $this->error('CATALOGUE_API_TOKEN is required and must be supplied through the process environment.');

            return self::FAILURE;
        }

        $barcodeFile = (string) $this->argument('barcodes');
        $outputFile = (string) $this->argument('output');
        $stateFile = $outputFile.'.state.ndjson';
        if (! is_file($barcodeFile) || ! is_readable($barcodeFile)) {
            $this->error("Barcode file is not readable: {$barcodeFile}");

            return self::FAILURE;
        }

        $resume = (bool) $this->option('resume');
        if (is_file($outputFile) && ! $resume) {
            $this->error('Output already exists. Use --resume or choose a new destination.');

            return self::FAILURE;
        }
        if (is_file($stateFile) && ! $resume) {
            $this->error('Checkpoint state already exists. Use --resume or choose a new destination.');

            return self::FAILURE;
        }

        $outputDirectory = dirname($outputFile);
        if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0755, true) && ! is_dir($outputDirectory)) {
            $this->error("Unable to create output directory: {$outputDirectory}");

            return self::FAILURE;
        }

        $imageDirectory = $this->option('images');
        if (is_string($imageDirectory) && $imageDirectory !== '') {
            if (! is_dir($imageDirectory) && ! mkdir($imageDirectory, 0755, true) && ! is_dir($imageDirectory)) {
                $this->error("Unable to create image directory: {$imageDirectory}");

                return self::FAILURE;
            }
        } else {
            $imageDirectory = null;
        }

        $batchSize = min(max((int) $this->option('batch-size'), 1), 10);
        $delayMilliseconds = min(max((int) $this->option('delay-ms'), 0), 5000);
        $limit = $this->option('limit') === null ? null : max((int) $this->option('limit'), 0);
        $completedKeys = $resume ? $this->completedKeys($outputFile, $stateFile) : [];
        $output = fopen($outputFile, $resume ? 'ab' : 'xb');
        if ($output === false) {
            $this->error("Unable to open output file: {$outputFile}");

            return self::FAILURE;
        }
        $state = fopen($stateFile, $resume ? 'ab' : 'xb');
        if ($state === false) {
            fclose($output);
            $this->error("Unable to open checkpoint state: {$stateFile}");

            return self::FAILURE;
        }

        $requested = 0;
        $written = 0;
        $invalid = 0;
        $unusable = 0;
        $failed = 0;
        $duplicateInput = 0;
        $seen = $completedKeys;
        $batch = [];

        try {
            $input = fopen($barcodeFile, 'rb');
            if ($input === false) {
                throw new RuntimeException("Unable to open barcode file: {$barcodeFile}");
            }

            while (($line = fgets($input)) !== false) {
                $catalogueBarcode = trim($line);
                if (! ProductBarcode::isValidGtin($catalogueBarcode)) {
                    $invalid++;

                    continue;
                }

                $barcode = ProductBarcode::canonical($catalogueBarcode);
                $key = ProductBarcode::key($barcode);
                if ($key === null || isset($seen[$key])) {
                    $duplicateInput++;

                    continue;
                }

                $seen[$key] = true;
                $batch[] = [
                    'catalogue_barcode' => $catalogueBarcode,
                    'canonical_barcode' => $barcode,
                ];
                $requested++;

                if (count($batch) >= $batchSize) {
                    [$added, $badRecords, $requestFailures] = $this->fetchBatch(
                        $batch,
                        $token,
                        $output,
                        $state,
                        $imageDirectory
                    );
                    $written += $added;
                    $unusable += $badRecords;
                    $failed += $requestFailures;
                    $batch = [];

                    if ($requested % 100 === 0) {
                        $this->line("Requested {$requested}; wrote {$written}; unusable {$unusable}; failed {$failed}.");
                    }

                    if ($delayMilliseconds > 0) {
                        usleep($delayMilliseconds * 1000);
                    }
                }

                if ($limit !== null && $requested >= $limit) {
                    break;
                }
            }

            fclose($input);

            if ($batch !== []) {
                [$added, $badRecords, $requestFailures] = $this->fetchBatch(
                    $batch,
                    $token,
                    $output,
                    $state,
                    $imageDirectory
                );
                $written += $added;
                $unusable += $badRecords;
                $failed += $requestFailures;
            }
        } finally {
            fclose($output);
            fclose($state);
        }

        $this->newLine();
        $this->info('Catalogue collection complete.');
        $this->table(
            ['Requested', 'Written', 'Invalid GTIN', 'Duplicate/resumed', 'Unusable', 'Failed'],
            [[$requested, $written, $invalid, $duplicateInput, $unusable, $failed]]
        );

        return self::SUCCESS;
    }

    /**
     * @param  list<array{catalogue_barcode: string, canonical_barcode: string}>  $batch
     * @param  resource  $output
     * @param  resource  $state
     * @return array{int, int, int}
     */
    private function fetchBatch(array $batch, string $token, $output, $state, ?string $imageDirectory): array
    {
        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (array $item, int $index) => $pool->as('item-'.$index)
                ->withToken($token)
                ->acceptJson()
                ->timeout(25)
                ->post(self::API_ENDPOINT, ['barcode' => $item['catalogue_barcode']]),
            $batch,
            array_keys($batch)
        ));

        $records = [];
        $failed = 0;
        $unusable = 0;
        $outcomes = [];

        foreach ($batch as $index => $item) {
            $canonicalBarcode = $item['canonical_barcode'];
            $response = $responses['item-'.$index] ?? null;
            if (! $response instanceof Response) {
                $failed++;
                $outcomes[$canonicalBarcode] = 'failed';

                continue;
            }

            $payload = $response->json();
            $product = is_array($payload) && ($payload['success'] ?? false) === true
                ? ($payload['product'] ?? null)
                : null;
            if (! is_array($product)) {
                $unusable++;
                $outcomes[$canonicalBarcode] = 'unusable';

                continue;
            }

            $record = ProductCatalogueRecord::fromApiProduct($product);
            if ($record === null
                || ProductBarcode::key($record['barcode']) !== ProductBarcode::key($canonicalBarcode)) {
                $unusable++;
                $outcomes[$canonicalBarcode] = 'unusable';

                continue;
            }

            $records[$canonicalBarcode] = $record;
        }

        if ($imageDirectory !== null && $records !== []) {
            $this->downloadImages($records, $imageDirectory);
        }

        $written = 0;
        foreach ($records as $record) {
            unset($record['image_download_url']);
            $encoded = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded === false || fwrite($output, $encoded.PHP_EOL) === false) {
                throw new RuntimeException('Unable to write the catalogue output.');
            }
            $written++;
            $outcomes[$record['barcode']] = 'written';
        }
        fflush($output);
        foreach ($outcomes as $barcode => $outcome) {
            $encoded = json_encode([
                'barcode' => (string) $barcode,
                'outcome' => $outcome,
            ], JSON_UNESCAPED_SLASHES);
            if ($encoded === false || fwrite($state, $encoded.PHP_EOL) === false) {
                throw new RuntimeException('Unable to write the catalogue checkpoint state.');
            }
        }
        fflush($state);

        return [$written, $unusable, $failed];
    }

    /**
     * @param  array<string, array<string, string|null>>  $records
     */
    private function downloadImages(array &$records, string $imageDirectory): void
    {
        $urls = [];
        foreach ($records as $barcode => $record) {
            $url = $record['image_download_url'] ?? null;
            if (is_string($url) && $url !== '') {
                $urls[$barcode] = $url;
            }
        }
        if ($urls === []) {
            return;
        }

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (string $barcode, string $url) => $pool->as($barcode)
                ->timeout(25)
                ->withHeaders(['Accept' => 'image/*'])
                ->get($url),
            array_keys($urls),
            array_values($urls)
        ));

        foreach ($urls as $barcode => $url) {
            $response = $responses[$barcode] ?? null;
            if (! $response instanceof Response || ! $response->successful()) {
                continue;
            }

            $filename = $this->normalizeImage($response, $barcode, $imageDirectory);
            if ($filename !== null) {
                $records[$barcode]['product_image'] = $filename;
            }
        }
    }

    private function normalizeImage(Response $response, string $barcode, string $imageDirectory): ?string
    {
        $body = $response->body();
        if ($body === '' || strlen($body) > 8 * 1024 * 1024) {
            return null;
        }

        $details = @getimagesizefromstring($body);
        $source = @imagecreatefromstring($body);
        if ($details === false || $source === false) {
            return null;
        }

        [$width, $height] = $details;
        if ($width < 20 || $height < 20) {
            imagedestroy($source);

            return null;
        }

        $scale = min(1, 1000 / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($target, 255, 255, 255);
        imagefill($target, 0, 0, $white);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $filename = "catalogue-{$barcode}.jpg";
        $destination = rtrim($imageDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;
        $saved = imagejpeg($target, $destination, 82);
        imagedestroy($source);
        imagedestroy($target);

        return $saved ? $filename : null;
    }

    /**
     * @return array<string, true>
     */
    private function completedKeys(string $outputFile, string $stateFile): array
    {
        $keys = [];
        if (is_file($outputFile)) {
            $input = fopen($outputFile, 'rb');
            if ($input !== false) {
                while (($line = fgets($input)) !== false) {
                    $row = json_decode($line, true);
                    if (! is_array($row)) {
                        continue;
                    }
                    $key = ProductBarcode::key((string) ($row['barcode'] ?? ''));
                    if ($key !== null) {
                        $keys[$key] = true;
                    }
                }
                fclose($input);
            }
        }

        if (is_file($stateFile)) {
            $input = fopen($stateFile, 'rb');
            if ($input !== false) {
                while (($line = fgets($input)) !== false) {
                    $row = json_decode($line, true);
                    if (! is_array($row) || ! in_array($row['outcome'] ?? null, ['written', 'unusable'], true)) {
                        continue;
                    }
                    $key = ProductBarcode::key((string) ($row['barcode'] ?? ''));
                    if ($key !== null) {
                        $keys[$key] = true;
                    }
                }
                fclose($input);
            }
        }

        return $keys;
    }
}
