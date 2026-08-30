<?php

namespace App\Services;

use App\Models\PrioritisationRequest;
use App\Models\UserInformationReply;
use App\Support\UserInformationReplyReference;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class UserInformationReplyService
{
    private const MAILBOX = 'products@halalkiwi.com';

    public function __construct(
        private readonly UserInformationReplyMatcher $matcher,
        private readonly UserInformationReplyAttachmentService $attachments,
        private readonly ProductsMailboxMessageIdGuard $messageIds,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $inspectedAttachments
     * @return array<string, mixed>
     */
    public function preview(array $input, array $inspectedAttachments = []): array
    {
        $message = $this->normalizeInput($input);
        $existing = UserInformationReply::query()
            ->with(['request', 'delivery', 'attachments'])
            ->where('message_id_hash', $message['message_id_hash'])
            ->first();
        if ($existing) {
            $this->assertCompatibleReplay($existing, $message);
            $this->assertReplayMayAddAttachments($existing, $inspectedAttachments);

            return [
                'message' => $message,
                'match' => [
                    'matched' => true,
                    'request' => $existing->request,
                    'delivery' => $existing->delivery,
                    'barcode' => $existing->barcode,
                    'method' => $existing->match_method,
                    'confidence' => $existing->match_confidence,
                    'requires_legacy_approval' => false,
                    'reason' => null,
                ],
                'existing' => $existing,
                'attachments' => $inspectedAttachments,
            ];
        }

        return [
            'message' => $message,
            'match' => $this->matcher->match($message),
            'existing' => null,
            'attachments' => $inspectedAttachments,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $inspectedAttachments
     * @return array{reply: UserInformationReply, created: bool, attachments: array}
     */
    public function capture(
        array $input,
        array $inspectedAttachments = [],
        bool $allowLegacyMatch = false,
    ): array {
        $message = $this->normalizeInput($input);

        return $this->messageIds->withClaimLock(
            $message['message_id'],
            ProductsMailboxMessageIdGuard::FLOW_USER_INFORMATION,
            function () use ($message, $inspectedAttachments, $allowLegacyMatch) {
                return $this->attachments->withIntakeLock(function () use (
                    $message,
                    $inspectedAttachments,
                    $allowLegacyMatch,
                ) {
                    /** @var array<int, array{disk: string, path: string}> $writtenFiles */
                    $writtenFiles = [];
                    $committed = false;

                    try {
                        $result = DB::transaction(function () use (
                            $message,
                            $inspectedAttachments,
                            $allowLegacyMatch,
                            &$writtenFiles,
                        ) {
                            $existing = UserInformationReply::query()
                                ->where('message_id_hash', $message['message_id_hash'])
                                ->lockForUpdate()
                                ->first();
                            if ($existing) {
                                $this->assertCompatibleReplay($existing, $message);
                                $this->assertReplayMayAddAttachments($existing, $inspectedAttachments);
                                $this->attachments->assertCapacity(
                                    $message['normalized_from_address'],
                                    $inspectedAttachments,
                                    $existing,
                                );
                                $stored = $this->attachments->storeForReply(
                                    $existing,
                                    $inspectedAttachments,
                                    $writtenFiles,
                                );

                                return [
                                    'reply' => $existing->fresh(['request', 'delivery', 'attachments.photo']),
                                    'created' => false,
                                    'attachments' => $stored,
                                ];
                            }

                            $match = $this->matcher->match($message);
                            if (! $match['matched']) {
                                throw new InvalidArgumentException(
                                    ($match['reason'] ?: 'The reply could not be mapped to one exact information request.')
                                    .' No reply was recorded.'
                                );
                            }
                            if ($match['requires_legacy_approval'] && ! $allowLegacyMatch) {
                                throw new InvalidArgumentException(
                                    'This is a legacy sender-and-barcode match. Re-run only after review with --allow-legacy-match.'
                                );
                            }

                            $request = PrioritisationRequest::query()
                                ->lockForUpdate()
                                ->findOrFail($match['request']->id);
                            $this->attachments->assertCapacity(
                                $message['normalized_from_address'],
                                $inspectedAttachments,
                            );
                            $reply = UserInformationReply::create([
                                'request_id' => $request->id,
                                'request_notification_delivery_id' => $match['delivery']->id,
                                'mailbox_address' => $message['mailbox_address'],
                                'message_id' => $message['message_id'],
                                'message_id_hash' => $message['message_id_hash'],
                                'payload_hash' => $message['payload_hash'],
                                'in_reply_to' => $message['in_reply_to'],
                                'in_reply_to_hash' => $message['in_reply_to_hash'],
                                'references_header' => $message['references_header'],
                                'from_name' => $message['from_name'],
                                'from_address' => $message['from_address'],
                                'normalized_from_address' => $message['normalized_from_address'],
                                'normalized_from_address_hash' => hash('sha256', $message['normalized_from_address']),
                                'to_address' => self::MAILBOX,
                                'delivered_to' => $message['delivered_to'],
                                'subject' => $message['subject'],
                                'body' => $message['body'],
                                'barcode' => $match['barcode'],
                                'match_method' => $match['method'],
                                'match_confidence' => $match['confidence'],
                                'processing_status' => 'pending_review',
                                'raw_headers' => $message['raw_headers'],
                                'received_at' => $message['received_at'],
                            ]);
                            $reply->setRelation('request', $request);

                            $latest = $request->information_received_at;
                            if (! $latest || $message['received_at']->greaterThan($latest)) {
                                $latest = $message['received_at'];
                            }
                            $request->update(['information_received_at' => $latest]);
                            $request->increment('information_reply_count');
                            $stored = $this->attachments->storeForReply(
                                $reply,
                                $inspectedAttachments,
                                $writtenFiles,
                            );

                            return [
                                'reply' => $reply->fresh(['request', 'delivery', 'attachments.photo']),
                                'created' => true,
                                'attachments' => $stored,
                            ];
                        });
                        $committed = true;

                        return $result;
                    } finally {
                        $this->attachments->cleanupRolledBackFiles($writtenFiles, $committed);
                    }
                });
            },
        );
    }

    public function disposition(
        UserInformationReply $reply,
        string $outcome,
        string $reason,
    ): UserInformationReply {
        $outcome = strtolower(trim($outcome));
        $reason = trim($reason);
        if (! in_array($outcome, ['processed', 'needs_clarification', 'no_action'], true)) {
            throw new InvalidArgumentException('The reply outcome must be processed, needs_clarification, or no_action.');
        }
        if ($reason === '') {
            throw new InvalidArgumentException('A concise reviewed disposition reason is required.');
        }

        return DB::transaction(function () use ($reply, $outcome, $reason) {
            $locked = UserInformationReply::query()->lockForUpdate()->findOrFail($reply->id);
            if ($locked->processing_status !== 'pending_review') {
                if ($locked->processing_status === $outcome && trim((string) $locked->review_notes) === $reason) {
                    return $locked;
                }

                throw new InvalidArgumentException(
                    "Reply #{$locked->id} already has terminal outcome {$locked->processing_status}."
                );
            }

            $locked->update([
                'processing_status' => $outcome,
                'review_notes' => $reason,
                'processed_at' => now(),
            ]);

            return $locked->fresh(['request', 'delivery', 'attachments.photo']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeInput(array $input): array
    {
        $configuredMailbox = strtolower(trim((string) config('prioritisation.mailbox_address', self::MAILBOX)));
        if ($configuredMailbox !== self::MAILBOX) {
            throw new RuntimeException('Prioritisation reply intake is hard-locked to products@halalkiwi.com.');
        }
        $mailbox = strtolower(trim((string) ($input['mailbox_address'] ?? '')));
        if ($mailbox !== self::MAILBOX) {
            throw new InvalidArgumentException('Input must explicitly identify mailbox_address as products@halalkiwi.com.');
        }

        $deliveredTo = $this->extractAddresses($input['delivered_to'] ?? []);
        if (! in_array(self::MAILBOX, $deliveredTo, true)) {
            throw new InvalidArgumentException(
                'The extracted Delivered-To/To header must include products@halalkiwi.com.'
            );
        }

        $fromAddress = strtolower(trim((string) ($input['from_address'] ?? '')));
        if (mb_strlen($fromAddress) > 320
            || ! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)
            || str_ends_with($fromAddress, '@halalkiwi.com')) {
            throw new InvalidArgumentException('The reply sender must be a valid external email address.');
        }
        $messageId = $this->messageIds->normalize((string) ($input['message_id'] ?? ''));

        $receivedAt = $input['received_at'] ?? null;
        if (! $receivedAt instanceof DateTimeInterface) {
            throw new InvalidArgumentException('received_at must be a reviewed timezone-qualified timestamp.');
        }
        $receivedAt = Carbon::parse($receivedAt->format('Y-m-d\TH:i:s.uP'))
            ->setTimezone(config('app.timezone', 'UTC'));

        $subject = trim((string) ($input['subject'] ?? ''));
        if ($subject === '' || mb_strlen($subject) > 500) {
            throw new InvalidArgumentException('The email subject is required and must not exceed 500 characters.');
        }
        $body = (string) ($input['body'] ?? '');
        if (strlen($body) > max((int) config('prioritisation.mailbox_body_max_bytes', 2 * 1024 * 1024), 1)) {
            throw new InvalidArgumentException('The email body exceeds the configured intake limit.');
        }
        $rawHeaders = $input['raw_headers'] ?? [];
        if (! is_array($rawHeaders)) {
            throw new InvalidArgumentException('raw_headers must be a JSON object when supplied.');
        }
        $encodedHeaders = json_encode($rawHeaders, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($encodedHeaders)
            || strlen($encodedHeaders) > max((int) config('prioritisation.mailbox_headers_max_bytes', 64 * 1024), 1)) {
            throw new InvalidArgumentException('The extracted email headers exceed the configured intake limit.');
        }

        $references = $this->normalizedThreadIds($input['references'] ?? $input['references_header'] ?? []);
        $inReplyToIds = $this->normalizedThreadIds($input['in_reply_to'] ?? []);
        if (count($inReplyToIds) > 1) {
            throw new InvalidArgumentException(
                'In-Reply-To identifies more than one parent Message-ID. Manual review is required.'
            );
        }
        $inReplyTo = $inReplyToIds[0] ?? null;
        $normalized = [
            ...$input,
            'mailbox_address' => self::MAILBOX,
            'delivered_to' => $deliveredTo,
            'from_name' => ($name = trim((string) ($input['from_name'] ?? ''))) !== ''
                ? mb_substr($name, 0, 255)
                : null,
            'from_address' => $fromAddress,
            'normalized_from_address' => $fromAddress,
            'message_id' => $messageId,
            'message_id_hash' => hash('sha256', $messageId),
            'received_at' => $receivedAt,
            'subject' => $subject,
            'body' => $body,
            'raw_headers' => $rawHeaders,
            'in_reply_to' => $inReplyTo,
            'in_reply_to_hash' => $inReplyTo ? hash('sha256', $inReplyTo) : null,
            'references' => $references,
            'references_header' => $references,
        ];
        $normalized['payload_hash'] = $this->payloadHash($normalized);

        return $normalized;
    }

    private function assertCompatibleReplay(UserInformationReply $existing, array $message): void
    {
        if (! hash_equals((string) $existing->payload_hash, (string) $message['payload_hash'])) {
            throw new InvalidArgumentException(
                'This Message-ID already exists with a different sender, timestamp, subject, body, or thread payload.'
            );
        }
    }

    /** @param array<int, array<string, mixed>> $inspectedAttachments */
    private function assertReplayMayAddAttachments(
        UserInformationReply $existing,
        array $inspectedAttachments,
    ): void {
        if ($existing->processing_status === 'pending_review') {
            return;
        }

        $existingHashes = $existing->attachments()
            ->pluck('sha256')
            ->map(fn ($hash) => strtolower((string) $hash));
        $hasNewAttachment = collect($inspectedAttachments)->contains(
            fn (array $attachment) => ! $existingHashes->contains(strtolower((string) $attachment['sha256'])),
        );
        if ($hasNewAttachment) {
            throw new InvalidArgumentException(
                "Reply #{$existing->id} is already terminal and cannot receive unreviewed attachment evidence."
            );
        }
    }

    private function payloadHash(array $message): string
    {
        $payload = [
            'mailbox_address' => $message['mailbox_address'],
            'delivered_to' => $message['delivered_to'],
            'message_id' => $message['message_id'],
            'from_address' => $message['normalized_from_address'],
            'received_at' => $message['received_at']->format('Y-m-d\TH:i:s.uP'),
            'subject' => $message['subject'],
            'body' => $message['body'],
            'in_reply_to' => $message['in_reply_to'],
            'references' => $message['references_header'],
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @return array<int, string> */
    private function normalizedThreadIds(mixed $values): array
    {
        return collect((array) $values)
            ->flatMap(function ($value) {
                $value = trim((string) $value);
                preg_match_all('/<[^<>\s]+@[^<>\s]+>/', $value, $matches);
                if (($matches[0] ?? []) === [] && preg_match('/^[^<>\s]+@[^<>\s]+$/D', $value) === 1) {
                    return [$value];
                }

                return $matches[0] ?? [];
            })
            ->map(function (string $messageId) {
                try {
                    return UserInformationReplyReference::normalizeMessageId($messageId);
                } catch (InvalidArgumentException) {
                    return null;
                }
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function extractAddresses(mixed $values): array
    {
        return collect((array) $values)
            ->flatMap(function ($value) {
                preg_match_all(
                    '/[A-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
                    (string) $value,
                    $matches,
                );

                return $matches[0] ?? [];
            })
            ->map(fn (string $email) => strtolower(trim($email)))
            ->filter(fn (string $email) => mb_strlen($email) <= 320
                && filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }
}
