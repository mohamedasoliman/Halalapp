<?php

namespace Tests\Feature;

use App\Jobs\SendBrandOutreachBatch;
use App\Mail\BrandOutreachEmail;
use App\Mail\UserNotificationEmail;
use App\Models\Brand;
use App\Models\BrandCommunication;
use App\Models\BrandOutreachBatch;
use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
use App\Models\RequestWatcher;
use App\Services\BrandOutreachService;
use App\Services\RequestNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
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

    public function test_future_approval_is_durable_and_does_not_queue_immediately(): void
    {
        Queue::fake();
        $this->travelTo(Carbon::parse('2026-08-10 09:00:00', 'Pacific/Auckland'));
        [$brand, $request, $batch] = $this->createSchedulableBatch('HK-SCHEDULED-1');
        $notBefore = now('Pacific/Auckland')->addDay();

        $approved = app(BrandOutreachService::class)->approveScheduledBatches(
            collect([$batch]),
            $notBefore,
            'prioritisation:2026-08-07:approved-in-chat',
        );

        $this->assertSame([$batch->id], $approved);
        $this->assertSame('approved', $batch->fresh()->status);
        $this->assertSame(
            '2026-08-10 21:00:00',
            DB::table('brand_outreach_batches')->where('id', $batch->id)->value('not_before_at'),
        );
        $this->assertSame('prioritisation:2026-08-07:approved-in-chat', $batch->fresh()->approval_reference);
        $this->assertNotNull($batch->fresh()->approved_at);
        $this->assertSame('ready_for_outreach', $request->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_due_approval_revalidates_and_queues_exactly_once(): void
    {
        Queue::fake();
        config(['outreach.enabled' => true]);
        $this->travelTo(Carbon::parse('2026-08-10 09:00:00', 'Pacific/Auckland'));
        [, , $batch] = $this->createSchedulableBatch('HK-SCHEDULED-2');
        $service = app(BrandOutreachService::class);
        $service->approveScheduledBatches(
            collect([$batch]),
            now('Pacific/Auckland')->addHour(),
            'prioritisation:2026-08-07:approved-in-chat',
        );

        $this->travel(61)->minutes();
        $first = $service->releaseScheduledApprovals();
        $second = $service->releaseScheduledApprovals();

        $this->assertSame([$batch->id], $first['queued']);
        $this->assertSame([], $second['queued']);
        $this->assertSame('queued', $batch->fresh()->status);
        Queue::assertPushed(SendBrandOutreachBatch::class, 1);
    }

    public function test_due_approval_requires_review_when_recipient_changes(): void
    {
        Queue::fake();
        config(['outreach.enabled' => true]);
        $this->travelTo(Carbon::parse('2026-08-10 09:00:00', 'Pacific/Auckland'));
        [$brand, , $batch] = $this->createSchedulableBatch('HK-SCHEDULED-3');
        $service = app(BrandOutreachService::class);
        $service->approveScheduledBatches(
            collect([$batch]),
            now('Pacific/Auckland')->addHour(),
            'prioritisation:2026-08-07:approved-in-chat',
        );
        $brand->update(['email' => 'new-contact@example.com']);

        $this->travel(61)->minutes();
        $result = $service->releaseScheduledApprovals();

        $this->assertArrayHasKey($batch->id, $result['review_required']);
        $this->assertSame('review_required', $batch->fresh()->status);
        $this->assertStringContainsString('recipient changed', $batch->fresh()->error);
        Queue::assertNothingPushed();
    }

    public function test_due_approval_requires_review_after_a_manufacturer_reply(): void
    {
        Queue::fake();
        config(['outreach.enabled' => true]);
        $this->travelTo(Carbon::parse('2026-08-10 09:00:00', 'Pacific/Auckland'));
        [$brand, , $batch] = $this->createSchedulableBatch('HK-SCHEDULED-4');
        $service = app(BrandOutreachService::class);
        $service->approveScheduledBatches(
            collect([$batch]),
            now('Pacific/Auckland')->addHour(),
            'prioritisation:2026-08-07:approved-in-chat',
        );
        $this->travel(10)->minutes();
        BrandCommunication::create([
            'brand_id' => $brand->id,
            'direction' => 'inbound',
            'subject' => 'Re: product inquiry',
        ]);

        $this->travel(51)->minutes();
        $service->releaseScheduledApprovals();

        $this->assertSame('review_required', $batch->fresh()->status);
        $this->assertStringContainsString('reply arrived', $batch->fresh()->error);
        Queue::assertNothingPushed();
    }

    public function test_due_approval_requires_review_when_product_is_no_longer_unreviewed(): void
    {
        Queue::fake();
        config(['outreach.enabled' => true]);
        $this->travelTo(Carbon::parse('2026-08-10 09:00:00', 'Pacific/Auckland'));
        [, , $batch] = $this->createSchedulableBatch('HK-SCHEDULED-5');
        $service = app(BrandOutreachService::class);
        $service->approveScheduledBatches(
            collect([$batch]),
            now('Pacific/Auckland')->addHour(),
            'prioritisation:2026-08-07:approved-in-chat',
        );
        Product::where('Barcode', '9400000000001')->update(['halal_status' => 0]);

        $this->travel(61)->minutes();
        $service->releaseScheduledApprovals();

        $this->assertSame('review_required', $batch->fresh()->status);
        $this->assertStringContainsString('no longer active and unreviewed', $batch->fresh()->error);
        Queue::assertNothingPushed();
    }

    public function test_scheduled_approval_command_requires_exact_batches_and_keeps_email_unsent(): void
    {
        Queue::fake();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-08-10 09:00:00', 'Pacific/Auckland'));
        [, , $batch] = $this->createSchedulableBatch('HK-SCHEDULED-COMMAND');

        $this->artisan('brands:outreach', [
            '--approve' => true,
            '--batch' => [(string) $batch->id],
            '--not-before' => '2026-08-11 09:00',
            '--approval-reference' => 'prioritisation:2026-08-07:approved-in-chat',
        ])->assertSuccessful();

        $this->assertSame('approved', $batch->fresh()->status);
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
    }

    public function test_clarification_draft_is_idempotent_and_requires_strict_source_barcode_evidence(): void
    {
        $brand = Brand::create([
            'name' => 'Partial Brand',
            'email' => 'quality@example.com',
            'contact_type' => 'email',
            'contact_research_status' => 'verified',
            'response' => 'partial',
        ]);
        Product::create([
            'product_name' => 'Leading Zero Product',
            'Barcode' => '0123456789012',
            'halal_status' => 2,
        ]);
        $communication = BrandCommunication::create([
            'brand_id' => $brand->id,
            'direction' => 'inbound',
            'email_message_id' => '<Reply.123@Example.com>',
            'barcodes_mentioned' => ['0123456789012'],
            'proof_path' => '/proof/partial-brand',
        ]);
        $service = app(BrandOutreachService::class);

        $first = $service->createClarificationDraft(
            $brand,
            $communication,
            'manufacturer-clarification:test:0123456789012',
            'Re: Exact product question',
            'Please confirm the exact ingredient source.',
            ['0123456789012'],
            ['<Earlier.1@Example.com>'],
        );
        $second = $service->createClarificationDraft(
            $brand,
            $communication,
            'manufacturer-clarification:test:0123456789012',
            'Re: Exact product question',
            'Please confirm the exact ingredient source.',
            ['0123456789012'],
            ['<Earlier.1@Example.com>'],
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, BrandOutreachBatch::count());
        $this->assertSame('clarification', $first->kind);
        $this->assertSame('<reply.123@example.com>', $first->in_reply_to_message_id);
        $this->assertSame(
            ['<reply.123@example.com>', '<earlier.1@example.com>'],
            $first->reference_message_ids,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not cover exact barcode');
        $service->createClarificationDraft(
            $brand,
            $communication,
            'manufacturer-clarification:test:loose-leading-zero',
            'Re: Wrong barcode',
            'This must not be created.',
            ['123456789012'],
        );
    }

    public function test_partial_brand_can_queue_only_an_explicitly_approved_clarification_batch(): void
    {
        Queue::fake();
        config(['outreach.enabled' => true]);
        $brand = Brand::create([
            'name' => 'Answered Brand',
            'email' => 'quality@example.com',
            'contact_type' => 'email',
            'contact_research_status' => 'verified',
            'response' => 'partial',
        ]);
        Product::create([
            'product_name' => 'Question Product',
            'Barcode' => '9400000000015',
            'halal_status' => 2,
        ]);
        $communication = BrandCommunication::create([
            'brand_id' => $brand->id,
            'direction' => 'inbound',
            'email_message_id' => '<partial@example.com>',
            'barcodes_mentioned' => ['9400000000015'],
            'proof_path' => '/proof/answered-brand',
        ]);
        $initial = $this->createBatch($brand, 'HK-BLOCKED-INITIAL');
        $clarification = app(BrandOutreachService::class)->createClarificationDraft(
            $brand,
            $communication,
            'manufacturer-clarification:test:queue-partial',
            'Re: Clarification',
            'Please clarify this exact product.',
            ['9400000000015'],
        );

        $queued = app(BrandOutreachService::class)->queueDrafts(collect([$initial, $clarification]));

        $this->assertSame([$clarification->id], $queued);
        $this->assertSame('draft', $initial->fresh()->status);
        $this->assertSame('queued', $clarification->fresh()->status);
        Queue::assertPushed(SendBrandOutreachBatch::class, 1);
    }

    public function test_clarification_command_creates_preview_only_draft_and_never_sends(): void
    {
        Mail::fake();
        Queue::fake();
        $brand = Brand::create([
            'name' => 'Command Brand',
            'email' => 'quality@example.com',
            'contact_type' => 'email',
            'contact_research_status' => 'verified',
            'response' => 'partial',
        ]);
        Product::create([
            'product_name' => 'Command Product',
            'Barcode' => '9400000000017',
            'halal_status' => 2,
        ]);
        $communication = BrandCommunication::create([
            'brand_id' => $brand->id,
            'direction' => 'inbound',
            'email_message_id' => '<command@example.com>',
            'barcodes_mentioned' => ['9400000000017'],
            'proof_path' => '/proof/command-brand',
        ]);

        $this->artisan('brands:clarification', [
            'brand' => (string) $brand->id,
            '--communication-id' => (string) $communication->id,
            '--event' => 'manufacturer-clarification:test:command',
            '--subject' => 'Re: Command clarification',
            '--body' => 'Please confirm the requested detail.',
            '--barcode' => ['9400000000017'],
        ])->assertSuccessful();

        $this->assertDatabaseHas('brand_outreach_batches', [
            'brand_id' => $brand->id,
            'kind' => 'clarification',
            'status' => 'draft',
            'source_communication_id' => $communication->id,
        ]);
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
    }

    public function test_clarification_cannot_queue_without_saved_inbound_proof(): void
    {
        Queue::fake();
        config(['outreach.enabled' => true]);
        $brand = Brand::create([
            'name' => 'Proofless Brand',
            'email' => 'quality@example.com',
            'contact_type' => 'email',
            'contact_research_status' => 'verified',
            'response' => 'partial',
        ]);
        Product::create([
            'product_name' => 'Proofless Product',
            'Barcode' => '9400000000018',
            'halal_status' => 2,
        ]);
        $communication = BrandCommunication::create([
            'brand_id' => $brand->id,
            'direction' => 'inbound',
            'email_message_id' => '<proofless@example.com>',
            'barcodes_mentioned' => ['9400000000018'],
        ]);
        $batch = app(BrandOutreachService::class)->createClarificationDraft(
            $brand,
            $communication,
            'manufacturer-clarification:test:proof-required',
            'Re: Proof required',
            'This draft must remain unsent until proof is saved.',
            ['9400000000018'],
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('missing exact inbound evidence, proof');
        app(BrandOutreachService::class)->queueDrafts(collect([$batch]));
    }

    public function test_clarification_job_sends_custom_threaded_email_without_user_notification_or_brand_downgrade(): void
    {
        Mail::fake();
        config(['outreach.enabled' => true]);
        $brand = Brand::create([
            'name' => 'Clarification Brand',
            'email' => 'quality@example.com',
            'contact_type' => 'email',
            'contact_research_status' => 'verified',
            'response' => 'partial',
            'follow_up_count' => 2,
            'next_follow_up_at' => now()->addDay(),
        ]);
        Product::create([
            'product_name' => 'Clarification Product',
            'Barcode' => '9400000000016',
            'halal_status' => 2,
        ]);
        $request = $this->createRequest('Clarification Brand', '9400000000016', 'Clarification Product', 'contacted');
        $request->update(['user_email' => 'customer@example.com']);
        $communication = BrandCommunication::create([
            'brand_id' => $brand->id,
            'direction' => 'inbound',
            'email_message_id' => '<clarify@example.com>',
            'barcodes_mentioned' => ['9400000000016'],
            'proof_path' => '/proof/clarification-brand',
        ]);
        $batch = app(BrandOutreachService::class)->createClarificationDraft(
            $brand,
            $communication,
            'manufacturer-clarification:test:send',
            'Re: Approved clarification',
            'Please confirm whether the flavour carrier contains ethanol.',
            ['9400000000016'],
        );
        $batch->update(['status' => 'queued']);

        (new SendBrandOutreachBatch($batch->id))->handle(app(BrandOutreachService::class));

        Mail::assertSent(BrandOutreachEmail::class, function (BrandOutreachEmail $mail) {
            return $mail->subjectOverride === 'Re: Approved clarification'
                && $mail->body === 'Please confirm whether the flavour carrier contains ethanol.'
                && $mail->inReplyTo === '<clarify@example.com>'
                && $mail->references === ['<clarify@example.com>'];
        });
        Mail::assertSent(UserNotificationEmail::class, 0);
        $this->assertSame('sent', $batch->fresh()->status);
        $this->assertSame(2, $brand->fresh()->follow_up_count);
        $this->assertSame('partial', $brand->fresh()->response);
        $this->assertSame('contacted', $request->fresh()->status);
        $this->assertSame('awaiting_response', $communication->fresh()->processing_status);
        $this->assertStringContainsString('awaiting manufacturer response', $communication->fresh()->action_taken);
        $this->assertNotNull($communication->fresh()->processed_at);
        $this->assertDatabaseHas('brand_communications', [
            'brand_id' => $brand->id,
            'direction' => 'outbound',
            'subject' => 'Re: Approved clarification',
        ]);
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

    public function test_stuck_or_uncertain_clarification_is_never_automatically_requeued(): void
    {
        Queue::fake();
        config(['outreach.enabled' => true]);
        $brand = Brand::create([
            'name' => 'Stuck Clarification Brand',
            'email' => 'quality@example.com',
            'contact_type' => 'email',
            'contact_research_status' => 'verified',
            'response' => 'partial',
        ]);
        $batch = $this->createBatch($brand, 'HK-STUCK-CLARIFICATION', 'sending');
        $batch->update(['kind' => 'clarification']);

        (new SendBrandOutreachBatch($batch->id))->failed(new RuntimeException('SMTP result was interrupted.'));
        $queued = app(BrandOutreachService::class)->queueDrafts(collect([$batch->fresh()]));

        $this->assertSame('uncertain', $batch->fresh()->status);
        $this->assertSame([], $queued);
        Queue::assertNothingPushed();
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

    private function createSchedulableBatch(string $reference): array
    {
        $brand = Brand::create([
            'name' => $reference.' Brand',
            'email' => 'quality@example.com',
            'contact_type' => 'email',
            'contact_research_status' => 'verified',
        ]);
        Product::create([
            'product_name' => 'Test product',
            'Barcode' => '9400000000001',
            'status' => 1,
            'halal_status' => 2,
        ]);
        $request = $this->createRequest($brand->name, '9400000000001', 'Test product', 'ready_for_outreach');
        $batch = $this->createBatch($brand, $reference, 'draft', [$request->id]);

        return [$brand, $request, $batch];
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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->string('Barcode', 20);
            $table->tinyInteger('status')->default(1);
            $table->tinyInteger('halal_status')->default(2);
            $table->softDeletes();
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
            $table->longText('message_body')->nullable();
            $table->json('products');
            $table->json('request_ids');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('not_before_at')->nullable();
            $table->string('approval_reference', 500)->nullable();
            $table->timestamp('review_required_at')->nullable();
            $table->unsignedBigInteger('source_communication_id')->nullable();
            $table->string('event_reference', 500)->nullable();
            $table->char('event_key', 64)->nullable()->unique();
            $table->string('in_reply_to_message_id', 998)->nullable();
            $table->text('reference_message_ids')->nullable();
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
