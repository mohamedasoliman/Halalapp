<?php

namespace Tests\Feature;

use App\Http\Controllers\PrioritisationController;
use App\Http\Requests\Api\PrioritiseRequest;
use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
use App\Support\ProductBarcode;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrioritisationRequestEnrichmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        Storage::fake('local');

        $this->createTables();
        $this->migration()->up();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_later_deliberate_submission_promotes_and_enriches_a_silent_request(): void
    {
        Product::create([
            'product_name' => 'Authoritative Product',
            'brand' => 'Known Brand',
            'Barcode' => '9400000000016',
            'barcode_key' => ProductBarcode::key('9400000000016'),
            'halal_status' => '2',
        ]);
        DB::table('brands')->insert([
            'name' => 'Known Brand',
            'email' => 'brand@example.com',
            'contact_type' => 'email',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $silent = PrioritisationRequest::create([
            'barcode' => '9400000000016',
            'type' => 'silent',
            'status' => 'pending',
            'source' => 'app',
        ]);

        $response = $this->submit([
            'barcode' => '9400000000016',
            'product_name' => 'Untrusted Submitted Name',
            'brand_name' => 'Untrusted Brand',
            'user_email' => 'Watcher@Example.com',
            'user_name' => 'Amina',
            'type' => 'prioritise',
            'photo' => UploadedFile::fake()->image('front.jpg'),
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['already_requested']);
        $this->assertSame($silent->id, $response->getData(true)['request_id']);
        $this->assertDatabaseCount('prioritisation_requests', 1);
        $this->assertDatabaseHas('prioritisation_requests', [
            'id' => $silent->id,
            'product_name' => 'Authoritative Product',
            'brand_name' => 'Known Brand',
            'type' => 'prioritise',
            'status' => 'ready_for_outreach',
        ]);
        $this->assertDatabaseHas('request_watchers', [
            'request_id' => $silent->id,
            'user_email' => 'watcher@example.com',
            'user_name' => 'Amina',
        ]);

        $photo = DB::table('prioritisation_request_photos')->where('request_id', $silent->id)->first();
        $this->assertNotNull($photo);
        $this->assertSame($photo->path, $silent->fresh()->photo_path);
        Storage::disk('local')->assertExists($photo->path);
    }

    public function test_unknown_deliberate_submission_becomes_discovery_and_stays_pending_even_with_known_brand(): void
    {
        DB::table('brands')->insert([
            'name' => 'Known Brand',
            'email' => 'brand@example.com',
            'contact_type' => 'email',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->submit([
            'barcode' => '1234567890123',
            'product_name' => 'Unknown Product',
            'brand_name' => 'Known Brand',
            'type' => 'prioritise',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('prioritisation_requests', [
            'barcode' => '1234567890123',
            'type' => 'new_product',
            'status' => 'pending',
        ]);
    }

    public function test_later_details_promote_an_unknown_silent_scan_without_creating_a_second_request(): void
    {
        $silent = PrioritisationRequest::create([
            'barcode' => '1234567890123',
            'type' => 'silent',
            'status' => 'pending',
            'source' => 'app',
        ]);

        $response = $this->submit([
            'barcode' => '1234567890123',
            'product_name' => 'User Identified Product',
            'brand_name' => 'User Identified Brand',
            'user_email' => 'discoverer@example.com',
            'type' => 'new_product',
        ]);

        $this->assertSame($silent->id, $response->getData(true)['request_id']);
        $this->assertDatabaseCount('prioritisation_requests', 1);
        $this->assertDatabaseHas('prioritisation_requests', [
            'id' => $silent->id,
            'product_name' => 'User Identified Product',
            'brand_name' => 'User Identified Brand',
            'type' => 'new_product',
            'status' => 'pending',
        ]);
    }

    public function test_each_submission_appends_photos_and_watcher_email_is_case_insensitive(): void
    {
        $first = $this->submit([
            'barcode' => '1234567890123',
            'product_name' => 'Unknown Product',
            'user_email' => 'watcher@example.com',
            'type' => 'new_product',
            'photos' => [
                UploadedFile::fake()->image('front.jpg'),
                UploadedFile::fake()->image('ingredients.png'),
            ],
        ]);
        $requestId = $first->getData(true)['request_id'];

        $second = $this->submit([
            'barcode' => '1234567890123',
            'user_email' => 'WATCHER@EXAMPLE.COM',
            'user_name' => 'Named Later',
            'type' => 'new_product',
            'photo' => UploadedFile::fake()->image('barcode.jpg'),
        ]);

        $this->assertSame($requestId, $second->getData(true)['request_id']);
        $this->assertSame(3, DB::table('prioritisation_request_photos')->where('request_id', $requestId)->count());
        $firstPhotoPath = DB::table('prioritisation_request_photos')
            ->where('request_id', $requestId)
            ->orderBy('id')
            ->value('path');
        $this->assertSame($firstPhotoPath, PrioritisationRequest::find($requestId)->photo_path);
        $this->assertSame(1, DB::table('request_watchers')->where('request_id', $requestId)->count());
        $this->assertSame('Named Later', DB::table('request_watchers')->where('request_id', $requestId)->value('user_name'));
    }

    public function test_enrichment_never_regresses_an_advanced_request_status(): void
    {
        Product::create([
            'product_name' => 'Progressed Product',
            'brand' => 'Progressed Brand',
            'Barcode' => '9400000000054',
            'barcode_key' => ProductBarcode::key('9400000000054'),
            'halal_status' => '2',
        ]);
        $existing = PrioritisationRequest::create([
            'barcode' => '9400000000054',
            'product_name' => 'Old Name',
            'type' => 'prioritise',
            'status' => 'ready_for_review',
            'source' => 'app',
        ]);

        $response = $this->submit([
            'barcode' => '9400000000054',
            'user_email' => 'later@example.com',
            'type' => 'prioritise',
        ]);

        $this->assertSame($existing->id, $response->getData(true)['request_id']);
        $this->assertDatabaseHas('prioritisation_requests', [
            'id' => $existing->id,
            'product_name' => 'Progressed Product',
            'brand_name' => 'Progressed Brand',
            'type' => 'prioritise',
            'status' => 'ready_for_review',
        ]);
    }

    public function test_all_zero_barcode_is_rejected_and_uploaded_photo_is_cleaned_up(): void
    {
        Product::create([
            'product_name' => 'Malformed Product',
            'Barcode' => '00000000',
            'halal_status' => '2',
        ]);

        $response = $this->submit([
            'barcode' => '00000000',
            'product_name' => 'Malformed Product',
            'type' => 'new_product',
            'photo' => UploadedFile::fake()->image('invalid.jpg'),
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertDatabaseCount('prioritisation_requests', 0);
        $this->assertDatabaseCount('prioritisation_request_photos', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('prioritisation_photos'));
    }

    private function submit(array $data)
    {
        $files = [];
        foreach (['photo', 'photos'] as $key) {
            if (array_key_exists($key, $data)) {
                $files[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        $request = PrioritiseRequest::create('/api/prioritise', 'POST', $data, [], $files);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->validateResolved();

        return (new PrioritisationController)->store($request);
    }

    private function createTables(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->string('brand')->nullable();
            $table->string('Barcode', 20);
            $table->string('barcode_key', 20)->nullable();
            $table->string('halal_status')->default('2');
            $table->string('notes')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('email')->nullable();
            $table->string('contact_type')->default('email');
            $table->string('response')->nullable();
            $table->string('response_scope')->nullable();
            $table->timestamps();
        });
        Schema::create('brand_communications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->string('direction');
            $table->json('barcodes_mentioned')->nullable();
            $table->timestamps();
        });
        Schema::create('prioritisation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 20);
            $table->string('barcode_key', 20)->nullable();
            $table->string('product_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('user_name')->nullable();
            $table->string('photo_path', 500)->nullable();
            $table->string('type')->default('prioritise');
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->string('source')->nullable();
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
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_08_13_000002_add_prioritisation_request_photos_and_active_uniqueness.php');
    }
}
