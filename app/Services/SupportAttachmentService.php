<?php

namespace App\Services;

use App\Models\SupportAttachment;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class SupportAttachmentService
{
    /**
     * Capture an app submission and its attachment as one database unit while
     * retaining the global attachment-capacity reservation until commit.
     */
    public function captureUploadedFileAtomically(
        string $normalizedEmail,
        ?string $submissionUuid,
        string $sha256,
        UploadedFile $file,
        callable $capture,
    ): mixed {
        $fileSize = $file->getSize();
        if (! is_int($fileSize) || $fileSize < 0) {
            throw ValidationException::withMessages([
                'attachment' => ['The support attachment size could not be determined.'],
            ]);
        }

        return $this->withIntakeReservation(
            $normalizedEmail,
            $fileSize,
            $submissionUuid,
            $sha256,
            function () use ($capture, $file) {
                /** @var array<int, array{disk: string, path: string}> $writtenFiles */
                $writtenFiles = [];
                $committed = false;

                try {
                    $result = DB::transaction(function () use ($capture, $file, &$writtenFiles) {
                        $result = $capture();
                        $message = $result['message'] ?? null;
                        if (! $message instanceof SupportMessage) {
                            throw new RuntimeException('The support submission did not return a captured message.');
                        }

                        $this->storeUploadedFile(
                            $message,
                            $file,
                            function (string $disk, string $path) use (&$writtenFiles): void {
                                // Track the file before the attachment insert:
                                // a model/DB error can happen after INSERT but
                                // before create() returns its model instance.
                                $writtenFiles[] = compact('disk', 'path');
                            },
                        );

                        return $result;
                    }, 3);
                    $committed = true;

                    return $result;
                } finally {
                    $this->cleanRolledBackFiles($writtenFiles, $committed);
                }
            },
        );
    }

    public function withIntakeReservation(
        string $normalizedEmail,
        int $incomingBytes,
        ?string $submissionUuid,
        ?string $sha256,
        callable $callback,
    ): mixed {
        return $this->withGlobalIntakeLock(function () use (
            $normalizedEmail,
            $incomingBytes,
            $submissionUuid,
            $sha256,
            $callback,
        ) {
            $isExactReplay = false;
            if ($submissionUuid && $sha256) {
                $isExactReplay = SupportMessage::query()
                    ->where('client_submission_uuid', strtolower(trim($submissionUuid)))
                    ->whereHas('attachments', fn ($query) => $query->where('sha256', strtolower(trim($sha256))))
                    ->exists();
            }
            if (! $isExactReplay) {
                $this->assertIntakeAvailable($normalizedEmail, $incomingBytes);
            }

            return $callback($isExactReplay);
        });
    }

    public function withGlobalIntakeLock(callable $callback): mixed
    {
        $lock = Cache::lock('support:attachment-intake', 30);
        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'attachment' => ['Support attachment intake is busy. Try again shortly.'],
            ]);
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    public function assertIntakeAvailable(
        string $normalizedEmail,
        int $incomingBytes,
        int $incomingCount = 1,
    ): void {
        $normalizedEmail = strtolower(trim($normalizedEmail));
        $incomingBytes = max($incomingBytes, 0);
        $incomingCount = max($incomingCount, 0);
        if ($incomingCount === 0) {
            return;
        }

        $dayStart = now()->startOfDay();
        $global = SupportAttachment::query()->where('created_at', '>=', $dayStart);
        $perEmail = SupportAttachment::query()
            ->where('created_at', '>=', $dayStart);
        $perEmail->whereHas('ticket', function ($query) use ($normalizedEmail) {
            if (Schema::hasColumn('support_tickets', 'normalized_requester_email_hash')) {
                $query->where(
                    'normalized_requester_email_hash',
                    SupportTicket::normalizedRequesterEmailHash($normalizedEmail),
                );

                return;
            }

            // Compatibility for isolated legacy test schemas. Production uses
            // the fixed-width digest above to avoid indexing a 320-char value.
            $query->where('normalized_requester_email', $normalizedEmail);
        });

        if ($perEmail->count() + $incomingCount
                > max((int) config('support.attachment_daily_per_email_count'), 1)
            || (int) $perEmail->sum('size_bytes') + $incomingBytes
                > max((int) config('support.attachment_daily_per_email_bytes'), 1)) {
            throw ValidationException::withMessages([
                'attachment' => ['The daily attachment limit for this email address has been reached. Try again tomorrow.'],
            ]);
        }
        if ($global->count() + $incomingCount
                > max((int) config('support.attachment_daily_global_count'), 1)
            || (int) $global->sum('size_bytes') + $incomingBytes
                > max((int) config('support.attachment_daily_global_bytes'), 1)) {
            throw ValidationException::withMessages([
                'attachment' => ['Support attachment capacity is temporarily unavailable. Try again later.'],
            ]);
        }

        $root = config('filesystems.disks.local.root', storage_path('app/private'));
        $probe = is_dir($root) ? $root : dirname($root);
        $freeBytes = @disk_free_space($probe);
        if ($freeBytes === false
            || $freeBytes - $incomingBytes < max((int) config('support.attachment_min_free_bytes'), 1)) {
            throw ValidationException::withMessages([
                'attachment' => ['Support attachment storage is temporarily unavailable. Try again later.'],
            ]);
        }
    }

    public function storeUploadedFile(
        SupportMessage $message,
        UploadedFile $file,
        ?callable $afterPrivateWrite = null,
    ): SupportAttachment {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('The support attachment upload is invalid.');
        }

        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw new RuntimeException('The support attachment could not be read.');
        }

        try {
            return $this->storeStream(
                $message,
                $stream,
                $file->getClientOriginalName(),
                $file->getMimeType() ?: $file->getClientMimeType(),
                $file->getSize(),
                $afterPrivateWrite,
            );
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     */
    public function storeStream(
        SupportMessage $message,
        $stream,
        string $originalName,
        ?string $mimeType = null,
        ?int $sizeBytes = null,
        ?callable $afterPrivateWrite = null,
    ): SupportAttachment {
        $safeName = mb_substr(basename(str_replace('\\', '/', trim($originalName))), 0, 500);
        if ($safeName === '') {
            throw new InvalidArgumentException('A support attachment filename is required.');
        }

        $temporary = tmpfile();
        if ($temporary === false) {
            throw new RuntimeException('A protected temporary attachment file could not be created.');
        }

        try {
            $hash = hash_init('sha256');
            $bytes = 0;
            $maximumBytes = max((int) config('support.attachment_max_bytes', 5 * 1024 * 1024), 1);
            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('The support attachment could not be read.');
                }
                if ($chunk === '') {
                    continue;
                }
                hash_update($hash, $chunk);
                $bytes += strlen($chunk);
                if ($bytes > $maximumBytes) {
                    throw new InvalidArgumentException('The support attachment exceeds the configured maximum size.');
                }
                if (fwrite($temporary, $chunk) === false) {
                    throw new RuntimeException('The support attachment could not be buffered.');
                }
            }
            $sha256 = hash_final($hash);

            $existing = SupportAttachment::query()
                ->where('support_message_id', $message->id)
                ->where('sha256', $sha256)
                ->first();
            if ($existing) {
                return $existing;
            }

            rewind($temporary);
            $temporaryPath = stream_get_meta_data($temporary)['uri'] ?? null;
            $detectedMime = is_string($temporaryPath)
                ? (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath)
                : false;
            $detectedMime = is_string($detectedMime) && $detectedMime !== ''
                ? mb_substr($detectedMime, 0, 255)
                : 'application/octet-stream';
            $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
            $extension = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
            $suffix = preg_match('/^[a-z0-9]{1,10}$/', $extension) ? '.'.$extension : '';
            $path = sprintf(
                'support/%s/%s/%s%s',
                $message->ticket->reference,
                $message->id,
                $sha256,
                $suffix,
            );
            try {
                $stored = Storage::disk('local')->put($path, $temporary);
            } catch (Throwable $exception) {
                $this->deletePrivateFile('local', $path);

                throw $exception;
            }
            if (! $stored) {
                $this->deletePrivateFile('local', $path);

                throw new RuntimeException('The support attachment could not be stored privately.');
            }
            if ($afterPrivateWrite !== null) {
                try {
                    $afterPrivateWrite('local', $path);
                } catch (Throwable $exception) {
                    $this->deletePrivateFile('local', $path);

                    throw $exception;
                }
            }
            try {
                return SupportAttachment::create([
                    'support_ticket_id' => $message->support_ticket_id,
                    'support_message_id' => $message->id,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $safeName,
                    // Do not trust the MIME declared by the mailbox or HTTP client.
                    'mime_type' => $detectedMime,
                    'security_status' => in_array($detectedMime, $allowedMimes, true)
                        ? 'pending_review'
                        : 'quarantined',
                    'size_bytes' => $bytes,
                    'sha256' => $sha256,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                $winner = SupportAttachment::query()
                    ->where('support_message_id', $message->id)
                    ->where('sha256', $sha256)
                    ->first();
                if ($winner) {
                    if ($winner->path !== $path && ! SupportAttachment::where('disk', 'local')->where('path', $path)->exists()) {
                        $this->deletePrivateFile('local', $path);
                    }

                    return $winner;
                }
                if (! SupportAttachment::where('disk', 'local')->where('path', $path)->exists()) {
                    $this->deletePrivateFile('local', $path);
                }
                throw $exception;
            } catch (Throwable $exception) {
                if (! SupportAttachment::where('disk', 'local')->where('path', $path)->exists()) {
                    $this->deletePrivateFile('local', $path);
                }
                throw $exception;
            }
        } finally {
            fclose($temporary);
        }
    }

    /**
     * @param  array<int, array{disk: string, path: string}>  $writtenFiles
     */
    private function cleanRolledBackFiles(array $writtenFiles, bool $committed): void
    {
        foreach (array_unique($writtenFiles, SORT_REGULAR) as $file) {
            if ($committed) {
                try {
                    if (SupportAttachment::query()
                        ->where('disk', $file['disk'])
                        ->where('path', $file['path'])
                        ->exists()) {
                        continue;
                    }
                } catch (Throwable $exception) {
                    // Do not risk deleting a committed attachment merely
                    // because the post-commit orphan check is unavailable.
                    Log::warning('Could not verify a support attachment after commit.', [
                        'disk' => $file['disk'],
                        'path' => $file['path'],
                        'exception' => $exception,
                    ]);

                    continue;
                }
            }

            $this->deletePrivateFile($file['disk'], $file['path']);
        }
    }

    private function deletePrivateFile(string $disk, string $path): void
    {
        try {
            $storage = Storage::disk($disk);
            if (! $storage->delete($path) && $storage->exists($path)) {
                Log::error('A failed support attachment write remains on private storage.', [
                    'disk' => $disk,
                    'path' => $path,
                ]);
            }
        } catch (Throwable $exception) {
            Log::error('A failed support attachment write could not be cleaned up.', [
                'disk' => $disk,
                'path' => $path,
                'exception' => $exception,
            ]);
        }
    }
}
