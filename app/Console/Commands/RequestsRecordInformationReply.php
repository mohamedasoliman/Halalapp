<?php

namespace App\Console\Commands;

use App\Services\UserInformationReplyAttachmentService;
use App\Services\UserInformationReplyService;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use JsonException;
use Throwable;

class RequestsRecordInformationReply extends Command
{
    protected $signature = 'requests:record-information-reply
        {--input= : UTF-8 JSON file containing one extracted products-mailbox message}
        {--record : Persist the previewed reply; otherwise no database/filesystem writes occur}
        {--since= : Timezone-qualified RFC3339 cutover; required with --record; older messages are skipped}
        {--attachment=* : Extracted attachment path; repeat as needed}
        {--allow-legacy-match : Approve the exact sender-and-barcode fallback for a legacy sent email}';

    protected $description = 'Preview or idempotently record a user response to a product information request';

    public function handle(
        UserInformationReplyService $replies,
        UserInformationReplyAttachmentService $attachments,
    ): int {
        $inputPath = trim((string) $this->option('input'));
        if ($inputPath === '' || ! is_file($inputPath) || ! is_readable($inputPath)) {
            $this->error('A readable --input JSON file is required. No reply was recorded.');

            return self::FAILURE;
        }

        try {
            $contents = file_get_contents($inputPath);
            if (! is_string($contents) || ! mb_check_encoding($contents, 'UTF-8')) {
                throw new InvalidArgumentException('The input must be valid UTF-8.');
            }
            $data = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                throw new InvalidArgumentException('The input JSON must be an object.');
            }
        } catch (JsonException|InvalidArgumentException $exception) {
            $this->error('The input is invalid: '.$exception->getMessage());

            return self::FAILURE;
        }

        $since = trim((string) $this->option('since'));
        if ($this->option('record') && $since === '') {
            $this->error('--record requires an explicit reviewed --since cutover. No writes performed.');

            return self::FAILURE;
        }
        try {
            $receivedAtInput = trim((string) ($data['received_at'] ?? ''));
            if ($receivedAtInput === '') {
                throw new InvalidArgumentException('received_at is required.');
            }
            $receivedAt = $this->parseRfc3339($receivedAtInput);
            $sinceAt = $since !== '' ? $this->parseRfc3339($since) : null;
            if ($sinceAt && $receivedAt->lt($sinceAt)) {
                $this->warn("Skipped: message predates the --since cutover ({$since}). No writes performed.");

                return self::SUCCESS;
            }
            $data['received_at'] = $receivedAt->setTimezone((string) config('app.timezone', 'UTC'));
            $inspected = $attachments->inspectPaths((array) $this->option('attachment'));
            $preview = $replies->preview($data, $inspected);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage().' No database, file, or mailbox state was changed.');

            return self::FAILURE;
        }

        $match = $preview['match'];
        $this->table(
            ['Message-ID', 'From', 'Received', 'Request', 'Barcode', 'Match', 'Delivery', 'State'],
            [[
                $preview['message']['message_id'],
                $preview['message']['normalized_from_address'],
                $preview['message']['received_at']->toIso8601String(),
                $match['request']?->id ?? 'UNMATCHED',
                $match['barcode'] ?? '-',
                $match['method'] ?? ($match['reason'] ?: 'none'),
                $match['delivery']?->status ?? '-',
                $preview['existing'] ? 'duplicate' : 'new',
            ]],
        );
        if ($inspected !== []) {
            $this->table(
                ['Attachment', 'Detected MIME', 'Bytes', 'Validation', 'Reason'],
                collect($inspected)->map(fn (array $file) => [
                    $file['original_name'],
                    $file['detected_mime_type'],
                    $file['size_bytes'],
                    $file['security_status'],
                    $file['rejection_reason'] ?? '',
                ])->all(),
            );
        }

        if (! $this->option('record')) {
            if ($match['requires_legacy_approval']) {
                $this->warn('Legacy fallback: recording requires a reviewed --allow-legacy-match.');
            }
            if (! $match['matched']) {
                $this->warn('Unmatched: '.($match['reason'] ?: 'manual routing is required.'));
            }
            $this->info('Preview complete. No database, mailbox, flags, emails, or files were changed.');

            return self::SUCCESS;
        }

        try {
            $result = $replies->capture(
                $data,
                $inspected,
                (bool) $this->option('allow-legacy-match'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            $this->error('No mailbox message was moved, deleted, or marked. Review the audit state before retrying.');

            return self::FAILURE;
        }

        $reply = $result['reply'];
        $this->info($result['created']
            ? "Recorded user-information reply #{$reply->id} for request #{$reply->request_id}."
            : "Duplicate Message-ID already belongs to reply #{$reply->id}; missing attachments were added idempotently.");
        $this->line(sprintf(
            'Accepted photos: %d; quarantined/rejected attachments: %d; review state: %s.',
            $reply->attachments->where('security_status', 'accepted_photo')->count(),
            $reply->attachments->whereIn('security_status', ['quarantined', 'rejected'])->count(),
            $reply->processing_status,
        ));
        $this->info('This command does not connect to IMAP or move, delete, or mark any mailbox message.');

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
