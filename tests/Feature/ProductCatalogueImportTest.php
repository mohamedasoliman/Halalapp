<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductCatalogueImportTest extends TestCase
{
    private string $catalogueFile;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name', 250);
            $table->string('brand', 250)->nullable();
            $table->text('product_image')->nullable();
            $table->string('proof')->nullable();
            $table->boolean('status')->default(true);
            $table->string('halal_status')->default('2');
            $table->string('Barcode', 20)->unique();
            $table->string('Certification_Status', 250)->nullable();
            $table->string('category', 250)->nullable();
            $table->string('country', 250)->nullable();
            $table->string('notes', 250)->nullable();
            $table->text('ingredient')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('products')->insert([
            'product_name' => 'Existing Milk',
            'Barcode' => '9310036040385',
            'halal_status' => '0',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->catalogueFile = sys_get_temp_dir().'/catalogue-import-'.bin2hex(random_bytes(8)).'.ndjson';
        file_put_contents($this->catalogueFile, implode(PHP_EOL, [
            json_encode([
                'barcode' => '9310036040385',
                'product_name' => 'Duplicate Milk',
            ]),
            json_encode([
                'barcode' => '9300462130132',
                'product_name' => 'Raguletto Pasta Sauce',
                'brand' => 'Raguletto',
                'country' => 'Australia',
                'category' => 'Sauces',
                'ingredient' => 'Tomato, garlic',
                'product_image' => 'catalogue-9300462130132.jpg',
                'source' => 'must-not-be-stored',
                'source_url' => 'https://example.com/product',
                'imported_at' => '2026-07-31',
                'halal_status' => '0',
                'type' => 'halal',
            ]),
            json_encode([
                'barcode' => '9400563455629',
                'product_name' => 'Product',
            ]),
        ]).PHP_EOL);
    }

    protected function tearDown(): void
    {
        if (isset($this->catalogueFile) && is_file($this->catalogueFile)) {
            unlink($this->catalogueFile);
        }
        DB::disconnect('sqlite');

        parent::tearDown();
    }

    public function test_preview_is_read_only_and_commit_imports_only_new_usable_products(): void
    {
        $this->artisan('products:import-catalogue', [
            'file' => $this->catalogueFile,
        ])->assertSuccessful();

        $this->assertSame(1, DB::table('products')->count());

        $this->artisan('products:import-catalogue', [
            'file' => $this->catalogueFile,
            '--commit' => true,
        ])->assertSuccessful();

        $this->assertSame(2, DB::table('products')->count());
        $product = DB::table('products')->where('Barcode', '9300462130132')->first();
        $this->assertNotNull($product);
        $this->assertSame('2', $product->halal_status);
        $this->assertSame(1, (int) $product->status);
        $this->assertSame('Raguletto', $product->brand);
        $this->assertSame('Australia', $product->country);
        $this->assertSame('Sauces', $product->category);
        $this->assertSame('Tomato, garlic', $product->ingredient);
        $this->assertSame('catalogue-9300462130132.jpg', $product->product_image);
        $this->assertNull($product->notes);
        $this->assertNull($product->proof);
        $this->assertFalse(property_exists($product, 'source'));
        $this->assertFalse(property_exists($product, 'source_url'));
        $this->assertFalse(property_exists($product, 'imported_at'));

        $this->artisan('products:import-catalogue', [
            'file' => $this->catalogueFile,
            '--commit' => true,
        ])->assertSuccessful();
        $this->assertSame(2, DB::table('products')->count());
    }
}
