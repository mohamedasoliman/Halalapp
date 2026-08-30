<?php

namespace App\Services;

use App\Models\PrioritisationRequestPhoto;
use App\Models\UserInformationReply;
use App\Models\UserInformationReplyAttachment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class UserInformationReplyAttachmentService
{
    /**
     * Inspect attachments without writing to storage or the database.
     *
     * @param  array<int, string>  $paths
     * @return array<int, array<string, mixed>>
     */
    public function inspectPaths(array $paths): array
    {
        $maximumCount = max((int) config('prioritisation.attachment_max_count', 12), 1);
        if (count($paths) > $maximumCount) {
            throw new InvalidArgumentException("At most {$maximumCount} attachments may be recorded for one reply.");
        }

        $inspected = [];
        $totalBytes = 0;
        foreach ($paths as $path) {
            $path = trim($path);
            if ($path === '' || ! is_file($path) || ! is_readable($path)) {
                throw new InvalidArgumentException("Attachment is not a readable file: {$path}");
            }

            $size = filesize($path);
            $sha256 = hash_file('sha256', $path);
            if (! is_int($size) || $size < 0 || ! is_string($sha256)) {
                throw new InvalidArgumentException("Attachment could not be inspected: {$path}");
            }
            if ($size > max((int) config('prioritisation.attachment_max_bytes', 5 * 1024 * 1024), 1)) {
                throw new InvalidArgumentException("Attachment exceeds the per-file size limit: {$path}");
            }

            $sha256 = strtolower($sha256);
            if (isset($inspected[$sha256])) {
                continue;
            }

            $totalBytes += $size;
            if ($totalBytes > max((int) config('prioritisation.attachment_total_max_bytes', 60 * 1024 * 1024), 1)) {
                throw new InvalidArgumentException('The reply attachments exceed the total size limit.');
            }

            $safeName = $this->safeName($path);
            $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
            $detectedMime = is_string($detectedMime) && $detectedMime !== ''
                ? mb_substr(strtolower($detectedMime), 0, 255)
                : 'application/octet-stream';

            $item = [
                'path' => $path,
                'original_name' => $safeName,
                'size_bytes' => $size,
                'sha256' => $sha256,
                'declared_mime_type' => null,
                'detected_mime_type' => $detectedMime,
                'security_status' => 'quarantined',
                'rejection_reason' => 'Only decoded JPEG, PNG, and WebP product photos are promoted for research.',
                'width' => null,
                'height' => null,
            ];

            if (in_array($detectedMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $item = [...$item, ...$this->inspectImage($path, $detectedMime)];
            }

            $inspected[$sha256] = $item;
        }

        return array_values($inspected);
    }

    public function withIntakeLock(callable $callback): mixed
    {
        $seconds = max((int) config('prioritisation.attachment_intake_lock_seconds', 600), 60);
        $lock = Cache::lock('prioritisation:user-information-attachment-intake', $seconds);
        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'attachments' => ['User-information attachment intake is busy. Try again shortly.'],
            ]);
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $inspected
     */
    public function assertCapacity(
        string $normalizedSender,
        array $inspected,
        ?UserInformationReply $existingReply = null,
    ): void {
        $existingHashes = $existingReply
            ? $existingReply->attachments()->pluck('sha256')->map(fn ($hash) => strtolower((string) $hash))->all()
            : [];
        $incoming = collect($inspected)
            ->reject(fn (array $file) => in_array($file['sha256'], $existingHashes, true));
        if ($incoming->isEmpty()) {
            return;
        }

        $incomingCount = $incoming->count();
        $incomingBytes = (int) $incoming->sum('size_bytes');
        $dayStart = now()->startOfDay();
        $global = UserInformationReplyAttachment::query()->where('created_at', '>=', $dayStart);
        $perSender = UserInformationReplyAttachment::query()
            ->where('created_at', '>=', $dayStart)
            ->whereHas('reply', fn ($query) => $query->where(
                'normalized_from_address_hash',
                hash('sha256', strtolower(trim($normalizedSender))),
            ));

        if ($perSender->count() + $incomingCount
                > max((int) config('prioritisation.attachment_daily_per_email_count', 12), 1)
            || (int) $perSender->sum('size_bytes') + $incomingBytes
                > max((int) config('prioritisation.attachment_daily_per_email_bytes', 60 * 1024 * 1024), 1)) {
            throw ValidationException::withMessages([
                'attachments' => ['The daily user-information attachment limit for this sender has been reached.'],
            ]);
        }
        if ($global->count() + $incomingCount
                > max((int) config('prioritisation.attachment_daily_global_count', 500), 1)
            || (int) $global->sum('size_bytes') + $incomingBytes
                > max((int) config('prioritisation.attachment_daily_global_bytes', 1024 * 1024 * 1024), 1)) {
            throw ValidationException::withMessages([
                'attachments' => ['User-information attachment capacity is temporarily unavailable.'],
            ]);
        }

        $root = config('filesystems.disks.local.root', storage_path('app/private'));
        $probe = is_dir($root) ? $root : dirname($root);
        $freeBytes = @disk_free_space($probe);
        if ($freeBytes === false
            || $freeBytes - $incomingBytes
                < max((int) config('prioritisation.attachment_min_free_bytes', 1024 * 1024 * 1024), 1)) {
            throw ValidationException::withMessages([
                'attachments' => ['Private attachment storage is temporarily unavailable.'],
            ]);
        }
    }

    /**
     * Caller must wrap this in the same database transaction that creates the reply.
     *
     * @param  array<int, array<string, mixed>>  $inspected
     * @param  array<int, array{disk: string, path: string}>  $writtenFiles
     * @return array<int, UserInformationReplyAttachment>
     */
    public function storeForReply(
        UserInformationReply $reply,
        array $inspected,
        array &$writtenFiles,
    ): array {
        $stored = [];
        foreach ($inspected as $file) {
            $existing = $reply->attachments()->where('sha256', $file['sha256'])->first();
            if ($existing) {
                $stored[] = $existing;

                continue;
            }

            $extension = match ($file['detected_mime_type']) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'bin',
            };
            $rawPath = sprintf(
                'prioritisation_information_replies/%d/%d/original/%s.%s',
                $reply->request_id,
                $reply->id,
                $file['sha256'],
                $extension,
            );
            // Read once under the intake lock and re-verify every preflight
            // fact. The buffered bytes are then used for both preservation
            // and decoding, so a path swap cannot bypass quota/MIME/hash
            // checks or desynchronise the audit row from its evidence.
            $rawContents = $this->readVerifiedContents($file);
            $this->putFile($rawPath, $rawContents, $writtenFiles);

            $photo = null;
            $promotedAt = null;
            $securityStatus = $file['security_status'];
            $rejectionReason = $file['rejection_reason'];
            $width = $file['width'];
            $height = $file['height'];
            if ($securityStatus === 'accepted_photo') {
                [$photo, $width, $height] = $this->promotePhoto(
                    $reply,
                    $file,
                    $rawContents,
                    $writtenFiles,
                );
                $promotedAt = now();
                $rejectionReason = null;
            }

            $stored[] = UserInformationReplyAttachment::create([
                'reply_id' => $reply->id,
                'prioritisation_request_photo_id' => $photo?->id,
                'disk' => 'local',
                'path' => $rawPath,
                'original_name' => $file['original_name'],
                'declared_mime_type' => $file['declared_mime_type'],
                'detected_mime_type' => $file['detected_mime_type'],
                'security_status' => $securityStatus,
                'size_bytes' => $file['size_bytes'],
                'sha256' => $file['sha256'],
                'width' => $width,
                'height' => $height,
                'rejection_reason' => $rejectionReason,
                'promoted_at' => $promotedAt,
            ]);
        }

        return $stored;
    }

    /**
     * @param  array<int, array{disk: string, path: string}>  $writtenFiles
     */
    public function cleanupRolledBackFiles(array $writtenFiles, bool $committed): void
    {
        if ($committed) {
            return;
        }

        foreach (array_unique($writtenFiles, SORT_REGULAR) as $file) {
            try {
                Storage::disk($file['disk'])->delete($file['path']);
            } catch (Throwable $exception) {
                Log::error('A rolled-back user-information attachment could not be removed.', [
                    'disk' => $file['disk'],
                    'path' => $file['path'],
                    'exception' => $exception,
                ]);
            }
        }
    }

    private function inspectImage(string $path, string $detectedMime): array
    {
        $details = @getimagesize($path);
        if (! is_array($details)
            || ! isset($details[0], $details[1])
            || (int) $details[0] < 20
            || (int) $details[1] < 20
            || strtolower((string) ($details['mime'] ?? '')) !== $detectedMime) {
            return [
                'security_status' => 'quarantined',
                'rejection_reason' => 'The attachment MIME or image structure is invalid.',
                'width' => null,
                'height' => null,
            ];
        }

        $width = (int) $details[0];
        $height = (int) $details[1];
        $maximumDimension = max((int) config('prioritisation.attachment_max_dimension', 4096), 256);
        $maximumPixels = max((int) config('prioritisation.attachment_max_pixels', 13_000_000), 1_000_000);
        if ($width > $maximumDimension * 2
            || $height > $maximumDimension * 2
            || $width * $height > $maximumPixels) {
            return [
                'security_status' => 'quarantined',
                'rejection_reason' => 'The decoded image dimensions exceed the safe processing limit.',
                'width' => $width,
                'height' => $height,
            ];
        }

        try {
            $image = ImageManager::gd()->read($path)->orient();
            $width = $image->width();
            $height = $image->height();
        } catch (Throwable) {
            return [
                'security_status' => 'quarantined',
                'rejection_reason' => 'The image could not be decoded safely.',
                'width' => $width,
                'height' => $height,
            ];
        }

        return [
            'security_status' => 'accepted_photo',
            'rejection_reason' => null,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @param  array<string, mixed>  $file
     * @param  array<int, array{disk: string, path: string}>  $writtenFiles
     * @return array{PrioritisationRequestPhoto, int, int}
     */
    private function promotePhoto(
        UserInformationReply $reply,
        array $file,
        string $rawContents,
        array &$writtenFiles,
    ): array {
        $maximumDimension = max((int) config('prioritisation.attachment_max_dimension', 4096), 256);
        $quality = min(max((int) config('prioritisation.attachment_jpeg_quality', 88), 40), 95);
        try {
            $image = ImageManager::gd()
                ->read($rawContents)
                ->orient()
                ->scaleDown(width: $maximumDimension, height: $maximumDimension);
            $encoded = (string) $image->toJpeg($quality);
        } catch (Throwable $exception) {
            throw new RuntimeException('A validated product photo could not be normalized.', previous: $exception);
        }

        $photoPath = sprintf(
            'prioritisation_request_photos/information-replies/%d/%s.jpg',
            $reply->request_id,
            $file['sha256'],
        );
        $this->putFile($photoPath, $encoded, $writtenFiles);
        $normalizedName = pathinfo($file['original_name'], PATHINFO_FILENAME).'.jpg';
        $photo = PrioritisationRequestPhoto::firstOrCreate(
            [
                'request_id' => $reply->request_id,
                'path' => $photoPath,
            ],
            [
                'original_name' => mb_substr($normalizedName, 0, 500),
                'mime_type' => 'image/jpeg',
                'size_bytes' => strlen($encoded),
                'source' => 'user_information_reply',
            ],
        );

        if (blank($reply->request->photo_path)) {
            $reply->request->update(['photo_path' => $photoPath]);
        }

        return [$photo, $image->width(), $image->height()];
    }

    /** @param array<string, mixed> $file */
    private function readVerifiedContents(array $file): string
    {
        $stream = fopen($file['path'], 'rb');
        if ($stream === false) {
            throw new RuntimeException('A user-information attachment could not be opened for private storage.');
        }

        $maximumBytes = max((int) config('prioritisation.attachment_max_bytes', 5 * 1024 * 1024), 1);
        $contents = '';
        $hash = hash_init('sha256');
        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('A user-information attachment changed or became unreadable during intake.');
                }
                if ($chunk === '') {
                    continue;
                }
                $contents .= $chunk;
                if (strlen($contents) > $maximumBytes
                    || strlen($contents) > (int) $file['size_bytes']) {
                    throw new InvalidArgumentException('A user-information attachment changed after preflight.');
                }
                hash_update($hash, $chunk);
            }
        } finally {
            fclose($stream);
        }

        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        if (strlen($contents) !== (int) $file['size_bytes']
            || ! hash_equals((string) $file['sha256'], hash_final($hash))
            || ! is_string($detectedMime)
            || strtolower($detectedMime) !== strtolower((string) $file['detected_mime_type'])) {
            throw new InvalidArgumentException('A user-information attachment changed after preflight.');
        }

        return $contents;
    }

    /**
     * @param  array<int, array{disk: string, path: string}>  $writtenFiles
     */
    private function putFile(string $path, string $contents, array &$writtenFiles): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            return;
        }
        if (! $disk->put($path, $contents)) {
            throw new RuntimeException('A user-information attachment could not be stored privately.');
        }
        $writtenFiles[] = ['disk' => 'local', 'path' => $path];
    }

    private function safeName(string $path): string
    {
        $name = mb_substr(basename(str_replace('\\', '/', trim($path))), 0, 500);
        if ($name === '') {
            throw new InvalidArgumentException('An attachment filename is required.');
        }

        return $name;
    }
}
