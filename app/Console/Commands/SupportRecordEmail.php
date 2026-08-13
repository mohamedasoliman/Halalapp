<?php

namespace App\Console\Commands;

use App\Models\SupportMessage;
use App\Services\SupportAttachmentService;
use App\Services\SupportTicketService;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

class SupportRecordEmail extends Command
{
    protected $signature = 'support:record-email
        {--input= : UTF-8 JSON file containing one extracted appsupport mailbox message}
        {--record : Persist the previewed message; otherwise no database/filesystem writes occur}
        {--since= : Timezone-qualified RFC3339 cutover; required with --record; older messages are skipped}
        {--attachment=* : Attachment path to preserve privately; repeat as needed}';

    protected $description = 'Preview or idempotently record an extracted appsupport email without modifying IMAP/Maildir state';

    public function handle(
        SupportTicketService $tickets,
        SupportAttachmentService $attachments,
    ): int {
        $input = trim((string) $this->option('input'));
        if ($input === '' || ! is_file($input) || ! is_readable($input)) {
            $this->error('A readable --input JSON file is required. No message was recorded.');

            return self::FAILURE;
        }

        try {
            $data = json_decode(file_get_contents($input), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error('The input is not valid JSON: '.$exception->getMessage());

            return self::FAILURE;
        }
        if (! is_array($data)) {
            $this->error('The input JSON must be an object.');

            return self::FAILURE;
        }

        if (strtolower(trim((string) ($data['mailbox_address'] ?? ''))) !== 'appsupport@halalkiwi.com') {
            $this->error('Input must explicitly identify mailbox_address as appsupport@halalkiwi.com. No writes performed.');

            return self::FAILURE;
        }
        if (! array_key_exists('delivered_to', $data)
            || trim(implode(', ', (array) $data['delivered_to'])) === '') {
            $this->error('Input must explicitly include the extracted Delivered-To/To header. No writes performed.');

            return self::FAILURE;
        }
        $since = trim((string) $this->option('since'));
        if ($this->option('record') && $since === '') {
            $this->error('--record requires an explicit reviewed --since cutover. No writes performed.');

            return self::FAILURE;
        }
        $receivedAtInput = trim((string) ($data['received_at'] ?? ''));
        if ($receivedAtInput === '') {
            $this->error('Input must explicitly contain received_at. No writes performed.');

            return self::FAILURE;
        }
        try {
            $receivedAt = $this->parseRfc3339($receivedAtInput);
            $sinceAt = $since !== '' ? $this->parseRfc3339($since) : null;
            if ($sinceAt && $receivedAt->lt($sinceAt)) {
                $this->warn("Skipped: message predates the --since cutover ({$since}). No writes performed.");

                return self::SUCCESS;
            }
            // Eloquent stores timestamp columns in the application timezone.
            // Normalize the represented instant before its offset is removed.
            $data['received_at'] = $receivedAt->setTimezone((string) config('app.timezone', 'UTC'));
        } catch (Throwable) {
            $this->error('The message received_at/--since value is invalid. No writes performed.');

            return self::FAILURE;
        }

        $messageId = $tickets->normalizeMessageId($data['message_id'] ?? null);
        $existing = $messageId
            ? SupportMessage::where('message_id_hash', hash('sha256', $messageId))->with('ticket')->first()
            : null;
        $this->table(['Mailbox', 'Message-ID', 'From', 'Subject', 'Received', 'State'], [[
            $data['mailbox_address'],
            $messageId ?? 'INVALID',
            $data['from_address'] ?? '',
            $data['subject'] ?? '',
            $receivedAt->toIso8601String(),
            $existing ? "duplicate of {$existing->ticket->reference}" : 'new',
        ]]);

        if (! $this->option('record')) {
            $this->info('Preview complete. No database, mailbox, flags, or files were changed. Re-run with --record after review.');

            return self::SUCCESS;
        }

        $attachmentPaths = $this->option('attachment');
        $attachmentFiles = [];
        foreach ($attachmentPaths as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                $this->error("Attachment is not readable: {$path}. No message was recorded.");

                return self::FAILURE;
            }

            $size = filesize($path);
            $sha256 = hash_file('sha256', $path);
            if (! is_int($size) || ! is_string($sha256)) {
                $this->error("Attachment could not be inspected: {$path}. No message was recorded.");

                return self::FAILURE;
            }
            if ($size > max((int) config('support.attachment_max_bytes', 5 * 1024 * 1024), 1)) {
                $this->error("Attachment exceeds the configured maximum size: {$path}. No message was recorded.");

                return self::FAILURE;
            }

            $attachmentFiles[] = [
                'path' => $path,
                'size' => $size,
                'sha256' => strtolower($sha256),
            ];
        }

        try {
            if ($attachmentFiles === []) {
                $result = $tickets->captureMailboxMessage($data);
            } else {
                $normalizedEmail = strtolower(trim((string) ($data['from_address'] ?? '')));
                if (! filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('The sender address is invalid.');
                }

                $result = $attachments->withGlobalIntakeLock(function () use (
                    $attachments,
                    $tickets,
                    $data,
                    $messageId,
                    $normalizedEmail,
                    $attachmentFiles,
                ) {
                    // Re-read under the shared lock so a concurrent exact import
                    // can turn every supplied hash into a quota-free replay.
                    $existingMessage = $messageId
                        ? SupportMessage::query()
                            ->where('message_id_hash', hash('sha256', $messageId))
                            ->first()
                        : null;
                    $uniqueFiles = [];
                    foreach ($attachmentFiles as $file) {
                        $uniqueFiles[$file['sha256']] ??= $file;
                    }
                    if ($existingMessage) {
                        $existingHashes = $existingMessage->attachments()
                            ->whereIn('sha256', array_keys($uniqueFiles))
                            ->pluck('sha256')
                            ->all();
                        foreach ($existingHashes as $existingHash) {
                            unset($uniqueFiles[strtolower((string) $existingHash)]);
                        }
                    }

                    $attachments->assertIntakeAvailable(
                        $normalizedEmail,
                        array_sum(array_column($uniqueFiles, 'size')),
                        count($uniqueFiles),
                    );

                    // Capacity for the complete new batch is reserved before a
                    // new support message is captured. A later file I/O error
                    // can leave that message as an auditable retry target, but
                    // quota rejection cannot create an orphan message.
                    $result = $tickets->captureMailboxMessage($data);
                    if (! ($result['ignored'] ?? false)) {
                        foreach ($attachmentFiles as $file) {
                            $stream = fopen($file['path'], 'rb');
                            if ($stream === false) {
                                throw new RuntimeException("Attachment could not be opened: {$file['path']}");
                            }
                            try {
                                $mime = mime_content_type($file['path']) ?: 'application/octet-stream';
                                $attachments->storeStream(
                                    $result['message'],
                                    $stream,
                                    basename($file['path']),
                                    $mime,
                                    $file['size'],
                                );
                            } finally {
                                fclose($stream);
                            }
                        }
                    }

                    return $result;
                });
            }
        } catch (Throwable $exception) {
            $this->error(
                $exception->getMessage()
                .' The support message may already be present for safe retry; no mailbox file or flag was modified.',
            );

            return self::FAILURE;
        }

        if ($result['ignored'] ?? false) {
            $this->info(
                "Recorded an authenticated internal notification copy under {$result['ticket']->reference}; "
                .'it was not classified as a second customer message.',
            );
        } elseif ($result['created']) {
            $this->info("Recorded {$result['ticket']->reference} / message #{$result['message']->id}.");
        } else {
            $this->info("Duplicate Message-ID already recorded under {$result['ticket']->reference}; no duplicate was created.");
        }
        $this->info('This command does not connect to IMAP or move, rename, mark, or delete any mailbox message.');

        return self::SUCCESS;
    }

