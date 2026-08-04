<?php

namespace Tests\Feature;

use App\Http\Controllers\PrioritisationController;
use App\Http\Requests\Api\PrioritiseRequest;
use App\Models\ProductModel\Product;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductHalalStatusDefaultTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->string('Barcode', 20);
            $table->string('barcode_key', 20)->nullable();
            $table->string('halal_status')->nullable();
            $table->boolean('status')->default(true);
            $table->string('category')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prioritisation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 20);
            $table->string('barcode_key', 20)->nullable();
            $table->string('product_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('user_name')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('type')->default('prioritise');
            $table->string('status')->default('pending');
            $table->string('source')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_new_prioritisation_does_not_publish_user_supplied_product_data(): void
    {
        $request = PrioritiseRequest::create('/api/prioritise', 'POST', [
            'barcode' => '1234567890123',
            'product_name' => 'New Product',
        ]);

        (new PrioritisationController)->store($request);

        $this->assertDatabaseMissing('products', [
            'Barcode' => '1234567890123',
        ]);
        $this->assertDatabaseHas('prioritisation_requests', [
            'barcode' => '1234567890123',
            'product_name' => 'New Product',
        ]);
    }

    public function test_prioritisation_failure_is_not_reported_as_success(): void
    {
        Schema::drop('prioritisation_requests');
        $request = PrioritiseRequest::create('/api/prioritise', 'POST', [
            'barcode' => '1234567890123',
            'product_name' => 'New Product',
        ]);

        $response = (new PrioritisationController)->store($request);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('REQUEST_FAILED', $response->getData(true)['code']);
        $this->assertFalse($response->getData(true)['message'] === 'Request submitted.');
    }

    public function test_product_model_defaults_to_unreviewed(): void
    {
        $product = Product::create([
            'product_name' => 'Default Product',
            'Barcode' => '1234567890124',
        ]);

        $this->assertSame('2', (string) $product->fresh()->halal_status);
    }

    public function test_migration_backfills_nulls_and_prevents_new_ones(): void
    {
        DB::table('products')->insert([
            'product_name' => 'Empty Status Product',
            'Barcode' => '1234567890125',
            'halal_status' => null,
        ]);

        $this->migration()->up();

        $this->assertSame('2', DB::table('products')->value('halal_status'));

        DB::table('products')->insert([
            'product_name' => 'Database Default Product',
            'Barcode' => '1234567890126',
        ]);
        $this->assertSame(
            '2',
            DB::table('products')->where('Barcode', '1234567890126')->value('halal_status')
        );

        $this->expectException(QueryException::class);
        DB::table('products')->insert([
            'product_name' => 'Invalid Empty Status Product',
            'Barcode' => '1234567890127',
            'halal_status' => null,
        ]);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_07_20_000002_default_products_to_unreviewed.php');
    }
}
