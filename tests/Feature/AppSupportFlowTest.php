<?php

namespace Tests\Feature;

use App\Admin;
use App\Mail\ContactUsEmail;
use App\Mail\SupportReplyEmail;
use App\Models\SupportAttachment;
use App\Models\SupportDelivery;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Services\SupportReplyService;
use App\Services\SupportTicketService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class AppSupportFlowTest extends TestCase
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
            'support.mailbox_address' => 'appsupport@halalkiwi.com',
            'support.mail_enabled' => false,
        ]);
        DB::purge('sqlite');
        $this->createTables();
        Storage::fake('local');
        Mail::fake();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_contact_submission_is_durable_private_and_idempotent_without_touching_product_flows(): void
    {
        foreach (['products', 'prioritisation_requests', 'brands', 'brand_communications', 'brand_outreach_batches'] as $table) {
            Schema::create($table, fn (Blueprint $blueprint) => $blueprint->id());
        }
        $uuid = '85a5c5b6-e7db-4a2f-8891-01a5c89067bb';
        $payload = [
            'name' => 'Amina',
            'email' => 'amina@example.com',
            'subject' => 'Scanner issue',
            'body' => 'The scanner closes after opening.',
            'category' => 'bug_report',
            'submission_uuid' => $uuid,
            'platform' => 'android',
            'context_type' => 'product',
            'context_id' => 'catalogue-42',
            'barcode' => '9400000000001',
            'attachment' => UploadedFile::fake()->image('screen.png'),
        ];

        $first = $this->withHeader('X-API-Key', self::API_KEY)->post('/api/contact-us', $payload);
        $first->assertOk()->assertJson(['duplicate' => false]);
        $reference = $first->json('reference');
        $second = $this->withHeader('X-API-Key', self::API_KEY)->post('/api/contact-us', $payload);
        $second->assertOk()->assertJson(['duplicate' => true, 'reference' => $reference]);

        $this->assertSame(1, SupportTicket::count());
        $this->assertSame(1, SupportMessage::where('direction', 'inbound')->count());
        $this->assertSame(1, SupportTicket::first()->attachments()->count());
        $this->assertSame('product', SupportTicket::first()->submission_context_type);
        $this->assertSame('catalogue-42', SupportTicket::first()->submission_context_id);
        $this->assertSame('9400000000001', SupportTicket::first()->submitted_barcode);
        $this->assertNull(SupportTicket::first()->linked_entity_type);
        $this->assertNull(SupportTicket::first()->linked_entity_id);
        $this->assertNull(SupportTicket::first()->linked_barcode);
        $this->assertNull(SupportTicket::first()->proposed_handoff);
        $this->assertSame(1, SupportDelivery::where('kind', 'internal_notification')->count());
        Mail::assertSent(ContactUsEmail::class, 1);
        Mail::assertSent(ContactUsEmail::class, function (ContactUsEmail $mail) {
            $mail->build();

            return $mail->attachments === [];
        });
        foreach (['products', 'prioritisation_requests', 'brands', 'brand_communications', 'brand_outreach_batches'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "Support capture mutated {$table}");
        }
    }

    public function test_legacy_contact_payload_without_uuid_is_still_accepted(): void
    {
        $payload = $this->payload();
        unset($payload['submission_uuid']);

        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/contact-us', $payload)
            ->assertOk()
            ->assertJson(['duplicate' => false]);

        $this->assertNull(SupportTicket::first()->client_submission_uuid);
    }

    public function test_attachment_quota_blocks_new_storage_without_orphan_ticket_but_allows_exact_replay(): void
    {
        config([
            'support.attachment_daily_per_email_count' => 1,
            'support.attachment_daily_per_email_bytes' => 10 * 1024 * 1024,
            'support.attachment_min_free_bytes' => 1,
        ]);
        $payload = array_merge($this->payload(), [
            'attachment' => UploadedFile::fake()->image('screen.png'),
        ]);
        $this->withHeader('X-API-Key', self::API_KEY)->post('/api/contact-us', $payload)->assertOk();
        $this->withHeader('X-API-Key', self::API_KEY)->post('/api/contact-us', $payload)
            ->assertOk()
            ->assertJson(['duplicate' => true]);

        $second = array_merge($payload, [
            'submission_uuid' => '85a5c5b6-e7db-4a2f-8891-01a5c89067cc',
            'attachment' => UploadedFile::fake()->image('another.png'),
        ]);
        $this->withHeader('X-API-Key', self::API_KEY)->post('/api/contact-us', $second)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachment');

        // Quota is checked under the shared intake lock before a new ticket or
        // attachment is persisted.
        $this->assertSame(1, SupportTicket::count());
        $this->assertSame(1, SupportAttachment::count());
        Mail::assertSent(ContactUsEmail::class, 1);
    }

    public function test_attachment_disk_reserve_guard_fails_before_file_storage(): void
    {
        config(['support.attachment_min_free_bytes' => PHP_INT_MAX]);

        $this->withHeader('X-API-Key', self::API_KEY)->post('/api/contact-us', array_merge(
            $this->payload(),
            ['attachment' => UploadedFile::fake()->image('screen.png')],
        ))->assertUnprocessable()->assertJsonValidationErrors('attachment');

        $this->assertSame(0, SupportTicket::count());
        $this->assertSame(0, SupportAttachment::count());
        Mail::assertNothingSent();
    }

    public function test_product_name_is_preserved_as_unverified_context_not_requester_identity(): void
    {
        $payload = array_merge($this->payload(), [
            'name' => 'Example Chocolate Bar',
            'category' => 'product_issue',
        ]);

        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/contact-us', $payload)
            ->assertOk();

        $ticket = SupportTicket::firstOrFail();
        $this->assertNull($ticket->requester_name);
        $this->assertSame('Example Chocolate Bar', $ticket->submission_context_label);
        Mail::assertSent(ContactUsEmail::class, function (ContactUsEmail $mail) {
            $mail->build();

            return $mail->hasReplyTo('amina@example.com', 'Halal Kiwi app user');
        });
    }

    public function test_entity_context_keeps_name_unverified_even_if_user_changes_category(): void
    {
        $result = app(SupportTicketService::class)->captureAppSubmission(array_merge($this->payload(), [
            'name' => 'Example Restaurant',
            'category' => 'general_inquiry',
            'context_type' => 'restaurant',
        ]));

        $this->assertNull($result['ticket']->requester_name);
        $this->assertSame('Example Restaurant', $result['ticket']->submission_context_label);
    }

    public function test_explicit_requester_name_is_kept_separate_from_product_context_label(): void
    {
        $result = app(SupportTicketService::class)->captureAppSubmission(array_merge($this->payload(), [
            'name' => 'Example Chocolate Bar',
            'requester_name' => 'Amina',
            'category' => 'product_issue',
        ]));

        $this->assertSame('Amina', $result['ticket']->requester_name);
        $this->assertSame('Example Chocolate Bar', $result['ticket']->submission_context_label);
    }

    public function test_all_flutter_context_values_are_accepted_as_untrusted_submission_hints_only(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach ([
            'app',
            'advertising',
            'business_network',
            'restaurant_suggestion',
            'muslim_guide',
        ] as $index => $contextType) {
            $payload = array_merge($this->payload(), [
                'submission_uuid' => sprintf('85a5c5b6-e7db-4a2f-8891-%012d', $index + 2),
                'context_type' => $contextType,
                'context_id' => 'source-'.$index,
            ]);
            $this->withHeader('X-API-Key', self::API_KEY)
                ->postJson('/api/contact-us', $payload)
                ->assertOk();
        }

        $this->assertSame(5, SupportTicket::count());
        $this->assertFalse(SupportTicket::whereNotNull('linked_entity_type')->exists());
        $this->assertFalse(SupportTicket::whereNotNull('linked_entity_id')->exists());
        $this->assertFalse(SupportTicket::whereNotNull('linked_barcode')->exists());
        $this->assertFalse(SupportTicket::whereNotNull('proposed_handoff')->exists());
    }

    public function test_uuid_reuse_with_changed_sender_or_content_is_a_conflict_and_does_not_disclose_reference(): void
    {
        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/contact-us', $this->payload())
            ->assertOk();

        $response = $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/contact-us', array_merge($this->payload(), ['email' => 'attacker@example.com']));

        $response->assertConflict();
        $this->assertStringNotContainsString('HK-SUP-', $response->getContent());
        $this->assertSame(1, SupportTicket::count());
    }

    public function test_client_cannot_self_classify_a_submission_as_no_action(): void
    {
        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/contact-us', array_merge($this->payload(), ['category' => 'no_action']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');

        $this->assertSame(0, SupportTicket::count());
    }

    public function test_conflicting_submission_uuid_aliases_are_rejected(): void
    {
        $this->withHeader('X-API-Key', self::API_KEY)->postJson('/api/contact-us', array_merge(
            $this->payload(),
            ['client_submission_uuid' => '35a5c5b6-e7db-4a2f-8891-01a5c8906711'],
        ))->assertUnprocessable()->assertJsonValidationErrors('client_submission_uuid');

        $this->assertSame(0, SupportTicket::count());
    }

    public function test_mailbox_capture_is_message_id_idempotent_and_links_by_reference_then_thread_headers(): void
    {
        $service = app(SupportTicketService::class);
        $first = $service->captureMailboxMessage($this->mailboxMessage('<first@example.com>', 'Need help'));
        $duplicate = $service->captureMailboxMessage($this->mailboxMessage(' <FIRST@EXAMPLE.COM> ', 'Duplicate'));
        $byReference = $service->captureMailboxMessage($this->mailboxMessage(
            '<reply@example.com>',
            "Re: Need help [{$first['ticket']->reference}]",
        ));
        $byParent = $service->captureMailboxMessage(array_merge(
            $this->mailboxMessage('<third@example.com>', 'Re: Need help'),
            ['in_reply_to' => '<reply@example.com>'],
        ));

        $this->assertSame($first['ticket']->id, $duplicate['ticket']->id);
        $this->assertFalse($duplicate['created']);
        $this->assertSame($first['ticket']->id, $byReference['ticket']->id);
        $this->assertSame($first['ticket']->id, $byParent['ticket']->id);
        $this->assertSame(1, SupportTicket::count());
        $this->assertSame(3, SupportMessage::count());
    }

    public function test_internal_contact_notification_is_audited_without_becoming_a_second_customer_inbound(): void
    {
        $service = app(SupportTicketService::class);
        $submission = $service->captureAppSubmission($this->payload());
        $result = $service->captureMailboxMessage(array_merge(
            $this->mailboxMessage(
                '<'.ContactUsEmail::notificationMessageIdFor($submission['message']->id).'>',
                "[{$submission['ticket']->reference}] Internal notification",
            ),
            [
                'from_address' => 'hello@example.com',
                'envelope_from' => 'hello@example.com',
                'authenticated_internal' => true,
                'support_notification' => 'internal',
                'support_reference' => $submission['ticket']->reference,
                'support_message_id' => (string) $submission['message']->id,
                'support_submission_uuid' => $this->payload()['submission_uuid'],
            ],
        ));

        $this->assertTrue($result['ignored']);
        $this->assertSame($submission['ticket']->id, $result['ticket']->id);
        $this->assertSame(2, SupportMessage::count());
        $this->assertSame('internal_notification', $result['message']->direction);
        $this->assertSame(
            '<'.ContactUsEmail::notificationMessageIdFor($submission['message']->id).'>',
            $result['message']->message_id,
        );
        $this->assertSame('Mailbox message body.', $result['message']->body);
    }

    public function test_forged_internal_markers_are_captured_not_silently_dropped(): void
    {
        $service = app(SupportTicketService::class);
        $submission = $service->captureAppSubmission($this->payload());
        $result = $service->captureMailboxMessage(array_merge(
            $this->mailboxMessage('<forged@example.com>', "[{$submission['ticket']->reference}] Forged"),
            [
                'support_notification' => 'internal',
                'support_reference' => $submission['ticket']->reference,
                'support_submission_uuid' => $this->payload()['submission_uuid'],
            ],
        ));

        $this->assertFalse($result['ignored']);
        $this->assertTrue($result['created']);
        $this->assertSame(2, SupportMessage::count());
    }

    public function test_malformed_message_ids_are_rejected_before_capture_or_threading(): void
    {
        $service = app(SupportTicketService::class);
        foreach ([
            'two@@example.com',
            'contains space@example.com',
            "newline@example.com\r\nBcc: attacker@example.com",
            '<bad..dots@example.com>',
        ] as $messageId) {
            $this->assertNull($service->normalizeMessageId($messageId));
        }

        $this->expectException(InvalidArgumentException::class);
        $service->captureMailboxMessage($this->mailboxMessage('two@@example.com', 'Bad'));
    }

    public function test_capture_fails_closed_for_any_other_configured_or_input_mailbox(): void
    {
        $service = app(SupportTicketService::class);
        config(['support.mailbox_address' => 'products@halalkiwi.com']);
        $this->expectException(InvalidArgumentException::class);

        $service->captureMailboxMessage($this->mailboxMessage('<blocked@example.com>', 'Blocked'));
    }

    public function test_mailbox_capture_rejects_a_mislabeled_message_delivered_to_another_mailbox(): void
    {
        $message = $this->mailboxMessage('<products-message@example.com>', 'Wrong mailbox');
        $message['delivered_to'] = '"appsupport@halalkiwi.com" <products@halalkiwi.com>';

        $this->expectException(InvalidArgumentException::class);
        app(SupportTicketService::class)->captureMailboxMessage($message);
    }

    public function test_mailbox_capture_rejects_malformed_sender_and_oversized_body(): void
    {
        $service = app(SupportTicketService::class);
        try {
            $service->captureMailboxMessage(array_merge(
                $this->mailboxMessage('<invalid-sender@example.com>', 'Bad sender'),
                ['from_address' => 'not-an-email'],
            ));
            $this->fail('Invalid sender should be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, SupportTicket::count());
        }

        config(['support.mailbox_body_max_bytes' => 10]);
        $this->expectException(InvalidArgumentException::class);
        $service->captureMailboxMessage(array_merge(
            $this->mailboxMessage('<large-body@example.com>', 'Too large'),
            ['body' => str_repeat('x', 11)],
        ));
    }

    public function test_reply_requires_explicit_approval_and_dedicated_support_transport(): void
    {
        $ticket = app(SupportTicketService::class)->captureMailboxMessage(
            $this->mailboxMessage('<customer-message@example.com>', 'Question')
        )['ticket'];
        $draft = app(SupportReplyService::class)->saveDraft($ticket, [
            'body' => 'Thanks, we are looking into this.',
        ]);

        $this->expectException(LogicException::class);
        app(SupportReplyService::class)->sendApprovedDraft($draft, 'approved-in-chat-1');
    }

    public function test_approved_reply_is_threaded_audited_and_never_resent(): void
    {
        config([
            'support.mail_enabled' => true,
            'support.mailer' => 'support',
            'mail.mailers.support.host' => 'mail.halalkiwi.com',
            'mail.mailers.support.username' => 'appsupport@halalkiwi.com',
        ]);
        $ticket = app(SupportTicketService::class)->captureMailboxMessage(array_merge(
            $this->mailboxMessage('<customer-message@example.com>', 'Question'),
            ['references' => ['<older@example.com>']],
        ))['ticket'];
        $draft = app(SupportReplyService::class)->saveDraft($ticket, ['body' => 'Our reply.']);
        $first = app(SupportReplyService::class)->sendApprovedDraft($draft, 'approved-in-chat-2');
        $second = app(SupportReplyService::class)->sendApprovedDraft($draft->fresh(), 'different-later-reference');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('sent', $first->fresh()->status);
        $this->assertSame('approved-in-chat-2', $first->fresh()->approval_reference);
        Mail::assertSent(SupportReplyEmail::class, 1);
        Mail::assertSent(SupportReplyEmail::class, function (SupportReplyEmail $mail) {
            $headers = $mail->headers();
            $mail->build();

            return $headers->messageId === 'support-reply-'.$mail->supportMessage->id.'@halalkiwi.com'
                && ($headers->text['In-Reply-To'] ?? null) === '<customer-message@example.com>'
                && in_array('<customer-message@example.com>', $headers->references, true)
                && $mail->hasFrom('appsupport@halalkiwi.com')
                && $mail->hasReplyTo('appsupport@halalkiwi.com');
        });
    }

    public function test_existing_pending_reply_delivery_is_claimed_once_on_safe_replay(): void
    {
        config([
            'support.mail_enabled' => true,
            'support.mailer' => 'support',
            'mail.mailers.support.url' => null,
            'mail.mailers.support.host' => 'mail.halalkiwi.com',
            'mail.mailers.support.username' => 'appsupport@halalkiwi.com',
        ]);
        $ticket = app(SupportTicketService::class)->captureAppSubmission($this->payload())['ticket'];
        $draft = app(SupportReplyService::class)->saveDraft($ticket, ['body' => 'Pending reply']);
        $delivery = SupportDelivery::create([
            'support_ticket_id' => $ticket->id,
            'support_message_id' => $draft->id,
            'kind' => 'customer_reply',
            'event_key' => hash('sha256', "support-reply:{$draft->id}"),
            'event_reference' => "support-reply:{$draft->id}",
            'mailer' => 'support',
            'recipient_address' => $draft->to_address,
            'normalized_recipient_address' => $draft->to_address,
            'status' => 'pending',
            'approval_reference' => 'original-approval',
            'transport_message_id' => "<support-reply-{$draft->id}@halalkiwi.com>",
            'transport_message_id_hash' => hash('sha256', "<support-reply-{$draft->id}@halalkiwi.com>"),
        ]);

        $result = app(SupportReplyService::class)->sendApprovedDraft($draft, 'later-click');
        $this->assertSame($delivery->id, $result->id);
        $this->assertSame('sent', $result->status);
        $this->assertSame(1, $result->attempts);
        $this->assertSame('original-approval', $draft->fresh()->approval_reference);
        Mail::assertSent(SupportReplyEmail::class, 1);
        $this->assertSame($result->id, app(SupportReplyService::class)
            ->sendApprovedDraft($draft->fresh(), 'third-click')->id);
        Mail::assertSent(SupportReplyEmail::class, 1);
    }

    public function test_support_mail_url_override_fails_closed(): void
    {
        config([
            'support.mail_enabled' => true,
            'support.mailer' => 'support',
            'mail.mailers.support.url' => 'smtp://products:secret@example.test',
            'mail.mailers.support.host' => 'mail.halalkiwi.com',
            'mail.mailers.support.username' => 'appsupport@halalkiwi.com',
        ]);
        $ticket = app(SupportTicketService::class)->captureAppSubmission($this->payload())['ticket'];
        $draft = app(SupportReplyService::class)->saveDraft($ticket, ['body' => 'Do not send']);

        $this->expectException(LogicException::class);
        app(SupportReplyService::class)->sendApprovedDraft($draft, 'approval');
    }

    public function test_uncertain_delivery_can_be_reconciled_sent_without_resending(): void
    {
        $admin = Admin::create(['name' => 'Reviewer', 'email' => 'reviewer@example.com', 'password' => 'x']);
        $ticket = app(SupportTicketService::class)->captureAppSubmission($this->payload())['ticket'];
        $draft = app(SupportReplyService::class)->saveDraft($ticket, ['body' => 'Uncertain reply']);
        $delivery = $this->customerDelivery($draft, 'uncertain');

        $result = app(SupportReplyService::class)->reconcileDelivery(
            $delivery,
            'confirmed_sent',
            'Verified in the appsupport Sent folder.',
            $admin->id,
        );

        $this->assertSame('sent', $result->status);
        $this->assertSame('confirmed_sent', $result->reconciliation_outcome);
        $this->assertSame('outbound', $draft->fresh()->direction);
        Mail::assertNothingSent();
        $this->assertSame($result->id, app(SupportReplyService::class)->reconcileDelivery(
            $result,
            'confirmed_sent',
            'Repeated confirmation.',
            $admin->id,
        )->id);
    }

    public function test_confirmed_not_sent_delivery_allows_audited_draft_discard_and_replacement(): void
    {
        config(['support.delivery_reconcile_after_seconds' => 300]);
        $admin = Admin::create(['name' => 'Reviewer', 'email' => 'reviewer@example.com', 'password' => 'x']);
        $ticket = app(SupportTicketService::class)->captureAppSubmission($this->payload())['ticket'];
        $replies = app(SupportReplyService::class);
        $draft = $replies->saveDraft($ticket, ['body' => 'Failed reply']);
        $delivery = $this->customerDelivery($draft, 'sending');
        $delivery->update(['last_attempted_at' => now()->subSeconds(301)]);

        $result = $replies->reconcileDelivery(
            $delivery,
            'confirmed_not_sent',
            'SMTP and Sent folder confirm rejection before acceptance.',
            $admin->id,
        );
        $this->assertSame('failed', $result->status);
        $this->assertSame('outbound_draft', $draft->fresh()->direction);
        $discarded = $replies->discardDraft($draft->fresh(), 'Replace with a corrected reply.', $admin->id);
        $this->assertSame('discarded_draft', $discarded->direction);
        $replacement = $replies->saveDraft($ticket->fresh(), ['body' => 'Corrected reply']);
        $this->assertSame('outbound_draft', $replacement->direction);
        Mail::assertNothingSent();
    }

    public function test_second_live_draft_is_blocked(): void
    {
        $ticket = app(SupportTicketService::class)->captureAppSubmission($this->payload())['ticket'];
        $replies = app(SupportReplyService::class);
        $replies->saveDraft($ticket, ['body' => 'First draft']);

        $this->expectException(LogicException::class);
        $replies->saveDraft($ticket, ['body' => 'Second draft']);
    }

    public function test_resolved_requires_note_and_blocks_an_outstanding_customer_reply(): void
    {
        $service = app(SupportTicketService::class);
        $ticket = $service->captureAppSubmission($this->payload())['ticket'];
        app(SupportReplyService::class)->saveDraft($ticket, ['body' => 'Draft reply']);

        try {
            $service->triage($ticket->fresh(), ['status' => 'resolved', 'resolution_note' => 'Done']);
            $this->fail('Expected outstanding draft validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        app(SupportReplyService::class)->discardDraft(
            $ticket->messages()->where('direction', 'outbound_draft')->firstOrFail(),
            'No reply is required.',
        );
        $service->triage($ticket->fresh(), ['status' => 'no_action', 'resolution_note' => 'Duplicate/spam.']);
        $this->assertSame('no_action', $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->resolved_at);
    }

    public function test_unsent_draft_can_be_audited_and_discarded_before_resolution(): void
    {
        $tickets = app(SupportTicketService::class);
        $replies = app(SupportReplyService::class);
        $ticket = $tickets->captureAppSubmission($this->payload())['ticket'];
        $draft = $replies->saveDraft($ticket, ['body' => 'Mistaken draft']);

        $discarded = $replies->discardDraft($draft, 'Draft was prepared for the wrong issue.');
        $this->assertSame('discarded_draft', $discarded->direction);
        $this->assertSame($discarded->id, $replies->discardDraft($discarded, 'Repeated click')->id);

        $resolved = $tickets->triage($ticket->fresh(), [
            'status' => 'resolved',
            'resolution_note' => 'Resolved without a customer reply.',
        ]);
        $this->assertSame('resolved', $resolved->status);
        $this->assertDatabaseHas('support_ticket_events', [
            'support_ticket_id' => $ticket->id,
            'event_type' => 'reply_draft_discarded',
        ]);
    }

    public function test_new_inbound_reopens_a_closed_ticket_but_a_duplicate_does_not(): void
    {
        $service = app(SupportTicketService::class);
        $first = $service->captureMailboxMessage($this->mailboxMessage('<closed@example.com>', 'Issue'));
        $service->triage($first['ticket'], [
            'status' => 'resolved',
            'resolution_note' => 'Original issue resolved.',
        ]);

        $duplicate = $service->captureMailboxMessage($this->mailboxMessage('<closed@example.com>', 'Duplicate'));
        $this->assertFalse($duplicate['created']);
        $this->assertSame('resolved', $first['ticket']->fresh()->status);

        $reply = $service->captureMailboxMessage(array_merge(
            $this->mailboxMessage('<reopen@example.com>', "Re: Issue [{$first['ticket']->reference}]"),
            ['in_reply_to' => '<closed@example.com>'],
        ));
        $this->assertTrue($reply['created']);
        $this->assertSame('new', $first['ticket']->fresh()->status);
        $this->assertNull($first['ticket']->fresh()->resolved_at);
        $this->assertDatabaseHas('support_ticket_events', [
            'support_ticket_id' => $first['ticket']->id,
            'event_type' => 'reopened_by_inbound',
        ]);
    }

    public function test_record_email_command_is_preview_only_and_requires_explicit_mailbox_and_cutover_to_record(): void
    {
        $directory = storage_path('framework/testing/support-command');
        File::ensureDirectoryExists($directory);
        $valid = $directory.'/valid.json';
        $missingMailbox = $directory.'/missing-mailbox.json';
        file_put_contents($valid, json_encode($this->mailboxMessage('<command@example.com>', 'Command message')));
        file_put_contents($missingMailbox, json_encode(array_diff_key(
            $this->mailboxMessage('<missing@example.com>', 'Missing mailbox'),
            ['mailbox_address' => true],
        )));

        $this->artisan('support:record-email', ['--input' => $valid])->assertSuccessful();
        $this->assertSame(0, SupportTicket::count());
        $this->artisan('support:record-email', ['--input' => $valid, '--record' => true])->assertFailed();
        $this->artisan('support:record-email', [
            '--input' => $missingMailbox,
            '--record' => true,
            '--since' => now()->subDay()->toIso8601String(),
        ])->assertFailed();
        $this->assertSame(0, SupportTicket::count());

        $this->artisan('support:record-email', [
            '--input' => $valid,
            '--record' => true,
            '--since' => now()->subDay()->toIso8601String(),
        ])->assertSuccessful();
        $this->assertSame(1, SupportTicket::count());

        File::deleteDirectory($directory);
    }

    private function payload(): array
    {
        return [
            'name' => 'Amina',
            'email' => 'amina@example.com',
            'subject' => 'General question',
            'body' => 'Please help.',
            'category' => 'general_inquiry',
            'submission_uuid' => '85a5c5b6-e7db-4a2f-8891-01a5c89067bb',
        ];
    }

    private function mailboxMessage(string $messageId, string $subject): array
    {
        return [
            'mailbox_address' => 'appsupport@halalkiwi.com',
            'delivered_to' => 'Halal Kiwi App Support <appsupport@halalkiwi.com>',
            'message_id' => $messageId,
            'from_name' => 'Customer',
            'from_address' => 'customer@example.com',
            'subject' => $subject,
            'body' => 'Mailbox message body.',
            'received_at' => now()->toIso8601String(),
        ];
    }

    private function customerDelivery(SupportMessage $draft, string $status): SupportDelivery
    {
        return SupportDelivery::create([
            'support_ticket_id' => $draft->support_ticket_id,
            'support_message_id' => $draft->id,
            'kind' => 'customer_reply',
            'event_key' => hash('sha256', "support-reply:{$draft->id}"),
            'event_reference' => "support-reply:{$draft->id}",
            'mailer' => 'support',
            'recipient_address' => $draft->to_address,
            'normalized_recipient_address' => $draft->to_address,
            'status' => $status,
            'approval_reference' => 'approved',
            'transport_message_id' => "<support-reply-{$draft->id}@halalkiwi.com>",
            'transport_message_id_hash' => hash('sha256', "<support-reply-{$draft->id}@halalkiwi.com>"),
            'attempts' => 1,
            'last_attempted_at' => now(),
            'uncertain_at' => $status === 'uncertain' ? now() : null,
        ]);
    }

    private function createTables(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_08_13_000003_create_app_support_tables.php'))->up();
    }
}