    private function parseRfc3339(string $value): Carbon
    {
        $value = trim($value);
        $matched = preg_match(
            '/\A(?<base>\d{4}-\d{2}-\d{2}[Tt](?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d)'.
            '(?:\.(?<fraction>\d+))?(?<zone>[Zz]|[+-](?:[01]\d|2[0-3]):[0-5]\d)\z/',
            $value,
            $parts,
        );
        if ($matched !== 1) {
            throw new InvalidArgumentException('A timezone-qualified RFC3339 timestamp is required.');
        }

        $fraction = (string) ($parts['fraction'] ?? '');
        $normalized = str_replace('t', 'T', (string) $parts['base']);
        if ($fraction !== '') {
            // Database timestamps preserve microseconds; RFC3339 permits more
            // precision, so truncate only the sub-microsecond remainder.
            $normalized .= '.'.str_pad(substr($fraction, 0, 6), 6, '0');
        }
        $normalized .= strtoupper((string) $parts['zone']);

        $format = $fraction === '' ? '!Y-m-d\TH:i:sP' : '!Y-m-d\TH:i:s.uP';
        $parsed = DateTimeImmutable::createFromFormat($format, $normalized);
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('The RFC3339 timestamp is not a valid calendar date and time.');
        }

        return Carbon::instance($parsed);
    }
}
