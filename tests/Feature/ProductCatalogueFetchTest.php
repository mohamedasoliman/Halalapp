<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductCatalogueFetchTest extends TestCase
{
    private string $barcodeFile;

    private string $outputFile;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = bin2hex(random_bytes(8));
        $this->barcodeFile = sys_get_temp_dir()."/catalogue-barcodes-{$suffix}.txt";
        $this->outputFile = sys_get_temp_dir()."/catalogue-output-{$suffix}.ndjson";
        file_put_contents($this->barcodeFile, "9310036040385\n");
        putenv('CATALOGUE_API_TOKEN=test-token');
    }

    protected function tearDown(): void
    {
        foreach ([$this->barcodeFile, $this->outputFile, $this->outputFile.'.state.ndjson'] as $file) {
            if (isset($file) && is_file($file)) {
                unlink($file);
            }
        }
        putenv('CATALOGUE_API_TOKEN');

        parent::tearDown();
    }

    public function test_fetch_writes_only_sanitized_identity_fields(): void
    {
        Http::fake([
            'admin.mustakshif.com/*' => Http::response([
                'success' => true,
                'message' => 'Product founded',
                'type' => 'locked',
                'product' => [
                    'barcode' => '9310036040385',
                    'name' => 'Milk',
                    'brand' => 'Mooloo Mountain',
                    'origin' => 'Australia',
                    'main_category' => 'Dairy',
                    'ingredients' => 'Cows milk',
                    'main_image' => null,
                    'type' => 'halal',
                    'locked_type' => 'halal',
                    'change_reason' => 'External verdict',
                    'created_at' => '2023-01-01T00:00:00Z',
                ],
            ], 404),
        ]);

        $this->artisan('products:fetch-catalogue', [
            'barcodes' => $this->barcodeFile,
            'output' => $this->outputFile,
            '--limit' => 1,
            '--delay-ms' => 0,
        ])->assertSuccessful();

        $record = json_decode(trim((string) file_get_contents($this->outputFile)), true);
        $this->assertSame('9310036040385', $record['barcode']);
        $this->assertSame('Mooloo Mountain Milk', $record['product_name']);
        $this->assertSame('Mooloo Mountain', $record['brand']);
        $this->assertSame('Australia', $record['country']);
        $this->assertSame('Dairy', $record['category']);
        $this->assertSame('Cows milk', $record['ingredient']);
        $this->assertArrayNotHasKey('type', $record);
        $this->assertArrayNotHasKey('locked_type', $record);
        $this->assertArrayNotHasKey('change_reason', $record);
        $this->assertArrayNotHasKey('created_at', $record);
        $this->assertArrayNotHasKey('source', $record);
        $this->assertArrayNotHasKey('source_url', $record);

        $state = json_decode(trim((string) file_get_contents($this->outputFile.'.state.ndjson')), true);
        $this->assertSame([
            'barcode' => '9310036040385',
            'outcome' => 'written',
        ], $state);

        $this->artisan('products:fetch-catalogue', [
            'barcodes' => $this->barcodeFile,
            'output' => $this->outputFile,
            '--resume' => true,
            '--delay-ms' => 0,
        ])->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertCount(1, file($this->outputFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    }
}
