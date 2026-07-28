<?php

namespace Tests\Feature;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\ProductSearchRequest;
use App\Services\ProductResolutionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class ProductMashboohApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
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
            $table->string('halal_status')->default('2');
            $table->boolean('status')->default(true);
            $table->string('Certification_Status')->nullable();
            $table->string('category')->default('');
            $table->string('ingredient')->default('');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('products')->insert([
            [
                'product_name' => 'Mashbooh Product',
                'Barcode' => '1234567890123',
                'halal_status' => '3',
                'status' => true,
                'category' => '',
            ],
            [
                'product_name' => 'Unreviewed Product',
                'Barcode' => '1234567890124',
                'halal_status' => '2',
                'status' => true,
                'category' => '',
            ],
            [
                'product_name' => 'Pams Salt and Vinegar Chips',
                'Barcode' => '1234567890125',
                'halal_status' => '0',
                'status' => true,
                'category' => 'Chips',
            ],
            [
                'product_name' => 'Woolworths Salt and Vinegar Chips',
                'Barcode' => '1234567890126',
                'halal_status' => '0',
                'status' => true,
                'category' => 'Chips',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_listing_can_filter_mashbooh_products(): void
    {
        $request = ProductSearchRequest::create('/api/listing', 'POST', [
            'halal_status' => '3',
            'per_page' => 50,
        ]);

        $response = (new ApiController)->allListing($request);
        $data = $response->getData(true);

        $this->assertSame('success', $data['status']);
        $this->assertSame(1, $data['total']);
        $this->assertSame('Mashbooh Product', $data['alldata'][0]['fruit_name']);
        $this->assertSame('3', (string) $data['alldata'][0]['halal_status']);
    }

    public function test_search_request_accepts_only_supported_statuses(): void
    {
        $request = new ProductSearchRequest;

        $valid = validator(['halal_status' => '3'], $request->rules());
        $invalid = validator(['halal_status' => '4'], $request->rules());

        $this->assertFalse($valid->fails());
        $this->assertTrue($invalid->fails());
    }

    public function test_assistant_product_search_never_crosses_house_brands(): void
    {
        $pakNSave = ProductSearchRequest::create('/api/listing', 'POST', [
            'search' => 'chips',
            'flavour' => 'salt and vinegar',
            'retailer' => 'pak_n_save',
            'halal_status' => '0',
        ]);
        $woolworths = ProductSearchRequest::create('/api/listing', 'POST', [
            'search' => 'chips',
            'flavour' => 'salt and vinegar',
            'retailer' => 'woolworths',
            'halal_status' => '0',
        ]);

        $pakNSaveData = (new ApiController)->allListing($pakNSave)->getData(true);
        $woolworthsData = (new ApiController)->allListing($woolworths)->getData(true);

        $this->assertSame('success', $pakNSaveData['status'], json_encode($pakNSaveData));
        $this->assertSame('success', $woolworthsData['status'], json_encode($woolworthsData));
        $this->assertSame(['Pams Salt and Vinegar Chips'], array_column($pakNSaveData['alldata'], 'fruit_name'));
        $this->assertSame(['pak_n_save'], $pakNSaveData['alldata'][0]['retailers']);
        $this->assertSame(['Woolworths Salt and Vinegar Chips'], array_column($woolworthsData['alldata'], 'fruit_name'));
        $this->assertSame(['woolworths'], $woolworthsData['alldata'][0]['retailers']);
    }

    public function test_mashbooh_cannot_be_used_as_a_final_resolution(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Status must be 0 (halal) or 1 (not halal).');

        app(ProductResolutionService::class)->resolve('1234567890123', '3');
    }
}
