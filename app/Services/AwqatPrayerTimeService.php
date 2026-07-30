<?php

namespace App\Services;

use App\Exceptions\AwqatMasjidNotFoundException;
use App\Exceptions\AwqatUpstreamException;
use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AwqatPrayerTimeService
{
    public const PRAYER_FIELDS = [
        'fajr' => 'JamaatFajr',
        'zohar' => 'JamaatZohar',
        'asr' => 'JamaatAsr',
        'magrib' => 'JamaatMagrib',
        'isha' => 'JamaatIsha',
        'jumma' => 'KhutbaJumma',
    ];

    private const AZAAN_FIELDS = [
        'AzaanFajr',
        'AzaanZohar',
        'AzaanAsr',
        'AzaanMagrib',
        'AzaanIsha',
        'AzaanJumma',
    ];

    public function current(
        string $areaId,
        string $masjidId,
        bool $fresh = false,
        ?int $maxAgeSeconds = null,
    ): array {
        $area = $this->area(
            $areaId,
            fresh: $fresh,
            maxAgeSeconds: $maxAgeSeconds,
        );

        foreach ($area['rows'] as $row) {
            if (is_array($row) && (string) ($row['MasjidID'] ?? '') === $masjidId) {
                return $row;
            }
        }

        throw new AwqatMasjidNotFoundException(
            'Prayer times were not found for this masjid.'
        );
    }

    private function area(
        string $areaId,
        bool $fresh,
        ?int $maxAgeSeconds,
    ): array {
        $cacheKey = $this->cacheKey($areaId);
        $cache = Cache::store((string) config('awqat.cache_store', 'file'));
        if (! $fresh) {
            $cached = $cache->get($cacheKey);
            if ($this->usableCache($cached, $maxAgeSeconds)) {
                return $cached;
            }
        }

        try {
            $response = Http::acceptJson()
                ->withUserAgent('HalalKiwi-PrayerTimes/1.0')
                ->connectTimeout((int) config('awqat.connect_timeout', 4))
                ->timeout((int) config('awqat.timeout', 10))
                ->retry(2, 200, throw: false)
                ->get($this->url('read_path'), [
                    'AreaID' => $areaId,
                    'cdatereq' => now('Pacific/Auckland')->format('d-m-Y'),
                    '_hk' => now()->format('YmdHi'),
                ]);
        } catch (ConnectionException $exception) {
            throw new AwqatUpstreamException(
                'The live prayer-time service could not be reached.',
                previous: $exception,
            );
        }

        if (! $response->successful()) {
            throw new AwqatUpstreamException(
                "The live prayer-time service returned HTTP {$response->status()}."
            );
        }

        $rows = $response->json('ResultData');
        if (! is_array($rows)) {
            throw new AwqatUpstreamException(
                'The live prayer-time service returned an invalid response.'
            );
        }

        $area = [
            'fetched_at' => now()->timestamp,
            'rows' => $rows,
        ];
        $ttl = max(0, (int) config('awqat.read_cache_ttl', 300));
        if ($ttl > 0) {
            $cache->put($cacheKey, $area, $ttl);
        }

        return $area;
    }

    public function publicTimes(array $record): array
    {
        $times = [];

        foreach (self::PRAYER_FIELDS as $prayer => $field) {
            $times[$prayer] = $this->normaliseTime($record[$field] ?? null);
        }

        return $times;
    }

    public function normaliseTime(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtoupper(trim($value));
        if ($value === '' || in_array($value, ['-', '--:--', 'N/A', 'NULL'], true)) {
            return null;
        }

        foreach (['!h:i A', '!g:i A'] as $format) {
            $time = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($time !== false && ($errors === false || (
                $errors['warning_count'] === 0 && $errors['error_count'] === 0
            ))) {
                return $time->format('h:i A');
            }
        }

        return null;
    }

    public function publishAcknowledged(array $current, array $changes): array
    {
        $payload = [
            'MasjidAdminID' => (string) ($current['MasjidAdminID'] ?? ''),
            'MasjidID' => (string) ($current['MasjidID'] ?? ''),
        ];

        if ($payload['MasjidAdminID'] === '' || $payload['MasjidID'] === '') {
            throw new AwqatUpstreamException(
                'The live prayer-time record is missing its update identifiers.'
            );
        }

        foreach (self::AZAAN_FIELDS as $field) {
            $payload[$field] = (string) ($current[$field] ?? '');
        }

        foreach (self::PRAYER_FIELDS as $prayer => $field) {
            $payload[$field] = $changes[$prayer]
                ?? (string) ($current[$field] ?? '');
        }

        $payload['JamaatEid'] = (string) ($current['JamaatEid'] ?? '');
        $payload['UpdateTime'] = now('Pacific/Auckland')->format('d-m-Y H:i:s');

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withUserAgent('HalalKiwi-PrayerTimes/1.0')
                ->connectTimeout((int) config('awqat.connect_timeout', 4))
                ->timeout((int) config('awqat.timeout', 10))
                ->post($this->url('update_path'), $payload);
        } catch (ConnectionException $exception) {
            return $this->reconcileUncertainWrite($current, $changes);
        }

        if (! $this->wasAccepted($response)) {
            throw new AwqatUpstreamException(
                $this->upstreamMessage($response)
            );
        }

        $published = $current;
        foreach ($changes as $prayer => $time) {
            $field = self::PRAYER_FIELDS[$prayer] ?? null;
            if ($field !== null) {
                $published[$field] = $time;
            }
        }
        $this->updateCachedRecord(
            (string) ($current['AreaID'] ?? ''),
            $published,
        );

        return $published;
    }

    private function wasAccepted(Response $response): bool
    {
        if (! $response->successful()) {
            return false;
        }

        $success = $response->json('Success');

        return $success === true
            || $success === 1
            || (is_string($success) && strtolower($success) === 'true');
    }

    private function upstreamMessage(Response $response): string
    {
        $message = $response->json('Message');

        return is_string($message) && trim($message) !== ''
            ? trim($message)
            : "The live prayer-time service rejected the update (HTTP {$response->status()}).";
    }

    private function reconcileUncertainWrite(
        array $current,
        array $changes,
    ): array {
        try {
            $verified = $this->current(
                (string) ($current['AreaID'] ?? ''),
                (string) ($current['MasjidID'] ?? ''),
                fresh: true,
            );
        } catch (AwqatMasjidNotFoundException|AwqatUpstreamException) {
            throw new AwqatUpstreamException(
                'The update connection ended before confirmation.',
                uncertain: true,
            );
        }

        $verifiedTimes = $this->publicTimes($verified);
        $matches = collect($changes)->every(
            fn (string $time, string $prayer): bool => ($verifiedTimes[$prayer] ?? null) === $time
        );
        if (! $matches) {
            throw new AwqatUpstreamException(
                'The update connection ended before confirmation.',
                uncertain: true,
            );
        }

        return $verified;
    }

    private function usableCache(mixed $cached, ?int $maxAgeSeconds): bool
    {
        if (! is_array($cached)
            || ! is_int($cached['fetched_at'] ?? null)
            || ! is_array($cached['rows'] ?? null)) {
            return false;
        }

        return $maxAgeSeconds === null
            || now()->timestamp - $cached['fetched_at'] <= $maxAgeSeconds;
    }

    private function updateCachedRecord(string $areaId, array $record): void
    {
        if ($areaId === '') {
            return;
        }

        $cacheKey = $this->cacheKey($areaId);
        $cache = Cache::store((string) config('awqat.cache_store', 'file'));
        $cached = $cache->get($cacheKey);
        if (! $this->usableCache($cached, null)) {
            return;
        }

        foreach ($cached['rows'] as $index => $row) {
            if (is_array($row)
                && (string) ($row['MasjidID'] ?? '') === (string) ($record['MasjidID'] ?? '')) {
                $cached['rows'][$index] = $record;
                $cache->put(
                    $cacheKey,
                    $cached,
                    max(
                        1,
                        (int) config('awqat.read_cache_ttl', 300)
                            - (now()->timestamp - $cached['fetched_at']),
                    ),
                );

                return;
            }
        }
    }

    private function cacheKey(string $areaId): string
    {
        $date = now('Pacific/Auckland')->format('Y-m-d');

        return 'awqat:area:'.hash('sha256', $areaId.'|'.$date);
    }

    private function url(string $pathKey): string
    {
        return rtrim((string) config('awqat.base_url'), '/')
            .'/'
            .ltrim((string) config("awqat.$pathKey"), '/');
    }
}
