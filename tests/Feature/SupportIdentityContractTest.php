<?php

namespace Tests\Feature;

use App\Mail\ContactUsEmail;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupportIdentityContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'support.mailbox_address' => 'appsupport@halalkiwi.com',
            'mail.from.address' => 'hello@example.com',
        ]);
        DB::purge('sqlite');
        (require database_path('migrations/2026_08_13_000003_create_app_support_tables.php'))->up();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_both_capture_paths_store_a_fixed_width_normalized_email_hash(): void
    {
        $tickets = app(SupportTicketService::class);
        $appTicket = $tickets->captureAppSubmission($this->appPayload([
            'email' => 'Amina@Example.COM',
        ]))['ticket'];
        $mailTicket = $tickets->captureMailboxMessage($this->mailboxPayload(
            '<customer-one@example.com>',
            ['from_address' => 'Customer@Example.COM'],
        ))['ticket'];

        $this->assertSame('amina@example.com', $appTicket->normalized_requester_email);
        $this->assertSame(
            SupportTicket::normalizedRequesterEmailHash('amina@example.com'),
            $appTicket->normalized_requester_email_hash,
        );
        $this->assertSame('customer@example.com', $mailTicket->normalized_requester_email);
        $this->assertSame(
            SupportTicket::normalizedRequesterEmailHash('customer@example.com'),
            $mailTicket->normalized_requester_email_hash,
        );
    }

    public function test_muslim_guide_name_is_requester_but_business_name_is_separate_context(): void
    {
        $tickets = app(SupportTicketService::class);
        $guide = $tickets->captureAppSubmission($this->appPayload([
            'name' => 'Amina',
            'category' => 'other',
            'context_type' => 'muslim_guide',
            'context_id' => 'Muslim Guide Enquiry',
        ]))['ticket'];
        $businessName = str_repeat('Long Business Name ', 7).'Long Business Name';
        $business = $tickets->captureAppSubmission($this->appPayload([
            'name' => 'Musa',
            'category' => 'muslim_business_network',
            'context_type' => 'business_network',
            'context_id' => $businessName,
        ]))['ticket'];

        $this->assertSame('Amina', $guide->requester_name);
        $this->assertNull($guide->submission_context_label);
        $this->assertSame('Musa', $business->requester_name);
        $this->assertSame($businessName, $business->submission_context_id);
        $this->assertSame($businessName, $business->submission_context_label);
    }

    public function test_legacy_submission_internal_notification_has_deterministic_identity_and_is_audited(): void
    {
        $tickets = app(SupportTicketService::class);
        $submission = $tickets->captureAppSubmission($this->appPayload());
        $headers = (new ContactUsEmail($submission['ticket'], $submission['message']))->headers();

        $this->assertSame(
            "support-notification-{$submission['message']->id}@halalkiwi.com",
            $headers->messageId,
        );
        $this->assertSame(
            (string) $submission['message']->id,
            $headers->text['X-Halal-Kiwi-Support-Message-ID'],
        );

        $captured = $tickets->captureMailboxMessage($this->mailboxPayload(
            '<'.$headers->messageId.'>',
            [
                'delivered_to' => [
                    'Halal Kiwi App Support <appsupport@halalkiwi.com>',
                    str_repeat('x', 1500)."\r\n",
                ],
                'from_address' => 'hello@example.com',
                'envelope_from' => 'hello@example.com',
                'authenticated_internal' => true,
                'support_notification' => 'internal',
                'support_reference' => $submission['ticket']->reference,
                'support_message_id' => (string) $submission['message']->id,
                'subject' => "[{$submission['ticket']->reference}] Internal notification",
                'body' => 'Preserved internal notification body.',
            ],
        ));

        $this->assertTrue($captured['ignored']);
        $this->assertSame('internal_notification', $captured['message']->direction);
        $this->assertSame('Preserved internal notification body.', $captured['message']->body);
        $this->assertSame('trusted_internal_notification', $captured['message']->raw_headers['capture_classification']);
        $this->assertTrue($captured['message']->raw_headers['authenticated_internal']);
        $this->assertSame('hello@example.com', $captured['message']->raw_headers['normalized_envelope_from']);
        $this->assertSame($submission['message']->id, $captured['message']->raw_headers['support_message_id']);
        $this->assertNull($captured['message']->raw_headers['support_submission_uuid']);
        $this->assertLessThanOrEqual(1000, strlen($captured['message']->raw_headers['delivered_to'][1]));
        $this->assertSame(2, SupportMessage::count());
        $this->assertDatabaseHas('support_ticket_events', [
            'support_ticket_id' => $submission['ticket']->id,
            'event_type' => 'internal_notification_captured',
        ]);
    }

    public function test_forged_or_mismatched_internal_identity_is_preserved_as_inbound(): void
    {
        $tickets = app(SupportTicketService::class);
        $submission = $tickets->captureAppSubmission($this->appPayload([
            'submission_uuid' => '85a5c5b6-e7db-4a2f-8891-01a5c89067bb',
        ]));
        $expectedId = ContactUsEmail::notificationMessageIdFor($submission['message']->id);
        $captured = $tickets->captureMailboxMessage($this->mailboxPayload(
            '<'.$expectedId.'>',
            [
                'from_address' => 'hello@example.com',
                'envelope_from' => 'hello@example.com',
                'authenticated_internal' => true,
                'support_notification' => 'internal',
                'support_reference' => $submission['ticket']->reference,
                'support_message_id' => (string) $submission['message']->id,
                'support_submission_uuid' => '35a5c5b6-e7db-4a2f-8891-01a5c8906711',
                'subject' => "[{$submission['ticket']->reference}] Forged UUID",
            ],
        ));

        $this->assertFalse($captured['ignored']);
        $this->assertSame('inbound', $captured['message']->direction);
        $this->assertSame($submission['ticket']->id, $captured['ticket']->id);
        $this->assertArrayNotHasKey('capture_classification', $captured['message']->raw_headers);
        $this->assertSame(2, SupportMessage::count());
    }

    private function appPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Amina',
            'email' => 'amina@example.com',
            'subject' => 'Support question',
            'body' => 'Please help.',
            'category' => 'general_inquiry',
        ], $overrides);
    }

    private function mailboxPayload(string $messageId, array $overrides = []): array
    {
        return array_merge([
            'mailbox_address' => 'appsupport@halalkiwi.com',
            'delivered_to' => 'Halal Kiwi App Support <appsupport@halalkiwi.com>',
            'message_id' => $messageId,
            'from_name' => 'Customer',
            'from_address' => 'customer@example.com',
            'subject' => 'Support question',
            'body' => 'Mailbox body.',
            'received_at' => now()->toIso8601String(),
        ], $overrides);
    }
}
