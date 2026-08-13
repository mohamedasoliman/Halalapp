<?php

namespace App\Services;

use App\Mail\ContactUsEmail;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Mime\Address;

class SupportTicketService
{
    private const REQUIRED_MAILBOX = 'appsupport@halalkiwi.com';

    public function mailboxAddress(): string
    {
        $address = strtolower(trim((string) config('support.mailbox_address')));
        if ($address !== self::REQUIRED_MAILBOX) {
            throw new InvalidArgumentException(
                'App-support capture is locked to appsupport@halalkiwi.com. No message was captured.'
            );
        }

        return $address;
    }

    public function captureAppSubmission(array $data): array
    {
        $uuid = $this->normalizeUuid($data['submission_uuid'] ?? $data['client_submission_uuid'] ?? null);
        $normalizedRequesterEmail = $this->normalizeEmail($data['email'] ?? null);
        $payloadHash = $this->submissionPayloadHash($data);
        $existing = $uuid === null ? null : SupportMessage::query()
            ->where('client_submission_uuid', $uuid)
            ->with('ticket')
            ->first();
        if ($existing) {
            if (! is_string($existing->ticket->payload_hash)
                || ! hash_equals($existing->ticket->payload_hash, $payloadHash)) {
                throw new ConflictHttpException('This submission identifier is already in use for different content.');
            }

            return ['ticket' => $existing->ticket, 'message' => $existing, 'created' => false];
        }

        try {
            return DB::transaction(function () use ($data, $uuid, $payloadHash, $normalizedRequesterEmail) {
                $ticket = SupportTicket::create([
                    'mailbox_address' => $this->mailboxAddress(),
                    'source' => 'app_form',
                    'client_submission_uuid' => $uuid,
                    'payload_hash' => $payloadHash,
                    'requester_name' => $this->requesterName($data),
                    'requester_email' => trim((string) $data['email']),
                    'normalized_requester_email' => $normalizedRequesterEmail,
                    'normalized_requester_email_hash' => SupportTicket::normalizedRequesterEmailHash(
                        $normalizedRequesterEmail,
                    ),
                    'subject' => $this->cleanHeader($data['subject'] ?? '', 500),
                    'summary' => mb_substr(trim((string) ($data['body'] ?? '')), 0, 2000),
                    'category' => $this->category($data['category'] ?? null),
                    'priority' => 'normal',
                    'status' => 'new',
                    // App-supplied context is a hint only. Admin-reviewed linked_*
                    // fields remain empty until explicit triage.
                    'submission_context_type' => isset($data['context_type'])
                        ? mb_substr(trim((string) $data['context_type']), 0, 40)
                        : null,
                    'submission_context_id' => isset($data['context_id'])
                        ? mb_substr(trim((string) $data['context_id']), 0, 255)
                        : null,
                    'submission_context_label' => $this->submissionContextLabel($data),
                    'submitted_barcode' => preg_match('/^[0-9]{8,14}$/', (string) ($data['barcode'] ?? ''))
                        ? (string) $data['barcode']
                        : null,
                    'received_at' => now(),
                ]);
                $this->assignReference($ticket);

                $message = SupportMessage::create([
                    'support_ticket_id' => $ticket->id,
                    'direction' => 'inbound',
                    'client_submission_uuid' => $uuid,
                    'from_name' => $ticket->requester_name,
                    'from_address' => $ticket->requester_email,
                    'to_address' => $ticket->mailbox_address,
                    'subject' => $ticket->subject,
                    'body' => trim((string) $data['body']),
                    'raw_headers' => Arr::only($data, [
                        'app_version',
                        'app_build',
                        'platform',
                        'device_model',
                        'os_version',
                        'context_type',
                        'context_id',
                        'barcode',
                    ]),
                    'received_at' => now(),
                ]);

                SupportTicketEvent::create([
                    'support_ticket_id' => $ticket->id,
                    'event_type' => 'captured',
                    'after_values' => [
                        'source' => 'app_form',
                        'client_submission_uuid' => $uuid,
                        'message_id' => $message->id,
                    ],
                ]);

                return ['ticket' => $ticket, 'message' => $message, 'created' => true];
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent request can win the UUID unique constraint after the
            // precheck. Reload only that UUID and apply the same replay check.
            $winner = $uuid === null ? null : SupportMessage::query()
                ->where('client_submission_uuid', $uuid)
                ->with('ticket')
                ->first();
            if (! $winner) {
                throw $exception;
            }
            if (! is_string($winner->ticket->payload_hash)
                || ! hash_equals($winner->ticket->payload_hash, $payloadHash)) {
                throw new ConflictHttpException('This submission identifier is already in use for different content.');
            }

            return ['ticket' => $winner->ticket, 'message' => $winner, 'created' => false];
        }
    }

    public function captureMailboxMessage(array $data): array
    {
        $mailbox = strtolower(trim((string) ($data['mailbox_address'] ?? '')));
        if ($mailbox !== $this->mailboxAddress()) {
            throw new InvalidArgumentException('Only the appsupport@halalkiwi.com mailbox may be captured.');
        }
        $deliveredTo = $this->sanitizeDeliveredTo($data['delivered_to'] ?? null);
        if (! $this->recipientHeaderContainsMailbox($deliveredTo, $mailbox)) {
            throw new InvalidArgumentException(
                'The extracted Delivered-To/To header must contain appsupport@halalkiwi.com.'
            );
        }
        $fromAddress = trim((string) ($data['from_address'] ?? ''));
        $normalizedFromAddress = $this->normalizeEmail($fromAddress);
        if (strlen($fromAddress) > 320) {
            throw new InvalidArgumentException('Mailbox sender address exceeds the supported length.');
        }
        $body = (string) ($data['body'] ?? '');
        if (strlen($body) > max((int) config('support.mailbox_body_max_bytes'), 1)) {
            throw new InvalidArgumentException('Mailbox message body exceeds the configured capture limit.');
        }
        $rawHeaders = $this->boundedCaptureHeaders(
            $this->sanitizeRawHeaders($data['raw_headers'] ?? []),
            [
                'delivered_to' => $deliveredTo,
                'extracted_support_notification' => $this->cleanHeader(
                    $data['support_notification'] ?? '',
                    40,
                ),
                'extracted_support_reference' => $this->cleanHeader(
                    $data['support_reference'] ?? '',
                    30,
                ),
                'extracted_support_message_id' => $this->cleanHeader(
                    $data['support_message_id'] ?? '',
                    20,
                ),
                'extracted_support_submission_uuid' => $this->cleanHeader(
                    $data['support_submission_uuid'] ?? '',
                    64,
                ),
                'extracted_authenticated_internal' => ($data['authenticated_internal'] ?? false) === true,
                'extracted_envelope_from' => $this->cleanHeader($data['envelope_from'] ?? '', 320),
            ],
        );

        $messageId = $this->normalizeMessageId($data['message_id'] ?? null);
        if ($messageId === null) {
            throw new InvalidArgumentException('A valid mailbox Message-ID is required.');
        }
        $messageIdHash = hash('sha256', $messageId);

        $existing = SupportMessage::query()
            ->where('message_id_hash', $messageIdHash)
            ->with('ticket')
            ->first();
        if ($existing) {
            if (! hash_equals((string) $existing->message_id, $messageId)) {
                throw new InvalidArgumentException('The support Message-ID hash conflicts with an existing message.');
            }

            return [
                'ticket' => $existing->ticket,
                'message' => $existing,
                'created' => false,
                'ignored' => $existing->direction === 'internal_notification',
            ];
        }

        $submissionUuid = $this->normalizeUuid($data['support_submission_uuid'] ?? null);
        $internalReference = strtoupper(trim((string) ($data['support_reference'] ?? '')));
        $submissionMessageId = $this->normalizePositiveInteger($data['support_message_id'] ?? null);
        $submission = $submissionMessageId === null ? null : SupportMessage::query()
            ->whereKey($submissionMessageId)
            ->with('ticket')
            ->first();
        if ($submission && $this->isTrustedInternalNotification(
            $data,
            $submission,
            $internalReference,
            $normalizedFromAddress,
            $messageId,
            $submissionUuid,
        )) {
            // Preserve the authenticated local notification as an audited
            // message, but never treat it as a second customer inbound.
            return $this->recordInternalNotification(
                $submission->ticket,
                $data,
                $messageId,
                $messageIdHash,
                $fromAddress,
                $body,
                $this->trustedNotificationEvidence(
                    $data,
                    $submission,
                    $messageId,
                    $internalReference,
                    $submissionUuid,
                    $deliveredTo,
                    $rawHeaders,
                ),
            );
        }

        $inReplyTo = $this->normalizeMessageId($data['in_reply_to'] ?? null);
        $references = $this->normalizeReferences($data['references'] ?? []);
        $reference = $this->extractTicketReference((string) ($data['subject'] ?? '').' '.(string) ($data['body'] ?? ''));
        $ticket = $reference ? SupportTicket::where('reference', $reference)->first() : null;
        if (! $ticket) {
            foreach (array_filter([$inReplyTo, ...$references]) as $parentMessageId) {
                $parent = SupportMessage::query()
                    ->where('message_id_hash', hash('sha256', $parentMessageId))
                    ->first();
                if ($parent && hash_equals((string) $parent->message_id, $parentMessageId)) {
                    $ticket = $parent->ticket;
                    break;
                }
            }
        }

        try {
            return DB::transaction(function () use (
                $data,
                $messageId,
                $messageIdHash,
                $inReplyTo,
                $references,
                $ticket,
                $fromAddress,
                $normalizedFromAddress,
                $body,
                $rawHeaders,
            ) {
                if ($ticket !== null) {
                    // Serialize inbound capture with triage/closure so a message
                    // cannot be appended to a stale closed ticket without reopening it.
                    $ticket = SupportTicket::query()->lockForUpdate()->find($ticket->id);
                }
                $createdTicket = $ticket === null;
                if ($ticket === null) {
                    $ticket = SupportTicket::create([
                        'mailbox_address' => $this->mailboxAddress(),
                        'source' => 'mailbox',
                        'first_message_id' => $messageId,
                        'first_message_id_hash' => $messageIdHash,
                        'requester_name' => $this->cleanHeader($data['from_name'] ?? '', 255),
                        'requester_email' => $fromAddress,
                        'normalized_requester_email' => $normalizedFromAddress,
                        'normalized_requester_email_hash' => SupportTicket::normalizedRequesterEmailHash(
                            $normalizedFromAddress,
                        ),
                        'subject' => $this->cleanHeader($data['subject'] ?? '', 500),
                        'summary' => mb_substr(trim((string) ($data['body'] ?? '')), 0, 2000),
                        'category' => $this->category($data['category'] ?? null),
                        'priority' => 'normal',
                        'status' => 'new',
                        'received_at' => $data['received_at'] ?? now(),
                    ]);
                    $this->assignReference($ticket);
                }

                $message = SupportMessage::create([
                    'support_ticket_id' => $ticket->id,
                    'direction' => 'inbound',
                    'message_id' => $messageId,
                    'message_id_hash' => $messageIdHash,
                    'from_name' => $this->cleanHeader($data['from_name'] ?? '', 255),
                    'from_address' => $fromAddress,
                    'to_address' => $this->mailboxAddress(),
                    'subject' => $this->cleanHeader($data['subject'] ?? '', 500),
                    'body' => $body,
                    'in_reply_to' => $inReplyTo,
                    'in_reply_to_hash' => $inReplyTo ? hash('sha256', $inReplyTo) : null,
                    'references_header' => $references,
                    'raw_headers' => $rawHeaders,
                    'received_at' => $data['received_at'] ?? now(),
                ]);

                if (! $createdTicket && in_array($ticket->status, ['resolved', 'no_action'], true)) {
                    $closedStatus = $ticket->status;
                    $closedAt = $ticket->resolved_at;
                    $ticket->update(['status' => 'new', 'resolved_at' => null, 'resolution_note' => null]);
                    SupportTicketEvent::create([
                        'support_ticket_id' => $ticket->id,
                        'event_type' => 'reopened_by_inbound',
                        'before_values' => ['status' => $closedStatus, 'resolved_at' => $closedAt],
                        'after_values' => ['status' => 'new', 'resolved_at' => null],
                        'details' => 'A new non-duplicate inbound message reopened the ticket.',
                    ]);
                }

                SupportTicketEvent::create([
                    'support_ticket_id' => $ticket->id,
                    'event_type' => $createdTicket ? 'captured' : 'message_added',
                    'after_values' => ['message_id' => $message->id, 'email_message_id' => $messageId],
                ]);

                return ['ticket' => $ticket, 'message' => $message, 'created' => true, 'ignored' => false];
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $winner = SupportMessage::query()
                ->where('message_id_hash', $messageIdHash)
                ->with('ticket')
                ->first();
            if (! $winner) {
                throw $exception;
            }
            if (! hash_equals((string) $winner->message_id, $messageId)) {
                throw new InvalidArgumentException('The support Message-ID hash conflicts with an existing message.');
            }

            return [
                'ticket' => $winner->ticket,
                'message' => $winner,
                'created' => false,
                'ignored' => false,
            ];
        }
    }

    public function triage(SupportTicket $ticket, array $changes, ?int $actorAdminId = null): SupportTicket
    {
        $allowed = Arr::only($changes, [
            'category',
            'priority',
            'status',
            'assigned_to',
            'linked_entity_type',
            'linked_entity_id',
            'linked_barcode',
            'proposed_handoff',
            'resolution_note',
        ]);

        return DB::transaction(function () use ($ticket, $allowed, $actorAdminId) {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->validateTriage($lockedTicket, $allowed);
            $before = Arr::only($lockedTicket->getAttributes(), array_keys($allowed));
            if (in_array(($allowed['status'] ?? null), ['resolved', 'no_action'], true)) {
                $allowed['resolved_at'] = now();
            } elseif (isset($allowed['status']) && ! in_array($allowed['status'], ['resolved', 'no_action'], true)) {
                $allowed['resolved_at'] = null;
            }
            $lockedTicket->update($allowed);
            SupportTicketEvent::create([
                'support_ticket_id' => $lockedTicket->id,
                'event_type' => 'triaged',
                'actor_admin_id' => $actorAdminId,
                'before_values' => $before,
                'after_values' => Arr::only($lockedTicket->fresh()->getAttributes(), array_keys($allowed)),
            ]);

            return $lockedTicket->fresh();
        });
    }

    public function normalizeMessageId(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));
        if ($value === '' || strlen($value) > 998 || preg_match('/[\r\n]/', $value)) {
            return null;
        }
        $value = trim($value, '<> ');

        return preg_match('/^[A-Za-z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[A-Za-z0-9.-]+$/', $value)
            && substr_count($value, '@') === 1
            && ! str_contains($value, '..')
            && ! str_starts_with($value, '.')
            && ! str_ends_with($value, '.')
            ? '<'.strtolower($value).'>'
            : null;
    }

    private function normalizeReferences(mixed $references): array
    {
        if (is_string($references)) {
            preg_match_all('/<[^<>\s]+@[^<>\s]+>/', $references, $matches);
            $references = $matches[0];
        }

        return collect(is_array($references) ? $references : [])
            ->map(fn ($id) => $this->normalizeMessageId($id))
            ->filter()
            ->uniqueStrict()
            ->values()
            ->all();
    }

    private function recipientHeaderContainsMailbox(mixed $value, string $mailbox): bool
    {
        $values = is_array($value) ? $value : [$value];

        return collect($values)->contains(function ($header) use ($mailbox) {
            try {
                return strtolower(Address::create((string) $header)->getAddress()) === $mailbox;
            } catch (\Throwable) {
                return false;
            }
        });
    }

    private function isTrustedInternalNotification(
        array $data,
        SupportMessage $submission,
        string $reference,
        string $normalizedFromAddress,
        string $messageId,
        ?string $submissionUuid,
    ): bool {
        $expectedSender = strtolower(trim((string) config('mail.from.address')));
        $envelopeFrom = strtolower(trim((string) ($data['envelope_from'] ?? '')));
        $subject = (string) ($data['subject'] ?? '');
        $expectedMessageId = $this->normalizeMessageId(
            ContactUsEmail::notificationMessageIdFor($submission->id),
        );
        $storedUuid = $this->normalizeUuid($submission->client_submission_uuid);
        $rawSubmissionUuid = trim((string) ($data['support_submission_uuid'] ?? ''));
        $uuidMatches = $storedUuid === null
            ? $rawSubmissionUuid === ''
            : $submissionUuid !== null && hash_equals($storedUuid, $submissionUuid);

        return ($data['authenticated_internal'] ?? false) === true
            && strtolower(trim((string) ($data['support_notification'] ?? ''))) === 'internal'
            && $expectedSender !== ''
            && $normalizedFromAddress === $expectedSender
            && $envelopeFrom === $expectedSender
            && $submission->direction === 'inbound'
            && $submission->ticket->source === 'app_form'
            && $this->normalizePositiveInteger($data['support_message_id'] ?? null) === $submission->id
            && $reference === $submission->ticket->reference
            && str_contains(strtoupper($subject), $reference)
            && $expectedMessageId !== null
            && hash_equals($expectedMessageId, $messageId)
            && $uuidMatches;
    }

    private function trustedNotificationEvidence(
        array $data,
        SupportMessage $submission,
        string $messageId,
        string $reference,
        ?string $submissionUuid,
        array $deliveredTo,
        array $rawHeaders,
    ): array {
        return $this->boundedCaptureHeaders($rawHeaders, [
            'delivered_to' => $deliveredTo,
            'capture_classification' => 'trusted_internal_notification',
            'authenticated_internal' => true,
            'normalized_from_address' => strtolower(trim((string) ($data['from_address'] ?? ''))),
            'normalized_envelope_from' => strtolower(trim((string) ($data['envelope_from'] ?? ''))),
            'support_notification' => 'internal',
            'support_reference' => $reference,
            'support_message_id' => $submission->id,
            'support_submission_uuid' => $submissionUuid,
            'expected_message_id' => $messageId,
        ]);
    }

    private function recordInternalNotification(
        SupportTicket $ticket,
        array $data,
        string $messageId,
        string $messageIdHash,
        string $fromAddress,
        string $body,
        array $rawHeaders,
    ): array {
        try {
            return DB::transaction(function () use (
                $ticket,
                $data,
                $messageId,
                $messageIdHash,
                $fromAddress,
                $body,
                $rawHeaders,
            ) {
                $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
                $message = SupportMessage::create([
                    'support_ticket_id' => $lockedTicket->id,
                    'direction' => 'internal_notification',
                    'message_id' => $messageId,
                    'message_id_hash' => $messageIdHash,
                    'from_name' => $this->cleanHeader($data['from_name'] ?? '', 255),
                    'from_address' => $fromAddress,
                    'to_address' => $this->mailboxAddress(),
                    'subject' => $this->cleanHeader($data['subject'] ?? '', 500),
                    'body' => $body,
                    'raw_headers' => $rawHeaders,
                    'received_at' => $data['received_at'] ?? now(),
                ]);
                SupportTicketEvent::create([
                    'support_ticket_id' => $lockedTicket->id,
                    'event_type' => 'internal_notification_captured',
                    'after_values' => [
                        'message_id' => $message->id,
                        'email_message_id' => $messageId,
                        'capture_classification' => $rawHeaders['capture_classification'],
                        'authenticated_internal' => $rawHeaders['authenticated_internal'],
                        'normalized_from_address' => $rawHeaders['normalized_from_address'],
                        'normalized_envelope_from' => $rawHeaders['normalized_envelope_from'],
                        'support_notification' => $rawHeaders['support_notification'],
                        'support_reference' => $rawHeaders['support_reference'],
                        'support_message_id' => $rawHeaders['support_message_id'],
                        'support_submission_uuid' => $rawHeaders['support_submission_uuid'],
                        'expected_message_id' => $rawHeaders['expected_message_id'],
                    ],
                ]);

                return [
                    'ticket' => $lockedTicket,
                    'message' => $message,
                    'created' => true,
                    'ignored' => true,
                ];
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $winner = SupportMessage::query()->where('message_id_hash', $messageIdHash)->with('ticket')->first();
            if (! $winner || ! hash_equals((string) $winner->message_id, $messageId)) {
                throw $exception;
            }

            return ['ticket' => $winner->ticket, 'message' => $winner, 'created' => false, 'ignored' => true];
        }
    }

    private function sanitizeRawHeaders(mixed $headers): array
    {
        if (! is_array($headers)) {
            return [];
        }
        $allowed = Arr::only($headers, [
            'date',
            'sender',
            'reply_to',
            'return_path',
            'content_type',
            'user_agent',
            'x_mailer',
        ]);
        $clean = [];
        foreach ($allowed as $name => $value) {
            if (! is_scalar($value) && ! is_array($value)) {
                continue;
            }
            $clean[$name] = mb_substr(
                preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', is_array($value) ? implode(', ', $value) : (string) $value),
                0,
                4000,
            );
        }
        if (strlen(json_encode($clean)) > max((int) config('support.mailbox_headers_max_bytes'), 1)) {
            throw new InvalidArgumentException('Mailbox headers exceed the configured capture limit.');
        }

        return $clean;
    }

    private function sanitizeDeliveredTo(mixed $headers): array
    {
        $headers = is_array($headers) ? array_slice($headers, 0, 20) : [$headers];

        return collect($headers)
            ->filter(fn ($header) => is_scalar($header))
            ->map(function ($header) {
                $header = preg_replace(
                    '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
                    '',
                    str_replace(["\r", "\n"], ' ', (string) $header),
                );

                return mb_substr(trim((string) $header), 0, 1000);
            })
            ->filter(fn ($header) => $header !== '')
            ->values()
            ->all();
    }

    private function boundedCaptureHeaders(array $headers, array $additional): array
    {
        $headers = array_merge($headers, $additional);
        $encoded = json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($encoded)
            || strlen($encoded) > max((int) config('support.mailbox_headers_max_bytes'), 1)) {
            throw new InvalidArgumentException('Mailbox headers exceed the configured capture limit.');
        }

        return $headers;
    }

    private function extractTicketReference(string $text): ?string
    {
        return preg_match('/\b(HK-SUP-\d{6,})\b/i', $text, $match)
            ? strtoupper($match[1])
            : null;
    }

    private function assignReference(SupportTicket $ticket): void
    {
        $ticket->forceFill(['reference' => sprintf('HK-SUP-%06d', $ticket->id)])->save();
    }

    private function normalizeUuid(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return Str::isUuid($value) ? $value : null;
    }

    private function normalizePositiveInteger(mixed $value): ?int
    {
        $value = trim((string) $value);
        if (! preg_match('/^[1-9][0-9]{0,17}$/', $value)) {
            return null;
        }

        $integer = (int) $value;

        return (string) $integer === $value ? $integer : null;
    }

    private function submissionPayloadHash(array $data): string
    {
        $canonical = [
            'name' => trim((string) ($data['name'] ?? '')),
            'requester_name' => trim((string) ($data['requester_name'] ?? '')),
            'email' => strtolower(trim((string) ($data['email'] ?? ''))),
            'subject' => trim((string) ($data['subject'] ?? '')),
            'body' => trim((string) ($data['body'] ?? '')),
            'category' => $this->category($data['category'] ?? null),
            'context_type' => trim((string) ($data['context_type'] ?? '')),
            'context_id' => trim((string) ($data['context_id'] ?? '')),
            'barcode' => trim((string) ($data['barcode'] ?? '')),
            'app_version' => trim((string) ($data['app_version'] ?? '')),
            'app_build' => trim((string) ($data['app_build'] ?? '')),
            'platform' => trim((string) ($data['platform'] ?? '')),
            'device_model' => trim((string) ($data['device_model'] ?? '')),
            'os_version' => trim((string) ($data['os_version'] ?? '')),
            'attachment_sha256' => strtolower(trim((string) ($data['attachment_sha256'] ?? ''))),
        ];

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function normalizeEmail(mixed $value): string
    {
        $email = strtolower(trim((string) $value));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid requester email address is required.');
        }

        return $email;
    }

    private function requesterName(array $data): ?string
    {
        $explicit = $this->cleanHeader($data['requester_name'] ?? '', 255);
        if ($explicit !== '') {
            return $explicit;
        }
        if ($this->usesNameAsContextLabel($data)) {
            return null;
        }

        $legacy = $this->cleanHeader($data['name'] ?? '', 255);

        return $legacy !== '' ? $legacy : null;
    }

    private function usesNameAsContextLabel(array $data): bool
    {
        return in_array(trim((string) ($data['context_type'] ?? '')), [
            'product',
            'restaurant',
            'restaurant_suggestion',
            'masjid',
            'advertising',
        ], true) || in_array($this->category($data['category'] ?? null), [
            'product_issue',
            'restaurant_update',
            'masjid_update',
            'barcode_submission',
            'advertising',
        ], true);
    }

    private function submissionContextLabel(array $data): ?string
    {
        $value = $this->usesNameAsContextLabel($data)
            ? $this->cleanHeader($data['name'] ?? '', 255)
            : (trim((string) ($data['context_type'] ?? '')) === 'business_network'
                ? $this->cleanHeader($data['context_id'] ?? '', 255)
                : '');

        return $value !== '' ? $value : null;
    }

    private function cleanHeader(mixed $value, int $limit): string
    {
        return mb_substr(trim((string) preg_replace('/[\r\n]+/', ' ', (string) $value)), 0, $limit);
    }

    private function category(mixed $category): string
    {
        $category = strtolower(trim((string) $category));

        return in_array($category, SupportTicket::CATEGORIES, true) ? $category : 'general_inquiry';
    }

    private function validateTriage(SupportTicket $ticket, array $changes): void
    {
        foreach ([
            'category' => SupportTicket::CATEGORIES,
            'priority' => SupportTicket::PRIORITIES,
            'status' => SupportTicket::STATUSES,
            'proposed_handoff' => SupportTicket::HANDOFFS,
            'linked_entity_type' => SupportTicket::LINKED_ENTITY_TYPES,
        ] as $field => $valid) {
            if (array_key_exists($field, $changes)
                && $changes[$field] !== null
                && ! in_array($changes[$field], $valid, true)) {
                throw ValidationException::withMessages([$field => ["The selected {$field} is invalid."]]);
            }
        }

        if (in_array(($changes['status'] ?? null), ['resolved', 'no_action'], true)
            && trim((string) ($changes['resolution_note'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'resolution_note' => ['A resolution/no-action reason is required before closing a support ticket.'],
            ]);
        }

        if (in_array(($changes['status'] ?? null), ['resolved', 'no_action'], true)) {
            $hasDraft = $ticket->messages()->where('direction', 'outbound_draft')->exists();
            $hasIncompleteCustomerDelivery = $ticket->deliveries()
                ->where('kind', 'customer_reply')
                ->whereIn('status', ['pending', 'sending', 'uncertain'])
                ->exists();
            if ($hasDraft || $hasIncompleteCustomerDelivery) {
                throw ValidationException::withMessages([
                    'status' => ['Resolve or discard the outstanding customer reply draft/delivery before closing this ticket.'],
                ]);
            }
        }
    }
}
