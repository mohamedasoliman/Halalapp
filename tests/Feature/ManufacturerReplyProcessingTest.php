<?php

namespace Tests\Feature;

use App\Mail\UserNotificationEmail;
use App\Models\Brand;
use App\Models\BrandCommunication;
use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
use App\Models\RequestNotificationDelivery;
use App\Models\RequestWatcher;
use App\Services\InboundBrandCommunicationService;
use App\Services\ProductResolutionService;
use App\Services\RequestNotificationService;
use App\Services\RequestRecipientService;
use App\Services\UserNotificationSender;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ManufacturerReplyProcessingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        $this->createTables();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_inbound_message_id_is_normalized_and_idempotent(): void
    {
        $brand = Brand::create(['name' => 'Reply Brand']);
        $service = app(InboundBrandCommunicationService::class);

        $first = $service->record(
            $brand,
            ' <ABC.123@Example.COM> ',
            'Product reply',
            'Confirmed details.',
            ['9400000000001'],
            '/proofs/reply.txt',
        );
        $duplicate = $service->record(
            $brand,
            '<abc.123@example.com>',
            'Duplicate delivery',
            'Should not create another record.',
            ['9400000000001'],
        );

        $this->assertSame($first->id, $duplicate->id);
        $this->assertSame(1, BrandCommunication::count());
        $this->assertSame('<abc.123@example.com>', $first->email_message_id);
        $this->assertSame('pending_review', $first->processing_status);
    }

    public function test_recipient_union_is_valid_case_insensitive_and_excludes_placeholders(): void
    {
        $request = PrioritisationRequest::create([
            'barcode' => '9400000000001',
            'user_email' => ' Customer@Example.com ',
            'status' => 'pending',
        ]);
        RequestWatcher::create(['request_id' => $request->id, 'user_email' => 'customer@example.COM']);
        RequestWatcher::create(['request_id' => $request->id, 'user_email' => 'second@example.com']);
        RequestWatcher::create(['request_id' => $request->id, 'user_email' => 'not-an-email']);
        RequestWatcher::create(['request_id' => $request->id, 'user_email' => 'anonymous@halalkiwi.com']);

        $recipients = app(RequestRecipientService::class)->collect(
            PrioritisationRequest::with('watchers')->whereKey($request->id)->get()
        );

        $this->assertSame(['customer@example.com', 'second@example.com'], $recipients->all());
    }

    public function test_resolution_preserves_notes_links_evidence_and_notifies_all_eligible_users(): void
    {
        Mail::fake();
        $brand = Brand::create(['name' => 'Evidence Brand']);
        $communication = app(InboundBrandCommunicationService::class)->record(
            $brand,
            '<evidence@example.com>',
            'Halal evidence',
            'Exact product response.',
            ['9400000000001'],
            '/proofs/evidence.txt',
        );
        $product = Product::create([
            'Barcode' => '9400000000001',
            'product_name' => 'Evidence product',
            'halal_status' => '2',
            'notes' => 'Existing product history.',
        ]);
        $request = PrioritisationRequest::create([
            'barcode' => $product->Barcode,
            'product_name' => $product->product_name,
            'user_email' => 'Direct@Example.com',
            'status' => 'ready_for_review',
            'notes' => 'Existing request history.',
        ]);
        RequestWatcher::create(['request_id' => $request->id, 'user_email' => 'direct@example.com']);
        RequestWatcher::create(['request_id' => $request->id, 'user_email' => 'watcher@example.com']);
        RequestWatcher::create(['request_id' => $request->id, 'user_email' => 'missing@halalkiwi.com']);

        $result = app(ProductResolutionService::class)->resolve(
            $product->Barcode,
            '0',
            'Manufacturer evidence approved.',
            brandCommunicationId: $communication->id,
        );

        $this->assertSame(1, $result['requests_resolved']);
        $this->assertSame(2, $result['recipients_prepared']);
        $this->assertSame(['sent' => 2, 'failed' => 0, 'uncertain' => 0, 'sending' => 0, 'skipped' => 0], $result['delivery']);
        $this->assertSame('0', (string) $product->fresh()->halal_status);
        $this->assertSame('/proofs/evidence.txt', $product->fresh()->proof);
        $this->assertStringContainsString('Existing product history.', $product->fresh()->notes);
        $this->assertStringContainsString('Inbound communication #'.$communication->id, $product->fresh()->notes);
        $this->assertSame('resolved', $request->fresh()->status);
        $this->assertSame($communication->id, $request->fresh()->resolution_communication_id);
        $this->assertStringContainsString('Existing request history.', $request->fresh()->notes);
        $this->assertSame('applied', $communication->fresh()->processing_status);
        $this->assertNotNull($communication->fresh()->processed_at);
        $this->assertSame(2, RequestNotificationDelivery::where('status', 'sent')->count());
        Mail::assertSent(UserNotificationEmail::class, 2);
        Mail::assertSent(UserNotificationEmail::class, function (UserNotificationEmail $mail) {
            $body = $mail->render();

            return str_contains($body, 'completed its review')
                && ! str_contains($body, 'received confirmation');
        });
    }

    public function test_missing_exact_product_does_not_resolve_requests(): void
    {
        $request = PrioritisationRequest::create([
            'barcode' => '9400000000099',
            'status' => 'ready_for_review',
            'notes' => 'Keep me.',
        ]);

        try {
            app(ProductResolutionService::class)->resolve($request->barcode, '1');
            $this->fail('Expected the missing exact product to abort resolution.');
        } catch (ModelNotFoundException) {
            $this->assertSame('ready_for_review', $request->fresh()->status);
            $this->assertSame('Keep me.', $request->fresh()->notes);
            $this->assertSame(0, RequestNotificationDelivery::count());
        }
    }

    public function test_resolution_rejects_inbound_evidence_for_a_different_barcode(): void
    {
        $brand = Brand::create(['name' => 'Mismatch Brand']);
        $communication = app(InboundBrandCommunicationService::class)->record(
            $brand,
            '<mismatch@example.com>',
            'Different product',
            'This reply covers another barcode.',
            ['9400000000088'],
            '/proofs/mismatch.txt',
        );
        $product = Product::create([
            'Barcode' => '9400000000077',
            'product_name' => 'Unrelated product',
            'halal_status' => '2',
            'notes' => 'Original note.',
        ]);
        $request = PrioritisationRequest::create([
            'barcode' => $product->Barcode,
            'status' => 'ready_for_review',
        ]);

        try {
            app(ProductResolutionService::class)->resolve(
                $product->Barcode,
                '0',
                brandCommunicationId: $communication->id,
            );
            $this->fail('Expected unrelated evidence to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('does not explicitly cover', $exception->getMessage());
        }

        $this->assertSame('2', (string) $product->fresh()->halal_status);
        $this->assertSame('Original note.', $product->fresh()->notes);
        $this->assertSame('ready_for_review', $request->fresh()->status);
        $this->assertSame('pending_review', $communication->fresh()->processing_status);
    }

    public function test_resolution_uses_strict_barcode_comparison_for_inbound_evidence(): void
    {
        $brand = Brand::create(['name' => 'Strict Barcode Brand']);
        $communication = app(InboundBrandCommunicationService::class)->record(
            $brand,
            '<strict-barcode@example.com>',
            'Leading-zero product',
            'This reply covers a different exact barcode.',
            ['09400000000077'],
            '/proofs/strict-barcode.txt',
        );
        $product = Product::create([
            'Barcode' => '9400000000077',
            'product_name' => 'Strict barcode product',
            'halal_status' => '2',
        ]);
        $request = PrioritisationRequest::create([
            'barcode' => $product->Barcode,
            'status' => 'ready_for_review',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not explicitly cover this exact barcode');

        try {
            app(ProductResolutionService::class)->resolve(
                $product->Barcode,
                '0',
                brandCommunicationId: $communication->id,
            );
        } finally {
            $this->assertSame('2', (string) $product->fresh()->halal_status);
            $this->assertSame('ready_for_review', $request->fresh()->status);
            $this->assertSame('pending_review', $communication->fresh()->processing_status);
        }
    }

    public function test_resolution_rolls_back_when_delivery_preparation_fails(): void
    {
        $product = Product::create([
            'Barcode' => '9400000000002',
            'product_name' => 'Rollback product',
            'halal_status' => '2',
            'notes' => 'Original product note.',
        ]);
        $request = PrioritisationRequest::create([
            'barcode' => $product->Barcode,
            'status' => 'pending',
            'notes' => 'Original request note.',
        ]);
        $notifications = Mockery::mock(RequestNotificationService::class);
        $notifications->shouldReceive('prepareEvent')->once()->andThrow(new RuntimeException('Preparation failed.'));
        $service = new ProductResolutionService($notifications);

        try {
            $service->resolve($product->Barcode, '1');
            $this->fail('Expected resolution transaction to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Preparation failed.', $exception->getMessage());
        }

        $this->assertSame('2', (string) $product->fresh()->halal_status);
        $this->assertSame('Original product note.', $product->fresh()->notes);
        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame('Original request note.', $request->fresh()->notes);
    }

    public function test_ambiguous_transport_failure_requires_reconciliation_before_retry(): void
    {
        $request = PrioritisationRequest::create([
            'barcode' => '9400000000003',
            'product_name' => 'Retry product',
            'user_email' => 'first@example.com',
            'status' => 'pending',
        ]);
        RequestWatcher::create(['request_id' => $request->id, 'user_email' => 'second@example.com']);
        $requests = PrioritisationRequest::with('watchers')->whereKey($request->id)->get();

        $firstSender = Mockery::mock(UserNotificationSender::class);
        $firstSender->shouldReceive('send')->once()->with(Mockery::on(
            fn (RequestNotificationDelivery $delivery) => $delivery->recipient_email === 'first@example.com'
        ));
        $firstSender->shouldReceive('send')->once()->with(Mockery::on(
            fn (RequestNotificationDelivery $delivery) => $delivery->recipient_email === 'second@example.com'
        ))->andThrow(new RuntimeException('Temporary delivery failure.'));
        $notifications = new RequestNotificationService(new RequestRecipientService, $firstSender);
        $notifications->prepareEvent('retry-test', $requests, 'resolved', 'Retry product', $request->barcode, '0');

        $firstResult = $notifications->deliverEvent('retry-test');

        $this->assertSame(['sent' => 1, 'failed' => 0, 'uncertain' => 1, 'sending' => 0, 'skipped' => 0], $firstResult);
        $this->assertDatabaseHas('request_notification_deliveries', [
            'normalized_email' => 'first@example.com',
            'status' => 'sent',
            'attempts' => 1,
        ]);
        $this->assertDatabaseHas('request_notification_deliveries', [
            'normalized_email' => 'second@example.com',
            'status' => 'uncertain',
            'attempts' => 1,
        ]);
        $this->assertNotNull(RequestNotificationDelivery::where('normalized_email', 'second@example.com')->value('uncertain_at'));

        $blockedRetrySender = Mockery::mock(UserNotificationSender::class);
        $blockedRetrySender->shouldNotReceive('send');
        $blockedRetryNotifications = new RequestNotificationService(new RequestRecipientService, $blockedRetrySender);

        $blockedRetryResult = $blockedRetryNotifications->deliverEvent('retry-test');

        $this->assertSame(['sent' => 0, 'failed' => 0, 'uncertain' => 1, 'sending' => 0, 'skipped' => 0], $blockedRetryResult);

        RequestNotificationDelivery::where('normalized_email', 'second@example.com')->update(['status' => 'failed']);

        $retrySender = Mockery::mock(UserNotificationSender::class);
        $retrySender->shouldReceive('send')->once()->with(Mockery::on(
            fn (RequestNotificationDelivery $delivery) => $delivery->recipient_email === 'second@example.com'
        ));
        $retryNotifications = new RequestNotificationService(new RequestRecipientService, $retrySender);

        $retryResult = $retryNotifications->deliverEvent('retry-test');

        $this->assertSame(['sent' => 1, 'failed' => 0, 'uncertain' => 0, 'sending' => 0, 'skipped' => 0], $retryResult);
        $this->assertSame(2, RequestNotificationDelivery::where('status', 'sent')->count());
        $this->assertSame(1, RequestNotificationDelivery::where('normalized_email', 'first@example.com')->value('attempts'));
        $this->assertSame(2, RequestNotificationDelivery::where('normalized_email', 'second@example.com')->value('attempts'));
    }

    public function test_stuck_sending_delivery_is_reported_without_being_retried(): void
    {
        $request = PrioritisationRequest::create([
            'barcode' => '9400000000004',
            'product_name' => 'Interrupted product',
            'user_email' => 'customer@example.com',
            'status' => 'pending',
        ]);
        $requests = PrioritisationRequest::with('watchers')->whereKey($request->id)->get();
        $sender = Mockery::mock(UserNotificationSender::class);
        $sender->shouldNotReceive('send');
        $notifications = new RequestNotificationService(new RequestRecipientService, $sender);
        $notifications->prepareEvent('stuck-sending-test', $requests, 'resolved', $request->product_name, $request->barcode, '0');
        RequestNotificationDelivery::query()->update([
            'status' => 'sending',
            'attempts' => 1,
            'last_attempted_at' => now()->subMinutes(10),
        ]);

        $result = $notifications->deliverEvent('stuck-sending-test');

        $this->assertSame(['sent' => 0, 'failed' => 0, 'uncertain' => 0, 'sending' => 1, 'skipped' => 0], $result);
        $this->assertDatabaseHas('request_notification_deliveries', [
            'normalized_email' => 'customer@example.com',
            'status' => 'sending',
            'attempts' => 1,
        ]);
    }

    private function createTables(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
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
            $table->string('email_message_id')->nullable()->unique();
            $table->text('proof_path')->nullable();
            $table->string('processing_status')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('Barcode');
            $table->string('product_name')->nullable();
            $table->string('halal_status')->default('2');
            $table->text('proof')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('prioritisation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 20);
            $table->string('product_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('status')->default('pending');
            $table->tinyInteger('resolved_status')->nullable();
            $table->unsignedBigInteger('resolution_communication_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('request_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id');
            $table->string('user_email');
            $table->string('user_name')->nullable();
            $table->timestamps();
        });
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
