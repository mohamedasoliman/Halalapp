<?php

namespace Tests\Feature;

use App\Models\ProductModel\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductBarcodeLookupScopeTest extends TestCase
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
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique('barcode_key', 'products_barcode_key_unique');
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_valid_leading_zero_variants_use_the_normalized_barcode(): void
    {
        DB::table('products')->insert([
            'id' => 10,
            'product_name' => 'Chilli Oil',
            'Barcode' => '078895743050',
            'barcode_key' => '78895743050',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            10,
            Product::matchingBarcode('078895743050')->value('id'),
        );
        $this->assertSame(
            10,
            Product::matchingBarcode('0078895743050')->value('id'),
        );
        $this->assertSame(
            10,
            Product::matchingBarcode('78895743050')->value('id'),
        );
    }

    public function test_valid_lookup_uses_only_the_unique_normalized_index_path(): void
    {
        $query = Product::matchingBarcode('0078895743050')
            ->where('status', true);

        $sql = $query->toSql();
        $this->assertStringContainsString('"barcode_key" = ?', $sql);
        $this->assertStringNotContainsString('"Barcode" = ?', $sql);
        $this->assertStringNotContainsString(' or ', strtolower($sql));

        $plan = DB::select(
            'EXPLAIN QUERY PLAN '.$sql,
            $query->getBindings(),
        );
        $planDetails = implode(' ', array_map(
            static fn (object $row): string => (string) $row->detail,
            $plan,
        ));

        $this->assertStringContainsString(
            'products_barcode_key_unique',
            $planDetails,
        );
        $this->assertStringNotContainsString('SCAN products', $planDetails);
    }

    public function test_invalid_legacy_barcode_falls_back_to_exact_value(): void
    {
        DB::table('products')->insert([
            'id' => 20,
            'product_name' => 'Legacy Placeholder',
            'Barcode' => '12345678',
            'barcode_key' => '12345678',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $query = Product::matchingBarcode('12345678');

        $this->assertSame(20, $query->value('id'));
        $this->assertStringContainsString('"Barcode" = ?', $query->toSql());
        $this->assertStringNotContainsString('"barcode_key" = ?', $query->toSql());
    }
}
