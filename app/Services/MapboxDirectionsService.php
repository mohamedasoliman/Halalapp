<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MapboxDirectionsService
{
    private const CACHE_VERSION = 'v1';

    /**
     * @return array{available: true, distance_meters: int, duration_seconds: int}|null
     */
    public function route(
        float $fromLat,
        float $fromLon,
        float $toLat,
        float $toLon,
    ): ?array {
        if (! config('mapbox.directions_enabled')) {
            return null;
        }

        $token = trim((string) config('mapbox.access_token'));
        if ($token === '') {
            Log::warning('Mapbox Directions proxy is enabled without a token.');

            return null;
        }

        $cacheKey = $this->cacheKey($fromLat, $fromLon, $toLat, $toLon);
        $cached = Cache::get($cacheKey);
        if ($this->isValidRoute($cached)) {
            return $cached;
        }

        if (! $this->reserveMonthlyRequest()) {
            Log::notice('Mapbox Directions monthly safety ceiling reached.');

            return null;
        }

        $coordinates = sprintf(
            '%.6F,%.6F;%.6F,%.6F',
            $fromLon,
            $fromLat,
            $toLon,
            $toLat,
        );
        $endpoint = rtrim(
            (string) config('mapbox.directions_endpoint'),
            '/'
        ).'/'.$coordinates;

        try {
            $response = Http::acceptJson()
                ->connectTimeout(max(1, (int) config('mapbox.connect_timeout', 4)))
                ->timeout(max(1, (int) config('mapbox.timeout', 10)))
                ->get($endpoint, [
                    'access_token' => $token,
                    'alternatives' => 'false',
                    'overview' => 'false',
                    'steps' => 'false',
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Mapbox Directions connection failed.', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Mapbox Directions returned an unsuccessful response.', [
                'status' => $response->status(),
            ]);

            return null;
        }

        $body = $response->json();
        $firstRoute = is_array($body)
            && ($body['code'] ?? null) === 'Ok'
            && is_array($body['routes'] ?? null)
            ? ($body['routes'][0] ?? null)
            : null;
        if (! is_array($firstRoute)
            || ! is_numeric($firstRoute['distance'] ?? null)
            || ! is_numeric($firstRoute['duration'] ?? null)) {
            return null;
        }

        $route = [
            'available' => true,
            'distance_meters' => (int) round((float) $firstRoute['distance']),
            'duration_seconds' => (int) round((float) $firstRoute['duration']),
        ];
        Cache::put(
            $cacheKey,
            $route,
            max(60, (int) config('mapbox.route_cache_ttl', 900)),
        );

        return $route;
    }

    private function reserveMonthlyRequest(): bool
    {
        $limit = max(0, (int) config(
            'mapbox.directions_monthly_limit',
            90000,
        ));
        if ($limit === 0) {
            return false;
        }

        $now = CarbonImmutable::now('UTC');
        $periodStart = $this->billingPeriodStart($now)->toDateString();
        DB::table('mapbox_direction_usage')->insertOrIgnore([
            'period_start' => $periodStart,
            'request_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::transaction(function () use ($limit, $now, $periodStart) {
            $counter = DB::table('mapbox_direction_usage')
                ->where('period_start', $periodStart)
                ->lockForUpdate()
                ->first();
            $count = (int) ($counter->request_count ?? 0);
            if ($count >= $limit) {
                return false;
            }

            DB::table('mapbox_direction_usage')
                ->where('period_start', $periodStart)
                ->update([
                    'request_count' => $count + 1,
                    'last_request_at' => $now,
                    'updated_at' => $now,
                ]);

            return true;
        }, 3);
    }

    private function billingPeriodStart(
        CarbonImmutable $now,
    ): CarbonImmutable {
        $configuredDay = min(
            31,
            max(1, (int) config('mapbox.billing_cycle_day', 1)),
        );
        $candidate = $now->startOfMonth()->day(
            min($configuredDay, $now->daysInMonth),
        );
        if ($now->greaterThanOrEqualTo($candidate)) {
            return $candidate;
        }

        $previousMonth = $now->subMonthNoOverflow()->startOfMonth();

        return $previousMonth->day(
            min($configuredDay, $previousMonth->daysInMonth),
        );
    }

    private function cacheKey(
        float $fromLat,
        float $fromLon,
        float $toLat,
        float $toLon,
    ): string {
        $roundedCoordinates = sprintf(
            '%.3F|%.3F|%.3F|%.3F',
            $fromLat,
            $fromLon,
            $toLat,
            $toLon,
        );

        return 'mapbox-directions:'.self::CACHE_VERSION.':'
            .hash('sha256', $roundedCoordinates);
    }

    private function isValidRoute(mixed $route): bool
    {
        return is_array($route)
            && ($route['available'] ?? null) === true
            && is_numeric($route['distance_meters'] ?? null)
            && is_numeric($route['duration_seconds'] ?? null);
    }
}
