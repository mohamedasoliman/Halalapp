<?php

namespace Tests\Feature;

use App\Mail\UserNotificationEmail;
use App\Models\Brand;
use App\Models\BrandCommunication;
use App\Models\BrandCommunicationBarcodeDisposition;
use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
use App\Models\RequestNotificationDelivery;
use App\Models\RequestWatcher;
use App\Services\BrandCommunicationDispositionService;
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
        $this->assertDatabaseHas('brand_communication_barcode_dispositions', [
            'brand_communication_id' => $first->id,
            'barcode' => '9400000000001',
            'disposition' => 'pending_review',
        ]);
    }

    public function test_duplicate_message_id_with_a_different_scope_is_rejected(): void
    {
        $brand = Brand::create(['name' => 'Conflicting Reply Brand']);
        $service = app(InboundBrandCommunicationService::class);
        $service->record(
            $brand,
            '<same-message@example.com>',
            'First scope',
            'First delivery.',
            ['9400000000001'],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('different brand or exact-barcode scope');

        $service->record(
            $brand,
            '<same-message@example.com>',
            'Changed scope',
            'Conflicting duplicate delivery.',
            ['9400000000002'],
        );
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
        $this->assertSame('Existing product history.', $product->fresh()->notes);
        $this->assertSame('resolved', $request->fresh()->status);
        $this->assertSame($communication->id, $request->fresh()->resolution_communication_id);
        $this->assertStringContainsString('Existing request history.', $request->fresh()->notes);
        $this->assertStringContainsString('Manufacturer evidence approved.', $request->fresh()->notes);
        $this->assertStringContainsString('Inbound communication #'.$communication->id, $request->fresh()->notes);
        $this->assertStringContainsString('Proof: /proofs/evidence.txt.', $request->fresh()->notes);
        $this->assertSame('applied', $communication->fresh()->processing_status);
        $this->assertNotNull($communication->fresh()->processed_at);
        $this->assertDatabaseHas('brand_communication_barcode_dispositions', [
            'brand_communication_id' => $communication->id,
            'barcode' => $product->Barcode,
            'disposition' => 'applied',
            'resolved_status' => 0,
            'product_id' => $product->id,
        ]);
        $this->assertSame(2, RequestNotificationDelivery::where('status', 'sent')->count());
        Mail::assertSent(UserNotificationEmail::class, 2);
        Mail::assertSent(UserNotificationEmail::class, function (UserNotificationEmail $mail) {
            $body = $mail->render();

            return str_contains($body, 'completed its review')
                && ! str_contains($body, 'received confirmation');
        });
    }

    public function test_multi_barcode_reply_is_partial_until_every_barcode_has_an_outcome(): void
    {
        Mail::fake();
        $brand = Brand::create(['name' => 'Multi Product Brand']);
        $barcodes = ['9400000000020', '9400000000021'];
        $communication = app(InboundBrandCommunicationService::class)->record(
            $brand,
            '<multi-product@example.com>',
            'Two product response',
            'The reply covers two exact products.',
            $barcodes,
            '/proofs/multi-product.txt',
        );
        foreach ($barcodes as $index => $barcode) {
            Product::create([
                'Barcode' => $barcode,
                'product_name' => 'Multi product '.($index + 1),
                'halal_status' => '2',
            ]);
            PrioritisationRequest::create([
                'barcode' => $barcode,
                'status' => 'ready_for_review',
            ]);
        }

        app(ProductResolutionService::class)->resolve(
            $barcodes[0],
            '0',
            brandCommunicationId: $communication->id,
            notify: false,
        );

        $this->assertSame('partially_processed', $communication->fresh()->processing_status);
        $this->assertNull($communication->fresh()->processed_at);
        $this->assertDatabaseHas('brand_communication_barcode_dispositions', [
            'brand_communication_id' => $communication->id,
            'barcode' => $barcodes[1],
            'disposition' => 'pending_review',
        ]);

        app(ProductResolutionService::class)->resolve(
            $barcodes[1],
            '1',
            brandCommunicationId: $communication->id,
            notify: false,
        );

        $this->assertSame('applied', $communication->fresh()->processing_status);
        $this->assertNotNull($communication->fresh()->processed_at);
        $this->assertSame(
            2,
            BrandCommunicationBarcodeDisposition::where('disposition', 'applied')->count(),
        );
    }

    public function test_non_verdict_barcode_outcome_can_complete_a_mixed_reply(): void
    {
        $brand = Brand::create(['name' => 'Clarification Brand']);
        $barcodes = ['9400000000030', '9400000000031'];
        $communication = app(InboundBrandCommunicationService::class)->record(
            $brand,
            '<mixed-outcomes@example.com>',
            'Mixed response',
            'One answer and one question.',
            $barcodes,
            '/proofs/mixed.txt',
        );
        $product = Product::create([
            'Barcode' => $barcodes[0],
            'product_name' => 'Answered product',
            'halal_status' => '2',
        ]);

        app(ProductResolutionService::class)->resolve(
            $product->Barcode,
            '0',
            brandCommunicationId: $communication->id,
            notify: false,
        );
        app(BrandCommunicationDispositionService::class)->recordNonVerdict(
            $communication->id,
            $barcodes[1],
            'needs_clarification',
            'Manufacturer must identify the flavour source.',
        );

        $this->assertSame('processed', $communication->fresh()->processing_status);
        $this->assertNotNull($communication->fresh()->processed_at);
    }

    public function test_resolution_stores_only_an_explicit_public_note_on_the_product(): void
    {
        Mail::fake();
        $product = Product::create([
            'Barcode' => '9400000000010',
            'product_name' => 'Public note product',
            'halal_status' => '2',
            'notes' => 'Old user-facing note.',
        ]);
        $request = PrioritisationRequest::create([
            'barcode' => $product->Barcode,
            'status' => 'ready_for_review',
        ]);

        app(ProductResolutionService::class)->resolve(
            $product->Barcode,
            '1',
            'Internal evidence reviewed on 2026-07-23.',
            '/proofs/private-file.txt',
            notify: false,
            publicNote: 'Contains carmine (E120).',
        );

        $this->assertSame('Contains carmine (E120).', $product->fresh()->notes);
        $this->assertSame('/proofs/private-file.txt', $product->fresh()->proof);
        $this->assertStringContainsString('Internal evidence reviewed on 2026-07-23.', $request->fresh()->notes);
        $this->assertStringContainsString('Proof: /proofs/private-file.txt.', $request->fresh()->notes);
        $this->assertStringNotContainsString('/proofs/private-file.txt', $product->fresh()->notes);
    }

    public function test_resolution_rejects_technical_metadata_in_a_public_note(): void
    {
        $product = Product::create([
            'Barcode' => '9400000000011',
            'product_name' => 'Invalid public note product',
            'halal_status' => '2',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot contain dates, proof locations, or internal communication IDs');

        app(ProductResolutionService::class)->resolve(
            $product->Barcode,
            '0',
            notify: false,
            publicNote: 'Confirmed 2026-07-23. Proof: Brand_Proofs/example.txt',
        );
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
        $service = new ProductResolutionService(
            $notifications,
            app(BrandCommunicationDispositionService::class),
        );

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

    public function test_resolution_failure_rolls_back_the_exact_barcode_disposition(): void
    {
        $brand = Brand::create(['name' => 'Disposition Rollback Brand']);
        $communication = app(InboundBrandCommunicationService::class)->record(
            $brand,
            '<disposition-rollback@example.com>',
            'Rollback reply',
            'Exact product evidence.',
            ['9400000000040'],
            '/proofs/disposition-rollback.txt',
        );
        $product = Product::create([
            'Barcode' => '9400000000040',
            'product_name' => 'Disposition rollback product',
            'halal_status' => '2',
        ]);
        PrioritisationRequest::create([
            'barcode' => $product->Barcode,
            'status' => 'ready_for_review',
        ]);
        $notifications = Mockery::mock(RequestNotificationService::class);
        $notifications->shouldReceive('prepareEvent')->once()->andThrow(new RuntimeException('Preparation failed.'));
        $service = new ProductResolutionService(
            $notifications,
            app(BrandCommunicationDispositionService::class),
        );

        try {
            $service->resolve(
                $product->Barcode,
                '0',
                brandCommunicationId: $communication->id,
            );
            $this->fail('Expected resolution transaction to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Preparation failed.', $exception->getMessage());
        }

        $this->assertSame('2', (string) $product->fresh()->halal_status);
        $this->assertSame('pending_review', $communication->fresh()->processing_status);
        $this->assertDatabaseHas('brand_communication_barcode_dispositions', [
            'brand_communication_id' => $communication->id,
            'barcode' => $product->Barcode,
            'disposition' => 'pending_review',
            'resolved_status' => null,
        ]);
    }

    public function test_repeated_same_resolution_is_idempotent_but_conflicting_verdict_is_rejected(): void
    {
        $brand = Brand::create(['name' => 'Idempotent Disposition Brand']);
        $communication = app(InboundBrandCommunicationService::class)->record(
            $brand,
            '<idempotent-disposition@example.com>',
            'Idempotent reply',
            'Exact product evidence.',
            ['9400000000041'],
            '/proofs/idempotent-disposition.txt',
        );
        $product = Product::create([
            'Barcode' => '9400000000041',
            'product_name' => 'Idempotent disposition product',
            'halal_status' => '2',
        ]);

        $service = app(ProductResolutionService::class);
        $service->resolve(
            $product->Barcode,
            '0',
            brandCommunicationId: $communication->id,
            notify: false,
        );
        $service->resolve(
            $product->Barcode,
            '0',
            brandCommunicationId: $communication->id,
            notify: false,
        );

        $actionLine = "Approved Halal resolution applied to {$product->Barcode}.";
        $this->assertSame(1, substr_count((string) $communication->fresh()->action_taken, $actionLine));
        $this->assertSame(1, BrandCommunicationBarcodeDisposition::count());

        try {
            $service->resolve(
                $product->Barcode,
                '1',
                brandCommunicationId: $communication->id,
                notify: false,
            );
            $this->fail('Expected the conflicting verdict to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('different terminal disposition', $exception->getMessage());
        }

        $this->assertSame('0', (string) $product->fresh()->halal_status);
        $this->assertDatabaseHas('brand_communication_barcode_dispositions', [
            'brand_communication_id' => $communication->id,
            'barcode' => $product->Barcode,
            'disposition' => 'applied',
            'resolved_status' => 0,
        ]);
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
        Schema::create('brand_communication_barcode_dispositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_communication_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('barcode', 20);
            $table->string('disposition', 40)->default('pending_review');
            $table->tinyInteger('resolved_status')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['brand_communication_id', 'barcode']);
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
