<?php

namespace Tests\Feature;

use App\Models\SupportDelivery;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Services\SupportReplyService;
use App\Services\SupportTicketService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class SupportReplyRaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'support.mailbox_address' => 'appsupport@halalkiwi.com',
            'support.mail_enabled' => false,
            'support.delivery_reconcile_after_seconds' => 1,
        ]);
        DB::purge('sqlite');
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_08_13_000003_create_app_support_tables.php'))->up();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_non_pending_delivery_is_returned_before_current_smtp_configuration_is_required(): void
    {
        $firstDraft = null;
        foreach (['sending', 'uncertain', 'sent', 'failed'] as $status) {
            [, $draft] = $this->ticketAndDraft();
            $firstDraft ??= $draft;
            $delivery = $this->delivery($draft, $status);

            $result = app(SupportReplyService::class)->sendApprovedDraft(
                $draft->fresh(),
                'repeat-status-check',
            );

            $this->assertSame($delivery->id, $result->id);
            $this->assertSame($status, $result->status);
            $this->assertSame(1, $result->attempts);
        }

        Mail::assertNothingSent();

        $this->expectException(InvalidArgumentException::class);
        app(SupportReplyService::class)->sendApprovedDraft($firstDraft->fresh(), '  ');
    }

    public function test_smtp_success_finalizer_cannot_overwrite_confirmed_not_sent_reconciliation(): void
    {
        [, $draft] = $this->ticketAndDraft();
        $delivery = $this->delivery($draft, 'sending');
        $delivery->update([
            'status' => 'failed',
            'failed_at' => now(),
            'reconciliation_outcome' => 'confirmed_not_sent',
            'reconciliation_reason' => 'Verified before the SMTP callback returned.',
            'reconciled_at' => now(),
        ]);
        Log::spy();

        $applied = $this->replyService()->completeSuccess(
            $draft->fresh(),
            $delivery->fresh(),
            'approved-before-send',
        );

        $this->assertFalse($applied);
        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame('confirmed_not_sent', $delivery->reconciliation_outcome);
        $this->assertNull($delivery->sent_at);
        $this->assertSame('outbound_draft', $draft->fresh()->direction);
        $this->assertDatabaseMissing('support_ticket_events', [
            'support_ticket_id' => $draft->support_ticket_id,
            'event_type' => 'reply_sent',
        ]);
        $this->assertDatabaseHas('support_ticket_events', [
            'support_ticket_id' => $draft->support_ticket_id,
            'event_type' => 'reply_delivery_state_conflict',
        ]);
        Log::shouldHaveReceived('critical')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'conflicted')
                && $context['support_delivery_id'] === $delivery->id
                && $context['phase'] === 'smtp_success');
    }

    public function test_smtp_exception_finalizer_cannot_overwrite_confirmed_sent_reconciliation(): void
    {
        [, $draft] = $this->ticketAndDraft();
        $delivery = $this->delivery($draft, 'sending');
        $draft->update(['direction' => 'outbound', 'sent_at' => now()]);
        $delivery->update([
            'status' => 'sent',
            'sent_at' => now(),
            'reconciliation_outcome' => 'confirmed_sent',
            'reconciliation_reason' => 'Verified in the Sent folder.',
            'reconciled_at' => now(),
        ]);
        Log::spy();

        $applied = $this->replyService()->completeException(
            $draft->fresh(),
            $delivery->fresh(),
            new RuntimeException('Late transport exception'),
        );

        $this->assertFalse($applied);
        $delivery->refresh();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame('confirmed_sent', $delivery->reconciliation_outcome);
        $this->assertNull($delivery->uncertain_at);
        $this->assertNull($delivery->error);
        $this->assertSame('outbound', $draft->fresh()->direction);
        $this->assertDatabaseHas('support_ticket_events', [
            'support_ticket_id' => $draft->support_ticket_id,
            'event_type' => 'reply_delivery_state_conflict',
        ]);
        Log::shouldHaveReceived('critical')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'conflicted')
                && $context['support_delivery_id'] === $delivery->id
                && $context['phase'] === 'smtp_exception');
    }

    public function test_smtp_exception_marks_only_an_owned_unreconciled_attempt_uncertain(): void
    {
        [, $draft] = $this->ticketAndDraft();
        $delivery = $this->delivery($draft, 'sending');

        $applied = $this->replyService()->completeException(
            $draft,
            $delivery,
            new RuntimeException('Connection closed after DATA'),
        );

        $this->assertTrue($applied);
        $delivery->refresh();
        $this->assertSame('uncertain', $delivery->status);
        $this->assertNull($delivery->reconciliation_outcome);
        $this->assertNotNull($delivery->uncertain_at);
        $this->assertSame('Connection closed after DATA', $delivery->error);
    }

    public function test_sending_delivery_cannot_be_reconciled_inside_minimum_300_second_lease(): void
    {
        [, $draft] = $this->ticketAndDraft();
        $delivery = $this->delivery($draft, 'sending');
        $delivery->update(['last_attempted_at' => now()->subSeconds(299)]);

        try {
            app(SupportReplyService::class)->reconcileDelivery(
                $delivery->fresh(),
                'confirmed_not_sent',
                'Checked too early.',
            );
            $this->fail('A live SMTP lease must not be manually reconciled.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('SMTP safety window', $exception->getMessage());
        }

        $this->assertSame('sending', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->reconciliation_outcome);
        Mail::assertNothingSent();
    }

    private function ticketAndDraft(): array
    {
        $ticket = SupportTicket::create([
            'mailbox_address' => 'appsupport@halalkiwi.com',
            'source' => 'app',
            'requester_name' => 'Amina',
            'requester_email' => 'amina@example.com',
            'normalized_requester_email' => 'amina@example.com',
            'normalized_requester_email_hash' => hash('sha256', 'amina@example.com'),
            'subject' => 'Support question',
            'category' => 'general_inquiry',
            'priority' => 'normal',
            'status' => 'draft_ready',
            'received_at' => now(),
        ]);
        $ticket->update(['reference' => sprintf('HK-SUP-%06d', $ticket->id)]);
        $draft = SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'direction' => 'outbound_draft',
            'from_name' => 'Halal Kiwi App Support',
            'from_address' => 'appsupport@halalkiwi.com',
            'to_address' => 'amina@example.com',
            'subject' => "Re: Support question [{$ticket->reference}]",
            'body' => 'Thanks for contacting us.',
            'drafted_at' => now(),
        ]);

        return [$ticket, $draft];
    }

    private function delivery(SupportMessage $draft, string $status): SupportDelivery
    {
        $transportMessageId = "<support-reply-{$draft->id}@halalkiwi.com>";

        return SupportDelivery::create([
            'support_ticket_id' => $draft->support_ticket_id,
            'support_message_id' => $draft->id,
            'kind' => 'customer_reply',
            'event_key' => hash('sha256', "support-reply:{$draft->id}"),
            'event_reference' => "support-reply:{$draft->id}",
            'mailer' => 'support',
            'recipient_address' => $draft->to_address,
            'normalized_recipient_address' => strtolower($draft->to_address),
            'status' => $status,
            'attempts' => 1,
            'approval_reference' => 'approved-before-send',
            'transport_message_id' => $transportMessageId,
            'transport_message_id_hash' => hash('sha256', strtolower($transportMessageId)),
            'last_attempted_at' => now(),
            'sent_at' => $status === 'sent' ? now() : null,
            'failed_at' => $status === 'failed' ? now() : null,
            'uncertain_at' => $status === 'uncertain' ? now() : null,
        ]);
    }

    private function replyService(): ExposedSupportReplyService
    {
        return new ExposedSupportReplyService(app(SupportTicketService::class));
    }
}

class ExposedSupportReplyService extends SupportReplyService
{
    public function completeSuccess(
        SupportMessage $draft,
        SupportDelivery $delivery,
        string $approvalReference,
    ): bool {
        return $this->finalizeSmtpSuccess($draft, $delivery, $approvalReference);
    }

    public function completeException(
        SupportMessage $draft,
        SupportDelivery $delivery,
        Throwable $exception,
    ): bool {
        return $this->finalizeSmtpException($draft, $delivery, $exception);
    }
}
