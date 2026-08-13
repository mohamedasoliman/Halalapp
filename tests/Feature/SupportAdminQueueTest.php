<?php

namespace Tests\Feature;

use App\Admin;
use App\Models\SupportAttachment;
use App\Models\SupportDelivery;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Services\SupportReplyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SupportAdminQueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'support.mail_enabled' => false,
        ]);
        DB::purge('sqlite');

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_support_queue_requires_admin_authentication(): void
    {
        $this->get(route('support.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_every_support_route_and_navigation_entry_are_restricted_to_role_one(): void
    {
        $supportRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'admin/support'));

        $this->assertCount(8, $supportRoutes);
        foreach ($supportRoutes as $supportRoute) {
            $this->assertContains('check_role:1', $supportRoute->gatherMiddleware(), $supportRoute->uri());
        }

        $limitedAdmin = $this->admin([
            'name' => 'Limited Administrator',
            'email' => 'limited-admin@example.com',
            'role_id' => 2,
        ]);

        $this->actingAs($limitedAdmin, 'admin')
            ->get(route('support.index'))
            ->assertForbidden();

        Route::get('/test-only/support-sidebar', fn () => view('admin.include.sidebar'))
            ->middleware(['web', 'auth:admin']);

        $this->actingAs($limitedAdmin, 'admin')
            ->get('/test-only/support-sidebar')
            ->assertOk()
            ->assertDontSee('App Support');
    }

    public function test_queue_filters_tickets_and_renders_text_labels_for_status_and_priority(): void
    {
        $urgent = $this->ticket([
            'reference' => 'HK-SUP-000101',
            'subject' => 'Scanner crashes after barcode scan',
            'category' => 'bug_report',
            'priority' => 'urgent',
        ]);
        $this->ticket([
            'reference' => 'HK-SUP-000102',
            'subject' => 'General app question',
            'priority' => 'normal',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('support.index', ['priority' => 'urgent']))
            ->assertOk()
            ->assertSee($urgent->reference)
            ->assertSee('Scanner crashes after barcode scan')
            ->assertSee('Priority: Urgent')
            ->assertSee('Status: New')
            ->assertSee('Category: Bug Report')
            ->assertDontSee('HK-SUP-000102')
            ->assertSee('This queue is isolated from product workflows.')
            ->assertSee('for="support-search"', false)
            ->assertSee('assets/css/support-admin.css');
    }

    public function test_only_active_role_one_admins_can_be_selected_as_ticket_owners(): void
    {
        $operator = $this->admin([
            'name' => 'Eligible Support Owner',
            'email' => 'eligible-owner@example.com',
        ]);
        $limitedAdmin = $this->admin([
            'name' => 'Forbidden Limited Owner',
            'email' => 'limited-owner@example.com',
            'role_id' => 2,
        ]);
        $inactiveAdmin = $this->admin([
            'name' => 'Inactive Support Owner',
            'email' => 'inactive-owner@example.com',
            'status' => 0,
        ]);
        $ticket = $this->ticket();

        $this->actingAs($operator, 'admin')
            ->get(route('support.show', $ticket))
            ->assertOk()
            ->assertSee('Eligible Support Owner')
            ->assertDontSee('Forbidden Limited Owner')
            ->assertDontSee('Inactive Support Owner');

        $this->actingAs($operator, 'admin')
            ->patch(route('support.triage', $ticket), [
                'status' => $ticket->status,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'assigned_to' => $limitedAdmin->id,
            ])
            ->assertSessionHasErrors('assigned_to');

        $this->assertNull($ticket->fresh()->assigned_to);

        $this->actingAs($operator, 'admin')
            ->patch(route('support.triage', $ticket), [
                'status' => $ticket->status,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'assigned_to' => $inactiveAdmin->id,
            ])
            ->assertSessionHasErrors('assigned_to');

        $this->assertNull($ticket->fresh()->assigned_to);
    }

    public function test_handoff_triage_only_records_support_metadata_and_leaves_existing_product_flows_unchanged(): void
    {
        DB::table('products')->insert(['id' => 501, 'Barcode' => '9400000123456', 'halal_status' => 2]);
        DB::table('prioritisation_requests')->insert(['id' => 601, 'barcode' => '9400000123456', 'status' => 'pending']);
        DB::table('brands')->insert(['id' => 701, 'name' => 'Example Brand', 'response' => null]);
        $before = [
            'product' => (array) DB::table('products')->where('id', 501)->first(),
            'request' => (array) DB::table('prioritisation_requests')->where('id', 601)->first(),
            'brand' => (array) DB::table('brands')->where('id', 701)->first(),
        ];
        $ticket = $this->ticket(['category' => 'product_issue']);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->patch(route('support.triage', $ticket), [
                'status' => 'triaged',
                'category' => 'product_issue',
                'priority' => 'high',
                'assigned_to' => $admin->id,
                'proposed_handoff' => 'product_prioritisation',
                'linked_entity_type' => 'product',
                'linked_entity_id' => '501',
                'linked_barcode' => '9400000123456',
            ])
            ->assertRedirect(route('support.show', $ticket))
            ->assertSessionHas('success');

        $ticket->refresh();
        $this->assertSame('triaged', $ticket->status);
        $this->assertSame('high', $ticket->priority);
        $this->assertSame('product_prioritisation', $ticket->proposed_handoff);
        $this->assertSame('product', $ticket->linked_entity_type);
        $this->assertSame('501', $ticket->linked_entity_id);
        $this->assertSame('9400000123456', $ticket->linked_barcode);
        $this->assertDatabaseHas('support_ticket_events', [
            'support_ticket_id' => $ticket->id,
            'event_type' => 'triaged',
            'actor_admin_id' => $admin->id,
        ]);
        $this->assertSame($before['product'], (array) DB::table('products')->where('id', 501)->first());
        $this->assertSame($before['request'], (array) DB::table('prioritisation_requests')->where('id', 601)->first());
        $this->assertSame($before['brand'], (array) DB::table('brands')->where('id', 701)->first());
    }

    public function test_closure_requires_a_reason_and_resolution_is_blocked_by_an_unsent_draft(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket();

        $this->actingAs($admin, 'admin')
            ->patch(route('support.triage', $ticket), [
                'status' => 'no_action',
                'category' => 'no_action',
                'priority' => 'low',
            ])
            ->assertSessionHasErrors('resolution_note');

        $this->assertSame('new', $ticket->fresh()->status);

        $this->actingAs($admin, 'admin')
            ->patch(route('support.triage', $ticket), [
                'status' => 'no_action',
                'category' => 'no_action',
                'priority' => 'low',
                'resolution_note' => 'Duplicate automated acknowledgement; no customer action required.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('no_action', $ticket->fresh()->status);
        $this->assertSame(
            'Duplicate automated acknowledgement; no customer action required.',
            $ticket->fresh()->resolution_note
        );

        $blocked = $this->ticket(['reference' => 'HK-SUP-000299']);
        $draft = $this->message($blocked, ['direction' => 'outbound_draft']);

        $this->actingAs($admin, 'admin')
            ->patch(route('support.triage', $blocked), [
                'status' => 'resolved',
                'category' => 'general_inquiry',
                'priority' => 'normal',
                'resolution_note' => 'Question answered.',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('new', $blocked->fresh()->status);

        $this->actingAs($admin, 'admin')
            ->patch(route('support.triage', $blocked), [
                'status' => 'no_action',
                'category' => 'no_action',
                'priority' => 'low',
                'resolution_note' => 'No response should be sent.',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('new', $blocked->fresh()->status);

        $this->actingAs($admin, 'admin')
            ->post(route('support.reply-drafts.discard', [$blocked, $draft]), [
                'discard_reason' => 'Superseded by a corrected response.',
                'confirm_discard' => '1',
            ])
            ->assertRedirect(route('support.show', $blocked))
            ->assertSessionHas('success');

        $this->assertSame('discarded_draft', $draft->fresh()->direction);
        $this->assertDatabaseHas('support_ticket_events', [
            'support_ticket_id' => $blocked->id,
            'event_type' => 'reply_draft_discarded',
            'actor_admin_id' => $admin->id,
            'details' => 'Superseded by a corrected response.',
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('support.triage', $blocked), [
                'status' => 'resolved',
                'category' => 'general_inquiry',
                'priority' => 'normal',
                'resolution_note' => 'Question handled without sending the superseded draft.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('resolved', $blocked->fresh()->status);
    }

    public function test_sending_a_reply_requires_the_exact_confirmation_and_approval_reference(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket();
        $draft = $this->message($ticket, ['direction' => 'outbound_draft']);
        $mock = $this->mock(SupportReplyService::class);
        $mock->shouldNotReceive('sendApprovedDraft');

        $this->actingAs($admin, 'admin')
            ->post(route('support.reply-drafts.send', [$ticket, $draft]), [
                'approval_reference' => 'Approved in support review',
            ])
            ->assertSessionHasErrors('confirm_send');

        $this->releaseServiceMock(SupportReplyService::class);
        $mock = $this->mock(SupportReplyService::class);
        $mock->shouldReceive('sendApprovedDraft')
            ->once()
            ->with(
                Mockery::on(fn (SupportMessage $message): bool => $message->is($draft)),
                'Approved in support review'
            )
            ->andReturn(new SupportDelivery(['status' => 'sent']));

        $this->actingAs($admin, 'admin')
            ->post(route('support.reply-drafts.send', [$ticket, $draft]), [
                'approval_reference' => 'Approved in support review',
                'confirm_send' => '1',
            ])
            ->assertRedirect(route('support.show', $ticket))
            ->assertSessionHas('success');
    }

    public function test_draft_creation_conflict_returns_to_the_ticket_with_a_friendly_error(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket();
        $mock = $this->mock(SupportReplyService::class);
        $mock->shouldReceive('saveDraft')
            ->once()
            ->with(
                Mockery::on(fn (SupportTicket $candidate): bool => $candidate->is($ticket)),
                [
                    'subject' => 'Re: App support request',
                    'body' => 'A second draft must not be created.',
                ],
                $admin->id
            )
            ->andThrow(new \LogicException('An unfinished customer reply already blocks another draft.'));

        $this->actingAs($admin, 'admin')
            ->post(route('support.reply-drafts.store', $ticket), [
                'subject' => 'Re: App support request',
                'body' => 'A second draft must not be created.',
            ])
            ->assertRedirect(route('support.show', $ticket))
            ->assertSessionHas('error', 'An unfinished customer reply already blocks another draft.')
            ->assertSessionHasInput('body', 'A second draft must not be created.');

        $this->assertSame(0, $ticket->messages()->count());
    }

    public function test_ticket_page_exposes_accessible_controls_history_and_disabled_sending_state(): void
    {
        $ticket = $this->ticket([
            'resolution_note' => 'A previously recorded reason.',
            'requester_name' => null,
            'submission_context_type' => 'restaurant_suggestion',
            'submission_context_id' => 'restaurant-42',
            'submission_context_label' => 'Example Restaurant',
            'submitted_barcode' => '9400000123456',
        ]);
        $this->message($ticket, ['direction' => 'inbound', 'body' => 'The scanner closes unexpectedly.']);
        $this->message($ticket, ['direction' => 'outbound_draft', 'body' => 'Thanks, we are investigating.']);

        $response = $this->actingAs($this->admin(), 'admin')
            ->get(route('support.show', $ticket));

        $response->assertOk()
            ->assertSee('Handoffs on this page are proposals, not workflow actions.')
            ->assertSee('Submitted app context')
            ->assertSee('(unverified)')
            ->assertSee('Restaurant Suggestion')
            ->assertSee('restaurant-42')
            ->assertSee('Example Restaurant')
            ->assertSee('9400000123456')
            ->assertSee('It is not an admin-approved record link')
            ->assertSee('Unknown sender')
            ->assertSee('for="ticket-status"', false)
            ->assertSee('for="resolution-note"', false)
            ->assertSee('Resolved and No Action are unavailable')
            ->assertSee('Reply composition unavailable')
            ->assertSee('Review, send, or discard the existing unsent draft before preparing another reply.')
            ->assertDontSee('action="'.route('support.reply-drafts.store', $ticket).'"', false)
            ->assertSee('name="confirm_send"', false)
            ->assertSee('I reviewed this exact recipient, subject and message and approve sending it now.')
            ->assertSee('name="confirm_discard"', false)
            ->assertSee('I confirm this exact draft is unsent and should be discarded.')
            ->assertSee('Sending is disabled.')
            ->assertSee('disabled', false)
            ->assertSee('Audit history')
            ->assertSee('A previously recorded reason.');
        $this->assertMatchesRegularExpression(
            '/<dt>Requester<\/dt>\s*<dd>Unknown sender<\/dd>/',
            $response->getContent()
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<dt>Requester<\/dt>\s*<dd>Example Restaurant<\/dd>/',
            $response->getContent()
        );
    }

    public function test_quarantined_and_unreviewed_attachments_cannot_be_downloaded(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket();
        $message = $this->message($ticket);
        $quarantined = $this->attachment($message, [
            'path' => 'support/quarantined-file.bin',
            'original_name' => 'suspicious-file.bin',
            'security_status' => 'quarantined',
        ]);
        $pending = $this->attachment($message, [
            'path' => 'support/pending-document.pdf',
            'original_name' => 'pending-document.pdf',
            'security_status' => 'pending_review',
        ]);
        $response = $this->actingAs($admin, 'admin')->get(route('support.show', $ticket));

        $response->assertOk()
            ->assertSee('Security: Quarantined')
            ->assertSee('Security: Pending Review')
            ->assertSee('Download unavailable pending secure review')
            ->assertDontSee(route('support.attachments.show', [$ticket, $quarantined]), false)
            ->assertDontSee(route('support.attachments.show', [$ticket, $pending]), false);

        $this->actingAs($admin, 'admin')
            ->get(route('support.attachments.show', [$ticket, $quarantined]))
            ->assertForbidden();
        $this->actingAs($admin, 'admin')
            ->get(route('support.attachments.show', [$ticket, $pending]))
            ->assertForbidden();
    }

    public function test_delivery_reconciliation_requires_confirmation_and_never_offers_resend(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket();
        $draft = $this->message($ticket, ['direction' => 'outbound_draft']);
        $delivery = SupportDelivery::create([
            'support_ticket_id' => $ticket->id,
            'support_message_id' => $draft->id,
            'kind' => 'customer_reply',
            'recipient_address' => $ticket->requester_email,
            'status' => 'uncertain',
        ]);

        $page = $this->actingAs($admin, 'admin')->get(route('support.show', $ticket));
        $page->assertOk()
            ->assertSee('Manually reconcile this delivery')
            ->assertSee('This action never resends email.')
            ->assertSee('Confirmed sent')
            ->assertSee('Confirmed not sent')
            ->assertSee('name="confirm_reconciliation"', false)
            ->assertDontSee('Resend');

        $mock = $this->mock(SupportReplyService::class);
        $mock->shouldNotReceive('reconcileDelivery');
        $this->actingAs($admin, 'admin')
            ->post(route('support.deliveries.reconcile', [$ticket, $delivery]), [
                'outcome' => 'confirmed_not_sent',
                'reconciliation_reason' => 'Mail server logs show no acceptance.',
            ])
            ->assertSessionHasErrors('confirm_reconciliation');

        $this->releaseServiceMock(SupportReplyService::class);
        $mock = $this->mock(SupportReplyService::class);
        $mock->shouldReceive('reconcileDelivery')
            ->once()
            ->with(
                Mockery::on(fn (SupportDelivery $candidate): bool => $candidate->is($delivery)),
                'confirmed_not_sent',
                'Mail server logs show no acceptance.',
                $admin->id
            )
            ->andReturn($delivery->forceFill([
                'status' => 'failed',
                'reconciliation_outcome' => 'confirmed_not_sent',
            ]));

        $this->actingAs($admin, 'admin')
            ->post(route('support.deliveries.reconcile', [$ticket, $delivery]), [
                'outcome' => 'confirmed_not_sent',
                'reconciliation_reason' => 'Mail server logs show no acceptance.',
                'confirm_reconciliation' => '1',
            ])
            ->assertRedirect(route('support.show', $ticket))
            ->assertSessionHas('success', 'The delivery was reconciled without sending or retrying any email.');
    }

    public function test_internal_notification_delivery_is_audit_only_and_does_not_block_composition(): void
    {
        $ticket = $this->ticket();
        $message = $this->message($ticket, [
            'direction' => 'internal_notification',
            'from_address' => 'mailer@halalkiwi.com',
            'to_address' => 'appsupport@halalkiwi.com',
        ]);
        $delivery = SupportDelivery::create([
            'support_ticket_id' => $ticket->id,
            'support_message_id' => $message->id,
            'kind' => 'internal_notification',
            'recipient_address' => 'appsupport@halalkiwi.com',
            'status' => 'uncertain',
        ]);

        $response = $this->actingAs($this->admin(), 'admin')
            ->get(route('support.show', $ticket));

        $response->assertOk()
            ->assertSee('Audited internal copy: mailer@halalkiwi.com to appsupport@halalkiwi.com')
            ->assertSee('Kind: Internal Notification')
            ->assertSee('Delivery: Uncertain')
            ->assertDontSee(route('support.deliveries.reconcile', [$ticket, $delivery]), false)
            ->assertDontSee('Manually reconcile this delivery')
            ->assertSee('action="'.route('support.reply-drafts.store', $ticket).'"', false)
            ->assertDontSee('Reply composition unavailable');

        $this->actingAs($this->admin([
            'email' => 'second-support-admin@example.com',
        ]), 'admin')
            ->post(route('support.deliveries.reconcile', [$ticket, $delivery]), [
                'outcome' => 'confirmed_sent',
                'reconciliation_reason' => 'This should never be accepted for an internal notification.',
                'confirm_reconciliation' => '1',
            ])
            ->assertNotFound();

        $this->assertSame('uncertain', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->reconciliation_outcome);
    }

    public function test_recent_sending_customer_delivery_waits_for_smtp_safety_window(): void
    {
        $ticket = $this->ticket();
        $draft = $this->message($ticket, ['direction' => 'outbound_draft']);
        $delivery = SupportDelivery::create([
            'support_ticket_id' => $ticket->id,
            'support_message_id' => $draft->id,
            'kind' => 'customer_reply',
            'recipient_address' => $ticket->requester_email,
            'status' => 'sending',
            'last_attempted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin(), 'admin')
            ->get(route('support.show', $ticket));

        $response->assertOk()
            ->assertSee('Kind: Customer Reply')
            ->assertSee('Manual reconciliation is temporarily unavailable during the SMTP safety window.')
            ->assertDontSee(route('support.deliveries.reconcile', [$ticket, $delivery]), false)
            ->assertDontSee('Manually reconcile this delivery');
    }

    private function admin(array $overrides = []): Admin
    {
        return Admin::create(array_merge([
            'name' => 'Support Administrator',
            'email' => 'support-admin@example.com',
            'password' => Hash::make('ExamplePassword123'),
            'role_id' => 1,
            'status' => 1,
        ], $overrides));
    }

    private function ticket(array $overrides = []): SupportTicket
    {
        return SupportTicket::create(array_merge([
            'reference' => 'HK-SUP-000100',
            'mailbox_address' => 'appsupport@halalkiwi.com',
            'source' => 'app_form',
            'requester_name' => 'Example User',
            'requester_email' => 'user@example.com',
            'normalized_requester_email' => 'user@example.com',
            'subject' => 'App support request',
            'summary' => 'A concise request summary.',
            'category' => 'general_inquiry',
            'priority' => 'normal',
            'status' => 'new',
            'received_at' => now(),
        ], $overrides));
    }

    private function message(SupportTicket $ticket, array $overrides = []): SupportMessage
    {
        return SupportMessage::create(array_merge([
            'support_ticket_id' => $ticket->id,
            'direction' => 'inbound',
            'from_name' => 'Example User',
            'from_address' => 'user@example.com',
            'to_address' => 'appsupport@halalkiwi.com',
            'subject' => 'App support request',
            'body' => 'Please help with the app.',
            'received_at' => now(),
        ], $overrides));
    }

    private function attachment(SupportMessage $message, array $overrides = []): SupportAttachment
    {
        return SupportAttachment::create(array_merge([
            'support_ticket_id' => $message->support_ticket_id,
            'support_message_id' => $message->id,
            'disk' => 'local',
            'path' => 'support/example.pdf',
            'original_name' => 'example.pdf',
            'mime_type' => 'application/pdf',
            'security_status' => 'pending_review',
            'size_bytes' => 1024,
            'sha256' => hash('sha256', uniqid('', true)),
        ], $overrides));
    }

    private function releaseServiceMock(string $abstract): void
    {
        $this->app->forgetInstance($abstract);
    }

    private function createSchema(): void
    {
        Schema::create('admins', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedBigInteger('role_id')->default(1);
            $table->boolean('status')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('mailbox_address');
            $table->string('source')->default('mailbox');
            $table->string('requester_name')->nullable();
            $table->string('requester_email');
            $table->string('normalized_requester_email');
            $table->string('subject');
            $table->text('summary')->nullable();
            $table->string('category')->default('general_inquiry');
            $table->string('priority')->default('normal');
            $table->string('status')->default('new');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('submission_context_type')->nullable();
            $table->string('submission_context_id')->nullable();
            $table->string('submission_context_label')->nullable();
            $table->string('submitted_barcode')->nullable();
            $table->string('linked_entity_type')->nullable();
            $table->string('linked_entity_id')->nullable();
            $table->string('linked_barcode')->nullable();
            $table->string('proposed_handoff')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('support_ticket_id');
            $table->string('direction');
            $table->text('message_id')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_address');
            $table->string('to_address');
            $table->string('subject');
            $table->text('body');
            $table->text('in_reply_to')->nullable();
            $table->json('references_header')->nullable();
            $table->json('raw_headers')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('approval_reference')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('drafted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('support_ticket_id');
            $table->unsignedBigInteger('support_message_id');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->string('security_status')->default('pending_review');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('sha256')->nullable();
            $table->timestamps();
        });

        Schema::create('support_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('support_ticket_id');
            $table->unsignedBigInteger('support_message_id')->nullable();
            $table->string('kind')->default('customer_reply');
            $table->string('recipient_address');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('approval_reference')->nullable();
            $table->string('reconciliation_outcome')->nullable();
            $table->text('reconciliation_reason')->nullable();
            $table->unsignedBigInteger('reconciled_by')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('uncertain_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_ticket_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('support_ticket_id');
            $table->string('event_type');
            $table->unsignedBigInteger('actor_admin_id')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->text('details')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('Barcode');
            $table->integer('halal_status');
        });
        Schema::create('prioritisation_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('barcode');
            $table->string('status');
        });
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('response')->nullable();
        });
    }
}
