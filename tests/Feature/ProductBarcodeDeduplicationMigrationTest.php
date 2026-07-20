<?php

namespace Tests\Feature;

use App\Models\ProductModel\Product;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductBarcodeDeduplicationMigrationTest extends TestCase
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
            $table->text('product_image')->nullable();
            $table->string('proof')->nullable();
            $table->string('Barcode', 20);
            $table->string('halal_status')->default('2');
            $table->string('Certification_Status')->nullable();
            $table->string('category')->nullable();
            $table->string('notes', 250)->nullable();
            $table->text('ingredient')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('prioritisation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 20);
            $table->string('product_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('user_name')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('type')->default('silent');
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('request_watchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->string('user_email');
            $table->string('user_name')->nullable();
            $table->timestamps();
            $table->unique(['request_id', 'user_email']);
        });
        Schema::create('brand_communications', function (Blueprint $table) {
            $table->id();
            $table->json('barcodes_mentioned')->nullable();
        });
        Schema::create('brand_outreach_batches', function (Blueprint $table) {
            $table->id();
            $table->json('request_ids');
        });
        Schema::create('request_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 20);
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_it_merges_products_requests_and_watchers_using_a_canonical_barcode(): void
    {
        $now = now();
        foreach ([
            [
                'id' => 10,
                'product_name' => 'Chiu Chow Chilli Oil',
                'product_image' => 'good.jpg',
                'Barcode' => '78895743050',
                'halal_status' => '2',
                'category' => 'Sauces',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 11,
                'product_name' => 'Lee Kum Kee Chiu Chow Style Chilli Oil 205g',
                'Barcode' => '0078895743050',
                'halal_status' => '2',
                'notes' => 'Identity researched.',
                'ingredient' => 'Chilli and oil',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 12,
                'product_name' => 'Placeholder A',
                'Barcode' => '0',
                'halal_status' => '2',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 13,
                'product_name' => 'Placeholder B',
                'Barcode' => '0',
                'halal_status' => '2',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ] as $product) {
            DB::table('products')->insert($product);
        }
        foreach ([
            [
                'id' => 20,
                'barcode' => '78895743050',
                'product_name' => 'Chilli Oil',
                'type' => 'silent',
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 21,
                'barcode' => '0078895743050',
                'product_name' => 'Lee Kum Kee Chilli Oil',
                'brand_name' => 'Lee Kum Kee',
                'user_email' => 'requester@example.com',
                'type' => 'prioritise',
                'status' => 'ready_for_outreach',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ] as $request) {
            DB::table('prioritisation_requests')->insert($request);
        }
        DB::table('request_watchers')->insert([
            'request_id' => 20,
            'user_email' => 'watcher@example.com',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->migration()->up();

        $survivor = DB::table('products')->where('id', 10)->first();
        $this->assertSame('078895743050', $survivor->Barcode);
        $this->assertSame('78895743050', $survivor->barcode_key);
        $this->assertSame('Chilli and oil', $survivor->ingredient);
        $this->assertNotNull(DB::table('products')->where('id', 11)->value('deleted_at'));
        $this->assertSame(3, DB::table('products')->whereNull('deleted_at')->count());
        $this->assertSame(10, Product::matchingBarcode('0078895743050')->value('id'));
        $this->assertSame(10, Product::matchingBarcode('78895743050')->value('id'));

        $activeRequest = DB::table('prioritisation_requests')
            ->whereIn('status', ['pending', 'ready_for_outreach', 'contacted', 'ready_for_review'])
            ->first();
        $this->assertSame(21, $activeRequest->id);
        $this->assertSame('078895743050', $activeRequest->barcode);
        $this->assertSame('dead_end', DB::table('prioritisation_requests')->where('id', 20)->value('status'));
        $this->assertDatabaseHas('request_watchers', [
            'request_id' => 21,
            'user_email' => 'watcher@example.com',
        ]);

        $this->expectException(QueryException::class);
        DB::table('products')->insert([
            'product_name' => 'Duplicate Again',
            'Barcode' => '0078895743050',
            'halal_status' => '2',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function test_it_refuses_to_merge_conflicting_halal_verdicts(): void
    {
        $now = now();
        DB::table('products')->insert([
            'product_name' => 'Halal Product',
            'Barcode' => '78895743050',
            'halal_status' => '0',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('products')->insert([
            'product_name' => 'Not Halal Product',
            'Barcode' => '0078895743050',
            'halal_status' => '1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('conflicting halal statuses');

        $this->migration()->up();
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_07_20_000003_merge_leading_zero_product_duplicates.php');
    }
}
