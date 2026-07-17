<?php

namespace Tests\Feature;

use App\Jobs\SendBrandOutreachBatch;
use App\Mail\BrandOutreachEmail;
use App\Models\Brand;
use App\Models\BrandOutreachBatch;
use App\Models\PrioritisationRequest;
use App\Services\BrandOutreachService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class BrandOutreachServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'outreach.enabled' => false,
            'outreach.daily_limit' => 20,
            'outreach.spacing_minutes' => 3,
            'outreach.products_per_email' => 10,
        ]);
        DB::purge('sqlite');

        $this->createTables();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_prepare_creates_research_records_and_groups_only_valid_matching_barcodes(): void
    {
        Brand::create([
            'name' => 'Ready Brand',
            'email' => 'quality@example.com',
            'contact_type' => 'email',
            'contact_research_status' => 'verified',
        ]);

        $first = $this->createRequest('Ready Brand', '9400000000001', 'First product');
        $duplicate = $this->createRequest('Ready Brand', '9400000000001', 'First product duplicate');
        $invalidOne = $this->createRequest('Ready Brand', '0', 'Unknown product one');
        $invalidTwo = $this->createRequest('Ready Brand', '0', 'Unknown product two');
        $this->createRequest('Needs Research', '9400000000002', 'Missing-contact product');

        $result = app(BrandOutreachService::class)->prepareInitialOutreach();

        $this->assertSame(1, $result['createdBrands']);
        $this->assertSame(4, $result['readyRequests']);
        $this->assertSame(1, $result['draftsCreated']);
        $this->assertSame(1, $result['missingContacts']);

        $batch = BrandOutreachBatch::sole();
        $this->assertCount(3, $batch->products);
        $this->assertEqualsCanonicalizing(
            [$first->id, $duplicate->id, $invalidOne->id, $invalidTwo->id],
            $batch->request_ids,
        );
        $this->assertSame('pending', Brand::where('name', 'Needs Research')->value('contact_research_status'));
        $this->assertDatabaseMissing('brand_outreach_batches', ['recipient_email' => null]);
    }

    public function test_prepare_groups_brand_name_variants_into_one_draft(): void
    {
        $brand = Brand::create([
            'name' => "Ingham's",
            'email' => 'quality@example.com',
            'contact_type' => 'email',
            'contact_research_status' => 'verified',
        ]);

        $first = $this->createRequest("Ingham's", '9400000000010', 'First product');
        $second = $this->createRequest('  INGHAM\'S  ', '9400000000011', 'Second product');

        $result = app(BrandOutreachService::class)->prepareInitialOutreach();

        $this->assertSame(0, $result['createdBrands']);
        $this->assertSame(2, $result['readyRequests']);
        $this->assertSame(1, $result['draftsCreated']);
        $this->assertSame(0, $result['missingContacts']);

        $batch = BrandOutreachBatch::sole();
        $this->assertSame($brand->id, $batch->brand_id);
        $this->assertCount(2, $batch->products);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $batch->request_ids);
    }

    public function test_prepare_matches_accented_brand_name_variants(): void
    {
        $brand = Brand::create([
            'name' => 'Nestle',
            'email' => 'quality@example.com',
            'contact_type' => 'email',
            'contact_research_status' => 'verified',
        ]);

        $request = $this->createRequest('Nestlé', '9400000000012', 'Accented product');

        $result = app(BrandOutreachService::class)->prepareInitialOutreach();

        $this->assertSame(0, $result['createdBrands']);
        $this->assertSame(1, $result['readyRequests']);
        $this->assertSame(1, $result['draftsCreated']);
        $this->assertSame($brand->id, BrandOutreachBatch::sole()->brand_id);
        $this->assertSame([$request->id], BrandOutreachBatch::sole()->request_ids);
    }

    public function test_queueing_requires_explicit_enablement_and_obeys_daily_limit(): void
    {
        Queue::fake();
        $brand = Brand::create([
            'name' => 'Queue Brand',
            'email' => 'quality@example.com',
            'contact_type' => 'email',
            'contact_research_status' => 'verified',
        ]);
        $batches = collect([
            $this->createBatch($brand, 'HK-TEST-1'),
            $this->createBatch($brand, 'HK-TEST-2'),
            $this->createBatch($brand, 'HK-TEST-3'),
        ]);

        $this->expectException(LogicException::class);
        app(BrandOutreachService::class)->queueDrafts($batches);
    }

    public function test_enabled_queueing_uses_throttled_jobs(): void
    {
        Queue::fake();
        config(['outreach.enabled' => true, 'outreach.daily_limit' => 2]);
        $brand = Brand::create([
            'name' => 'Queue Brand',
            'email' => 'quality@example.com',
            'contact_type' => 'email',
            'contact_research_status' => 'verified',
        ]);
        $batches = collect([
            $this->createBatch($brand, 'HK-TEST-1'),
            $this->createBatch($brand, 'HK-TEST-2'),
            $this->createBatch($brand, 'HK-TEST-3'),
        ]);

        $queued = app(BrandOutreachService::class)->queueDrafts($batches);

        $this->assertCount(2, $queued);
        $this->assertSame(2, BrandOutreachBatch::where('status', 'queued')->count());
        $this->assertSame(1, BrandOutreachBatch::where('status', 'draft')->count());
        Queue::assertPushed(SendBrandOutreachBatch::class, 2);
    }

    public function test_successful_job_records_delivery_before_marking_requests_contacted(): void
    {
        Mail::fake();
        config(['outreach.enabled' => true]);
        $brand = Brand::create([
            'name' => 'Sent Brand',
            'email' => 'quality@example.com',
            'contact_type' => 'email',
            'contact_research_status' => 'verified',
        ]);
        $request = $this->createRequest('Sent Brand', '9400000000003', 'Sent product', 'ready_for_outreach');
        $batch = $this->createBatch($brand, 'HK-SENT-1', 'queued', [$request->id]);

        (new SendBrandOutreachBatch($batch->id))->handle(app(BrandOutreachService::class));

        Mail::assertSent(BrandOutreachEmail::class, fn (BrandOutreachEmail $mail) => $mail->reference === 'HK-SENT-1');
        $this->assertSame('sent', $batch->fresh()->status);
        $this->assertSame('contacted', $request->fresh()->status);
        $this->assertNotNull($brand->fresh()->last_contacted_at);
        $this->assertDatabaseHas('brand_communications', [
            'brand_id' => $brand->id,
            'direction' => 'outbound',
            'subject' => $batch->subject,
        ]);
    }

    private function createRequest(
        string $brand,
        string $barcode,
        string $product,
        string $status = 'pending',
    ): PrioritisationRequest {
        return PrioritisationRequest::create([
            'brand_name' => $brand,
            'barcode' => $barcode,
            'product_name' => $product,
            'status' => $status,
            'type' => 'prioritise',
        ]);
    }

    private function createBatch(
        Brand $brand,
        string $reference,
        string $status = 'draft',
        array $requestIds = [],
    ): BrandOutreachBatch {
        return BrandOutreachBatch::create([
            'reference' => $reference,
            'brand_id' => $brand->id,
            'status' => $status,
            'recipient_email' => $brand->email,
            'subject' => "Halal Suitability Inquiry [{$reference}]",
            'products' => [['name' => 'Test product', 'barcode' => '9400000000001']],
            'request_ids' => $requestIds,
        ]);
    }

    private function createTables(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('email')->nullable();
            $table->string('contact_type')->default('email');
            $table->string('contact_research_status')->default('pending');
            $table->string('contact_source')->nullable();
            $table->timestamp('contact_verified_at')->nullable();
            $table->string('response')->nullable();
            $table->string('response_scope')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->unsignedTinyInteger('follow_up_count')->default(0);
            $table->timestamp('outreach_paused_at')->nullable();
            $table->timestamps();
        });
        Schema::create('prioritisation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 20);
            $table->string('product_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('user_name')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('type')->default('prioritise');
            $table->string('status')->default('pending');
            $table->tinyInteger('resolved_status')->nullable();
            $table->text('notes')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();
        });
        Schema::create('brand_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id');
            $table->string('direction');
            $table->string('subject')->nullable();
            $table->text('body_preview')->nullable();
            $table->json('barcodes_mentioned')->nullable();
            $table->text('action_taken')->nullable();
            $table->timestamps();
        });
        Schema::create('request_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id');
            $table->string('user_email');
            $table->string('user_name')->nullable();
            $table->timestamps();
        });
        Schema::create('brand_outreach_batches', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('brand_id');
            $table->string('kind')->default('initial');
            $table->unsignedTinyInteger('follow_up_number')->default(0);
            $table->string('status')->default('draft');
            $table->string('recipient_email');
            $table->string('subject');
            $table->json('products');
            $table->json('request_ids');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }
}
