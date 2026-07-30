<?php

namespace App\Services\Security;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use stdClass;
use Throwable;
use UnexpectedValueException;

class FirebaseAppCheckTokenVerifier
{
    private const FRESH_CACHE_KEY = 'firebase-app-check:jwks:fresh';

    private const STALE_CACHE_KEY = 'firebase-app-check:jwks:stale';

    public function verify(string $token): string
    {
        if ($token === '' || strlen($token) > 8192 || substr_count($token, '.') !== 2) {
            throw new UnexpectedValueException('Invalid App Check token format.');
        }

        $projectNumber = trim((string) config('app_check.project_number', ''));
        if ($projectNumber === '' || ! ctype_digit($projectNumber)) {
            throw new RuntimeException('Firebase App Check project number is not configured.');
        }

        $headers = new stdClass;
        $claims = JWT::decode(
            $token,
            JWK::parseKeySet($this->keySet(), 'RS256'),
            $headers
        );

        if (($headers->alg ?? null) !== 'RS256' || ($headers->typ ?? null) !== 'JWT') {
            throw new UnexpectedValueException('Invalid App Check token header.');
        }

        $expectedIssuer = "https://firebaseappcheck.googleapis.com/$projectNumber";
        if (($claims->iss ?? null) !== $expectedIssuer) {
            throw new UnexpectedValueException('Invalid App Check token issuer.');
        }

        $audiences = is_array($claims->aud ?? null)
            ? $claims->aud
            : [$claims->aud ?? null];
        if (! in_array("projects/$projectNumber", $audiences, true)) {
            throw new UnexpectedValueException('Invalid App Check token audience.');
        }

        $appId = trim((string) ($claims->sub ?? ''));
        if ($appId === '') {
            throw new UnexpectedValueException('App Check token is missing its app ID.');
        }

        $allowedAppIds = array_values(array_filter(
            (array) config('app_check.allowed_app_ids', [])
        ));
        if ($allowedAppIds !== [] && ! in_array($appId, $allowedAppIds, true)) {
            throw new UnexpectedValueException('App Check token app ID is not allowed.');
        }

        return $appId;
    }

    /**
     * @return array<string, mixed>
     */
    private function keySet(): array
    {
        $fresh = Cache::get(self::FRESH_CACHE_KEY);
        if ($this->isValidKeySet($fresh)) {
            return $fresh;
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(max(1, (int) config('app_check.connect_timeout', 3)))
                ->timeout(max(1, (int) config('app_check.timeout', 6)))
                ->get((string) config('app_check.jwks_url'));

            if (! $response->successful()) {
                throw new RuntimeException(
                    'Firebase App Check JWKS request returned HTTP '.$response->status().'.'
                );
            }

            $keySet = $response->json();
            if (! $this->isValidKeySet($keySet)) {
                throw new RuntimeException('Firebase App Check returned an invalid JWKS document.');
            }

            Cache::put(
                self::FRESH_CACHE_KEY,
                $keySet,
                now()->addSeconds(max(60, (int) config('app_check.jwks_cache_ttl', 21600)))
            );
            Cache::put(
                self::STALE_CACHE_KEY,
                $keySet,
                now()->addSeconds(max(3600, (int) config('app_check.jwks_stale_ttl', 604800)))
            );

            return $keySet;
        } catch (Throwable $exception) {
            $stale = Cache::get(self::STALE_CACHE_KEY);
            if ($this->isValidKeySet($stale)) {
                Log::warning('Using cached Firebase App Check public keys.', [
                    'reason' => $exception->getMessage(),
                ]);

                return $stale;
            }

            throw new RuntimeException(
                'Unable to load Firebase App Check public keys.',
                previous: $exception
            );
        }
    }

    private function isValidKeySet(mixed $value): bool
    {
        return is_array($value)
            && isset($value['keys'])
            && is_array($value['keys'])
            && $value['keys'] !== [];
    }
}
