<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductApiSecurityTest extends TestCase
{
    private const API_KEY = 'test-mobile-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.api_key' => self::API_KEY,
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'mobile_api.require_version' => false,
            'mobile_api.minimum_version' => null,
            'mobile_api.legacy_catalogue_enabled' => true,
        ]);
        DB::purge('sqlite');
        DB::connection('sqlite')->getPdo()->sqliteCreateFunction(
            'SOUNDEX',
            static fn (string $value): string => soundex($value),
        );

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->string('product_image')->nullable();
            $table->string('Barcode', 20);
            $table->string('barcode_key', 20)->nullable();
            $table->string('halal_status')->default('2');
            $table->boolean('status')->default(true);
            $table->string('Certification_Status')->nullable();
            $table->string('category')->default('');
            $table->text('ingredient')->nullable();
            $table->text('notes')->nullable();
            $table->text('proof')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('products')->insert([
            'product_name' => 'Secure Product',
            'product_image' => 'secure-product.jpg',
            'Barcode' => '1234567890123',
            'barcode_key' => '1234567890123',
            'halal_status' => '0',
            'status' => true,
            'Certification_Status' => 'Manufacturer confirmed',
            'category' => 'Snacks',
            'ingredient' => 'Potatoes, salt',
            'notes' => 'Suitable',
            'proof' => '/Users/example/private/Brand_Proofs/secure.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_catalogue_does_not_expose_barcodes_or_internal_fields(): void
    {
        $response = $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/listing', [
                'page' => 1,
                'per_page' => 25,
                'search' => '',
            ])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('alldata.0.product_name', 'Secure Product');

        $product = $response->json('alldata.0');

        $this->assertArrayNotHasKey('Barcode', $product);
        $this->assertArrayNotHasKey('barcode_key', $product);
        $this->assertArrayNotHasKey('proof', $product);
        $this->assertArrayNotHasKey('created_at', $product);
        $this->assertArrayNotHasKey('updated_at', $product);
        $this->assertArrayNotHasKey('deleted_at', $product);
        $this->assertArrayNotHasKey('status', $product);
    }

    public function test_barcode_lookup_requires_an_exact_numeric_barcode(): void
    {
        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/listingcode')
            ->assertUnprocessable();

        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/listingcode', ['search' => '%'])
            ->assertUnprocessable();

        $response = $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/listingcode', ['search' => '1234567890123'])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('alldata.0.product_name', 'Secure Product');

        $this->assertArrayNotHasKey('Barcode', $response->json('alldata.0'));
        $this->assertArrayNotHasKey('proof', $response->json('alldata.0'));
    }

    public function test_catalogue_wildcards_cannot_enumerate_products(): void
    {
        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/listing', ['search' => '%'])
            ->assertUnprocessable();

        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/listing', ['search' => 'a%'])
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_v2_catalogue_requires_a_real_search_term(): void
    {
        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/v2/products/search', ['search' => ''])
            ->assertUnprocessable();

        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/v2/products/search', ['search' => 'S'])
            ->assertUnprocessable();

        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/v2/products/search', ['search' => 'Secure'])
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_legacy_catalogue_can_be_retired_after_forced_upgrade(): void
    {
        config(['mobile_api.legacy_catalogue_enabled' => false]);

        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/listing', ['search' => 'Secure'])
            ->assertStatus(426);

        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/listingcode', ['search' => '1234567890123'])
            ->assertStatus(426);

        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/v2/products/search', ['search' => 'Secure'])
            ->assertOk();
    }

    public function test_mobile_api_key_configuration_fails_closed(): void
    {
        config(['app.api_key' => null]);

        $this->postJson('/api/listing', ['search' => 'chips'])
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Service unavailable.']);
    }

    public function test_removed_directory_mutation_routes_are_not_reachable(): void
    {
        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/addjsondata/3', ['Name' => 'Injected'])
            ->assertNotFound();

        $this->withHeader('X-API-Key', self::API_KEY)
            ->putJson('/api/editjsondata/1', ['Name' => 'Injected'])
            ->assertNotFound();

        $this->withHeader('X-API-Key', self::API_KEY)
            ->deleteJson('/api/deletejsondata/1')
            ->assertNotFound();
    }

    public function test_minimum_app_version_can_be_enabled_after_store_release(): void
    {
        config([
            'mobile_api.require_version' => true,
            'mobile_api.minimum_version' => '10.2.5',
        ]);

        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/listingcode', ['search' => '1234567890123'])
            ->assertStatus(426);

        $this->withHeaders([
            'X-API-Key' => self::API_KEY,
            'X-App-Version' => '10.2.4',
        ])->postJson('/api/listingcode', ['search' => '1234567890123'])
            ->assertStatus(426);

        $this->withHeaders([
            'X-API-Key' => self::API_KEY,
            'X-App-Version' => '10.2.5',
        ])->postJson('/api/listingcode', ['search' => '1234567890123'])
            ->assertOk()
            ->assertHeader('X-Minimum-App-Version', '10.2.5');
    }
}
