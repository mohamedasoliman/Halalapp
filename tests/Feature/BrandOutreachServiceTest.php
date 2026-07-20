<?php

namespace Tests\Feature;

use App\Jobs\SendBrandOutreachBatch;
use App\Mail\BrandOutreachEmail;
use App\Mail\UserNotificationEmail;
use App\Models\Brand;
use App\Models\BrandOutreachBatch;
use App\Models\PrioritisationRequest;
use App\Models\RequestWatcher;
use App\Services\BrandOutreachService;
use App\Services\RequestNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Mockery;
use RuntimeException;
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
            'outreach.mailer' => 'outreach',
            'outreach.daily_limit' => 20,
            'outreach.spacing_minutes' => 3,
            'outreach.products_per_email' => 10,
            'mail.mailers.outreach' => ['transport' => 'array'],
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

    public function test_enabled_queueing_rejects_a_mismatched_smtp_identity(): void
    {
        config([
            'outreach.enabled' => true,
            'outreach.from_address' => 'products@halalkiwi.com',
            'mail.mailers.outreach' => [
                'transport' => 'smtp',
                'username' => 'info@halalapp.info',
                'password' => 'configured',
            ],
        ]);
        $brand = Brand::create([
            'name' => 'Identity Brand',
            'email' => 'quality@example.com',
            'contact_type' => 'email',
            'contact_research_status' => 'verified',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must authenticate as');

        app(BrandOutreachService::class)->queueDrafts(collect([
            $this->createBatch($brand, 'HK-IDENTITY-1'),
        ]));
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
        $request->update(['user_email' => 'Customer@Example.com']);
        RequestWatcher::create(['request_id' => $request->id, 'user_email' => 'customer@example.com']);
        RequestWatcher::create(['request_id' => $request->id, 'user_email' => 'anonymous@halalkiwi.com']);
        $batch = $this->createBatch($brand, 'HK-SENT-1', 'queued', [$request->id]);

        (new SendBrandOutreachBatch($batch->id))->handle(app(BrandOutreachService::class));

        Mail::assertSent(BrandOutreachEmail::class, fn (BrandOutreachEmail $mail) => $mail->reference === 'HK-SENT-1');
        Mail::assertSent(UserNotificationEmail::class, 1);
        Mail::assertSent(UserNotificationEmail::class, fn (UserNotificationEmail $mail) => $mail->hasTo('customer@example.com'));
        $this->assertSame('sent', $batch->fresh()->status);
        $this->assertSame('contacted', $request->fresh()->status);
        $this->assertNotNull($brand->fresh()->last_contacted_at);
        $this->assertDatabaseHas('brand_communications', [
            'brand_id' => $brand->id,
            'direction' => 'outbound',
            'subject' => $batch->subject,
        ]);
    }

    public function test_requester_notification_preparation_failure_does_not_downgrade_sent_batch(): void
    {
        Mail::fake();
        config(['outreach.enabled' => true]);
        $brand = Brand::create([
            'name' => 'Post-send Brand',
            'email' => 'quality@example.com',
            'contact_type' => 'email',
            'contact_research_status' => 'verified',
        ]);
        $request = $this->createRequest('Post-send Brand', '9400000000014', 'Post-send product', 'ready_for_outreach');
        $request->update(['user_email' => 'customer@example.com']);
        $batch = $this->createBatch($brand, 'HK-POST-SEND-1', 'queued', [$request->id]);
        $notifications = Mockery::mock(RequestNotificationService::class);
        $notifications->shouldReceive('prepareEvent')->once()->andThrow(new RuntimeException('Delivery ledger unavailable.'));
        $service = new BrandOutreachService($notifications);

        (new SendBrandOutreachBatch($batch->id))->handle($service);

        Mail::assertSent(BrandOutreachEmail::class, 1);
        $this->assertSame('sent', $batch->fresh()->status);
        $this->assertStringContainsString('requester notification processing requires review', $batch->fresh()->error);
        $this->assertSame('contacted', $request->fresh()->status);
        $this->assertDatabaseHas('brand_communications', [
            'brand_id' => $brand->id,
            'direction' => 'outbound',
            'subject' => $batch->subject,
        ]);
    }

    public function test_failure_after_send_attempt_marks_manufacturer_batch_uncertain(): void
    {
        $brand = Brand::create([
            'name' => 'Uncertain Brand',
            'email' => 'quality@example.com',
            'contact_type' => 'email',
            'contact_research_status' => 'verified',
        ]);
        $batch = $this->createBatch($brand, 'HK-UNCERTAIN-1', 'sending');

        (new SendBrandOutreachBatch($batch->id))->failed(new RuntimeException('SMTP connection ended unexpectedly.'));

        $this->assertSame('uncertain', $batch->fresh()->status);
        $this->assertStringContainsString('do not retry without reconciliation', $batch->fresh()->error);
        $this->assertNull($batch->fresh()->failed_at);
    }

    public function test_job_failure_callback_never_downgrades_a_sent_manufacturer_batch(): void
    {
        $brand = Brand::create([
            'name' => 'Already Sent Brand',
            'email' => 'quality@example.com',
            'contact_type' => 'email',
            'contact_research_status' => 'verified',
        ]);
        $batch = $this->createBatch($brand, 'HK-ALREADY-SENT-1', 'sent');

        (new SendBrandOutreachBatch($batch->id))->failed(new RuntimeException('Post-send notification failure.'));

        $this->assertSame('sent', $batch->fresh()->status);
        $this->assertStringContainsString('Post-send processing requires review', $batch->fresh()->error);
    }

    public function test_outreach_email_requests_meat_and_regional_manufacturer_details(): void
    {
        $email = new BrandOutreachEmail(
            'Test Brand',
            [['name' => 'Test product', 'barcode' => '9400000000013']],
            'HK-TEST-CONTENT',
        );

        $body = $email->render();

        $this->assertStringContainsString('halal slaughter method', $body);
        $this->assertStringContainsString('another regional team, licensee, or manufacturer', $body);
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
            $table->unsignedBigInteger('resolution_communication_id')->nullable();
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
            $table->string('email_message_id')->nullable();
            $table->text('proof_path')->nullable();
            $table->string('processing_status')->nullable();
            $table->timestamp('processed_at')->nullable();
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
        $this->createNotificationTable();
    }

    private function createNotificationTable(): void
    {
        Schema::create('request_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->char('event_key', 64);
            $table->string('event_reference');
            $table->json('request_ids')->nullable();
            $table->unsignedBigInteger('brand_communication_id')->nullable();
            $table->string('notification_type');
            $table->string('recipient_email');
            $table->string('normalized_email');
            $table->char('recipient_hash', 64);
            $table->string('product_name');
            $table->string('barcode');
            $table->tinyInteger('halal_status')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('uncertain_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['event_key', 'recipient_hash']);
        });
    }
}
