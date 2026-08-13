<?php

namespace App\Services;

use App\Mail\SupportReplyEmail;
use App\Models\SupportDelivery;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use LogicException;
use Throwable;

class SupportReplyService
{
    public function __construct(private readonly SupportTicketService $tickets) {}

    public function saveDraft(SupportTicket $ticket, array $data, ?int $actorAdminId = null): SupportMessage
    {
        if ($ticket->messages()->where('direction', 'outbound_draft')->exists()) {
            throw new LogicException('This ticket already has an active customer reply draft. Discard it before creating another.');
        }
        if ($ticket->deliveries()
            ->where('kind', 'customer_reply')
            ->whereIn('status', ['pending', 'sending', 'uncertain'])
            ->exists()) {
            throw new LogicException('This ticket has an unresolved customer reply delivery. Reconcile it before drafting another reply.');
        }
        $recipient = strtolower(trim((string) ($data['recipient_address'] ?? $ticket->requester_email)));
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid customer reply recipient is required.');
        }
        if ($recipient !== strtolower(trim((string) $ticket->requester_email))) {
            throw new InvalidArgumentException('A support reply may only be drafted for the ticket requester.');
        }
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            throw new InvalidArgumentException('A support reply body is required.');
        }

        $latestInbound = $ticket->messages()->where('direction', 'inbound')->latest('received_at')->latest('id')->first();
        $inReplyTo = $latestInbound?->message_id;
        $references = collect($latestInbound?->references_header ?? [])
            ->push($inReplyTo)
            ->filter()
            ->uniqueStrict()
            ->values()
            ->all();
        $subjectText = preg_replace('/[\r\n]+/', ' ', (string) ($data['subject'] ?? $ticket->subject));
        $subject = mb_substr(trim((string) $subjectText), 0, 500);
        if (! str_contains(strtoupper($subject), $ticket->reference)) {
            $subject = mb_substr("Re: {$subject} [{$ticket->reference}]", 0, 500);
        }

        return DB::transaction(function () use (
            $ticket,
            $recipient,
            $subject,
            $body,
            $inReplyTo,
            $references,
            $actorAdminId,
        ) {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($lockedTicket->messages()->where('direction', 'outbound_draft')->exists()
                || $lockedTicket->deliveries()
                    ->where('kind', 'customer_reply')
                    ->whereIn('status', ['pending', 'sending', 'uncertain'])
                    ->exists()) {
                throw new LogicException('This ticket gained an active draft/delivery. No second reply draft was created.');
            }
            $draft = SupportMessage::create([
                'support_ticket_id' => $lockedTicket->id,
                'direction' => 'outbound_draft',
                'from_name' => config('support.mailbox_name'),
                'from_address' => $this->tickets->mailboxAddress(),
                'to_address' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'in_reply_to' => $inReplyTo,
                'in_reply_to_hash' => $inReplyTo ? hash('sha256', $inReplyTo) : null,
                'references_header' => $references,
                'created_by' => $actorAdminId,
                'drafted_at' => now(),
            ]);
            $lockedTicket->update(['status' => 'draft_ready', 'resolved_at' => null]);
            SupportTicketEvent::create([
                'support_ticket_id' => $lockedTicket->id,
                'event_type' => 'reply_drafted',
                'actor_admin_id' => $actorAdminId,
                'after_values' => ['message_id' => $draft->id],
            ]);

            return $draft;
        });
    }

    public function sendApprovedDraft(SupportMessage $draft, string $approvalReference): SupportDelivery
    {
        $approvalReference = trim($approvalReference);
        if ($approvalReference === '') {
            throw new InvalidArgumentException('An explicit approval reference is required to send a support reply.');
        }
        $mailbox = $this->tickets->mailboxAddress();

        // A repeated approval request is also a safe delivery-status query. Do
        // not make an already-started or terminal audit record depend on the
        // server's current SMTP configuration, which may have been disabled
        // after the original attempt.
        $existingDelivery = $this->existingNonPendingDelivery($draft, $mailbox);
        if ($existingDelivery) {
            return $existingDelivery;
        }

        $mailerName = trim((string) config('support.mailer'));
        $configurationError = null;
        if (! config('support.mail_enabled')) {
            $configurationError = 'App-support customer email delivery is disabled. No email was sent.';
        } elseif ($mailerName !== 'support'
            || trim((string) config('mail.mailers.support.url')) !== ''
            || trim((string) config('mail.mailers.support.host')) === ''
            || strtolower(trim((string) config('mail.mailers.support.username'))) !== $mailbox) {
            $configurationError = 'Dedicated appsupport@ SMTP credentials are not configured. No email was sent.';
        }
        if ($configurationError !== null) {
            // Close the small race in which another request finishes or starts
            // this delivery while configuration is being checked.
            $existingDelivery = $this->existingNonPendingDelivery($draft, $mailbox);
            if ($existingDelivery) {
                return $existingDelivery;
            }

            throw new LogicException($configurationError);
        }

        $eventReference = "support-reply:{$draft->id}";
        $eventKey = hash('sha256', strtolower($eventReference));
        $transportMessageId = "<support-reply-{$draft->id}@halalkiwi.com>";
        [$lockedDraft, $delivery, $claimed] = DB::transaction(function () use (
            $draft,
            $eventKey,
            $eventReference,
            $mailerName,
            $approvalReference,
            $transportMessageId,
        ) {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($draft->support_ticket_id);
            $lockedDraft = SupportMessage::query()->lockForUpdate()->findOrFail($draft->id);
            $this->assertDraftBelongsToTicketAndMailbox($lockedTicket, $lockedDraft);
            $delivery = SupportDelivery::query()
                ->where('support_message_id', $lockedDraft->id)
                ->where('kind', 'customer_reply')
                ->lockForUpdate()
                ->first();
            if ($delivery) {
                $this->assertDeliveryRelationships($lockedTicket, $lockedDraft, $delivery);
            }
            if ($delivery && ($delivery->status !== 'pending'
                || $delivery->reconciliation_outcome !== null)) {
                return [$lockedDraft, $delivery, false];
            }
            if ($lockedDraft->direction !== 'outbound_draft') {
                throw new LogicException('Only an unsent support reply draft may be sent.');
            }
            if (! $delivery) {
                $delivery = SupportDelivery::create([
                    'support_ticket_id' => $lockedTicket->id,
                    'support_message_id' => $lockedDraft->id,
                    'kind' => 'customer_reply',
                    'event_key' => $eventKey,
                    'event_reference' => $eventReference,
                    'mailer' => $mailerName,
                    'recipient_address' => $lockedDraft->to_address,
                    'normalized_recipient_address' => strtolower(trim($lockedDraft->to_address)),
                    'status' => 'pending',
                    'approval_reference' => $approvalReference,
                    'transport_message_id' => $transportMessageId,
                    'transport_message_id_hash' => hash('sha256', strtolower($transportMessageId)),
                ]);
            }
            $claimed = SupportDelivery::query()
                ->whereKey($delivery->id)
                ->where('status', 'pending')
                ->whereNull('reconciliation_outcome')
                ->update([
                    'status' => 'sending',
                    'attempts' => $delivery->attempts + 1,
                    'last_attempted_at' => now(),
                ]) === 1;

            return [$lockedDraft, $delivery->fresh(), $claimed];
        });
        if (! $claimed) {
            return $delivery;
        }
        $draft = $lockedDraft;
        $effectiveApprovalReference = (string) ($delivery->approval_reference ?: $approvalReference);

        try {
            Mail::mailer($mailerName)
                ->to($delivery->recipient_address)
                ->send(new SupportReplyEmail($draft->fresh(['ticket']), $transportMessageId));
            $this->finalizeSmtpSuccess($draft, $delivery, $effectiveApprovalReference);
        } catch (Throwable $exception) {
            try {
                $this->finalizeSmtpException($draft, $delivery, $exception);
            } catch (Throwable $finalizerException) {
                Log::critical('Unable to safely record an app-support SMTP exception.', [
                    'support_ticket_id' => $draft->support_ticket_id,
                    'support_message_id' => $draft->id,
                    'support_delivery_id' => $delivery->id,
                    'smtp_exception' => $exception::class,
                    'finalizer_exception' => $finalizerException::class,
                ]);
            }
        }

        return $delivery->fresh();
    }

    /**
     * Return a delivery that must never be automatically attempted again.
     *
     * Pending deliveries deliberately return null because they still require
     * valid, dedicated SMTP configuration before they can be claimed.
     */
    private function existingNonPendingDelivery(
        SupportMessage $draft,
        string $mailbox,
    ): ?SupportDelivery {
        return DB::transaction(function () use ($draft, $mailbox) {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($draft->support_ticket_id);
            $lockedDraft = SupportMessage::query()->lockForUpdate()->findOrFail($draft->id);
            $this->assertDraftBelongsToTicketAndMailbox($lockedTicket, $lockedDraft, $mailbox);
            $delivery = SupportDelivery::query()
                ->where('support_message_id', $lockedDraft->id)
                ->where('kind', 'customer_reply')
                ->lockForUpdate()
                ->first();
            if ($delivery) {
                $this->assertDeliveryRelationships($lockedTicket, $lockedDraft, $delivery);
            }

            if ($delivery && ($delivery->status !== 'pending'
                || $delivery->reconciliation_outcome !== null)) {
                return $delivery;
            }
            if ($lockedDraft->direction !== 'outbound_draft') {
                throw new LogicException('Only an unsent support reply draft may be sent.');
            }

            return null;
        });
    }

    /**
     * Apply an SMTP success only while this exact attempt still owns the lease.
     */
    protected function finalizeSmtpSuccess(
        SupportMessage $draft,
        SupportDelivery $delivery,
        string $approvalReference,
    ): bool {
        return DB::transaction(function () use ($draft, $delivery, $approvalReference) {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($draft->support_ticket_id);
            $lockedDraft = SupportMessage::query()->lockForUpdate()->findOrFail($draft->id);
            $lockedDelivery = SupportDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            $this->assertDeliveryRelationships($lockedTicket, $lockedDraft, $lockedDelivery);

            if ($lockedDelivery->status !== 'sending'
                || $lockedDelivery->reconciliation_outcome !== null) {
                $this->recordDeliveryStateConflict(
                    $lockedTicket,
                    $lockedDraft,
                    $lockedDelivery,
                    'smtp_success',
                );

                return false;
            }

            $sentAt = now();
            $updated = SupportDelivery::query()
                ->whereKey($lockedDelivery->id)
                ->where('status', 'sending')
                ->whereNull('reconciliation_outcome')
                ->update(['status' => 'sent', 'sent_at' => $sentAt, 'error' => null]);
            if ($updated !== 1) {
                $lockedDelivery->refresh();
                $this->recordDeliveryStateConflict(
                    $lockedTicket,
                    $lockedDraft,
                    $lockedDelivery,
                    'smtp_success_conditional_update',
                );

                return false;
            }

            $lockedDraft->update([
                'direction' => 'outbound',
                'message_id' => $lockedDelivery->transport_message_id,
                'message_id_hash' => $lockedDelivery->transport_message_id_hash,
                'approval_reference' => $approvalReference,
                'sent_at' => $sentAt,
            ]);
            SupportTicketEvent::create([
                'support_ticket_id' => $lockedTicket->id,
                'event_type' => 'reply_sent',
                'actor_admin_id' => $lockedDraft->created_by,
                'after_values' => [
                    'message_id' => $lockedDraft->id,
                    'delivery_id' => $lockedDelivery->id,
                    'approval_reference' => $approvalReference,
                ],
            ]);

            return true;
        });
    }

    /**
     * Conservatively record an SMTP exception without undoing reconciliation.
     */
    protected function finalizeSmtpException(
        SupportMessage $draft,
        SupportDelivery $delivery,
        Throwable $exception,
    ): bool {
        return DB::transaction(function () use ($draft, $delivery, $exception) {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($draft->support_ticket_id);
            $lockedDraft = SupportMessage::query()->lockForUpdate()->findOrFail($draft->id);
            $lockedDelivery = SupportDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            $this->assertDeliveryRelationships($lockedTicket, $lockedDraft, $lockedDelivery);

            if ($lockedDelivery->status !== 'sending'
                || $lockedDelivery->reconciliation_outcome !== null) {
                $this->recordDeliveryStateConflict(
                    $lockedTicket,
                    $lockedDraft,
                    $lockedDelivery,
                    'smtp_exception',
                );

                return false;
            }

            $updated = SupportDelivery::query()
                ->whereKey($lockedDelivery->id)
                ->where('status', 'sending')
                ->whereNull('reconciliation_outcome')
                ->update([
                    // SMTP acceptance can occur before a transport exception reaches the app.
                    'status' => 'uncertain',
                    'uncertain_at' => now(),
                    'error' => mb_substr($exception->getMessage(), 0, 5000),
                ]);
            if ($updated !== 1) {
                $lockedDelivery->refresh();
                $this->recordDeliveryStateConflict(
                    $lockedTicket,
                    $lockedDraft,
                    $lockedDelivery,
                    'smtp_exception_conditional_update',
                );

                return false;
            }

            return true;
        });
    }

    private function assertDraftBelongsToTicketAndMailbox(
        SupportTicket $ticket,
        SupportMessage $draft,
        ?string $mailbox = null,
    ): void {
        $mailbox ??= $this->tickets->mailboxAddress();
        if ((int) $draft->support_ticket_id !== (int) $ticket->id) {
            throw new LogicException('The reply draft does not belong to its support ticket.');
        }
        if (strtolower(trim((string) $draft->from_address)) !== $mailbox) {
            throw new LogicException('The reply draft is not from appsupport@halalkiwi.com.');
        }
    }

    private function assertDeliveryRelationships(
        SupportTicket $ticket,
        SupportMessage $draft,
        SupportDelivery $delivery,
    ): void {
        $this->assertDraftBelongsToTicketAndMailbox($ticket, $draft);
        if ($delivery->kind !== 'customer_reply'
            || (int) $delivery->support_ticket_id !== (int) $ticket->id
            || (int) $delivery->support_message_id !== (int) $draft->id) {
            throw new LogicException('The customer reply delivery does not belong to this support draft.');
        }
    }

    private function recordDeliveryStateConflict(
        SupportTicket $ticket,
        SupportMessage $draft,
        SupportDelivery $delivery,
        string $phase,
    ): void {
        $context = [
            'support_ticket_id' => $ticket->id,
            'support_message_id' => $draft->id,
            'support_delivery_id' => $delivery->id,
            'phase' => $phase,
            'delivery_status' => $delivery->status,
            'reconciliation_outcome' => $delivery->reconciliation_outcome,
        ];
        Log::critical('App-support SMTP result conflicted with the delivery audit state.', $context);

        try {
            SupportTicketEvent::create([
                'support_ticket_id' => $ticket->id,
                'event_type' => 'reply_delivery_state_conflict',
                'actor_admin_id' => $draft->created_by,
                'before_values' => [
                    'delivery_id' => $delivery->id,
                    'status' => $delivery->status,
                    'reconciliation_outcome' => $delivery->reconciliation_outcome,
                ],
                'after_values' => [
                    'delivery_id' => $delivery->id,
                    'status' => $delivery->status,
                    'reconciliation_outcome' => $delivery->reconciliation_outcome,
                    'smtp_result_ignored' => true,
                ],
                'details' => "SMTP phase {$phase} was ignored because the delivery lease or reconciliation state changed.",
            ]);
        } catch (Throwable $auditException) {
            Log::critical('Unable to persist the app-support delivery conflict audit event.', $context + [
                'audit_exception' => $auditException::class,
            ]);
        }
    }

    public function discardDraft(
        SupportMessage $draft,
        string $reason,
        ?int $actorAdminId = null,
    ): SupportMessage {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A reason is required to discard a support reply draft.');
        }

        return DB::transaction(function () use ($draft, $reason, $actorAdminId) {
            SupportTicket::query()->lockForUpdate()->findOrFail($draft->support_ticket_id);
            $lockedDraft = SupportMessage::query()->lockForUpdate()->findOrFail($draft->id);
            if ($lockedDraft->direction === 'discarded_draft') {
                return $lockedDraft;
            }
            if ($lockedDraft->direction !== 'outbound_draft') {
                throw new LogicException('Only an unsent support reply draft may be discarded.');
            }
            if ($lockedDraft->deliveries()->where(function ($query) {
                $query->where('status', '!=', 'failed')
                    ->orWhere('reconciliation_outcome', '!=', 'confirmed_not_sent')
                    ->orWhereNull('reconciliation_outcome');
            })->exists()) {
                throw new LogicException('A draft with an unresolved delivery audit record cannot be discarded.');
            }
            $lockedDraft->update(['direction' => 'discarded_draft']);
            SupportTicketEvent::create([
                'support_ticket_id' => $lockedDraft->support_ticket_id,
                'event_type' => 'reply_draft_discarded',
                'actor_admin_id' => $actorAdminId,
                'before_values' => ['message_id' => $lockedDraft->id, 'direction' => 'outbound_draft'],
                'after_values' => ['message_id' => $lockedDraft->id, 'direction' => 'discarded_draft'],
                'details' => mb_substr($reason, 0, 5000),
            ]);

            return $lockedDraft->fresh();
        });
    }

    public function reconcileDelivery(
        SupportDelivery $delivery,
        string $outcome,
        string $reason,
        ?int $actorAdminId = null,
    ): SupportDelivery {
        $outcome = trim($outcome);
        $reason = trim($reason);
        if (! in_array($outcome, ['confirmed_sent', 'confirmed_not_sent'], true)) {
            throw new InvalidArgumentException('Reconciliation outcome must be confirmed_sent or confirmed_not_sent.');
        }
        if ($reason === '') {
            throw new InvalidArgumentException('A reconciliation reason is required.');
        }
        if ($delivery->kind !== 'customer_reply') {
            throw new LogicException('Only a customer reply delivery may be reconciled here.');
        }

        return DB::transaction(function () use ($delivery, $outcome, $reason, $actorAdminId) {
            // Match send/discard lock ordering: support ticket, message, delivery.
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($delivery->support_ticket_id);
            $draft = SupportMessage::query()
                ->whereKey($delivery->support_message_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDelivery = SupportDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            $this->assertDeliveryRelationships($lockedTicket, $draft, $lockedDelivery);
            if ($lockedDelivery->reconciliation_outcome !== null) {
                if ($lockedDelivery->reconciliation_outcome === $outcome) {
                    return $lockedDelivery;
                }

                throw new LogicException('This delivery was already reconciled to a different outcome.');
            }
            if (! in_array($lockedDelivery->status, ['sending', 'uncertain'], true)) {
                throw new LogicException('Only a sending or uncertain customer reply delivery may be reconciled.');
            }
            if ($lockedDelivery->status === 'sending'
                && ($lockedDelivery->last_attempted_at === null
                    || $lockedDelivery->last_attempted_at->gt(
                        now()->subSeconds(max((int) config('support.delivery_reconcile_after_seconds'), 300))
                    ))) {
                throw new LogicException(
                    'This delivery attempt is still within the SMTP safety window. Wait before reconciling it.'
                );
            }
            $statusBefore = $lockedDelivery->status;
            $at = now();
            $deliveryChanges = [
                'reconciliation_outcome' => $outcome,
                'reconciliation_reason' => mb_substr($reason, 0, 5000),
                'reconciled_by' => $actorAdminId,
                'reconciled_at' => $at,
            ];

            if ($outcome === 'confirmed_sent') {
                $deliveryChanges += ['status' => 'sent', 'sent_at' => $at];
                $draft->update([
                    'direction' => 'outbound',
                    'message_id' => $lockedDelivery->transport_message_id,
                    'message_id_hash' => $lockedDelivery->transport_message_id_hash,
                    'approval_reference' => $lockedDelivery->approval_reference,
                    'sent_at' => $at,
                ]);
            } else {
                $deliveryChanges += ['status' => 'failed', 'failed_at' => $at];
            }
            $lockedDelivery->update($deliveryChanges);
            SupportTicketEvent::create([
                'support_ticket_id' => $lockedTicket->id,
                'event_type' => 'reply_delivery_reconciled',
                'actor_admin_id' => $actorAdminId,
                'before_values' => [
                    'delivery_id' => $lockedDelivery->id,
                    'status' => $statusBefore,
                ],
                'after_values' => [
                    'delivery_id' => $lockedDelivery->id,
                    'status' => $lockedDelivery->fresh()->status,
                    'outcome' => $outcome,
                ],
                'details' => mb_substr($reason, 0, 5000),
            ]);

            return $lockedDelivery->fresh();
        });
    }
}
