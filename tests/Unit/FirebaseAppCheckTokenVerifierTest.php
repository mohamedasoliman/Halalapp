<?php

namespace Tests\Unit;

use App\Services\Security\FirebaseAppCheckTokenVerifier;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use UnexpectedValueException;

class FirebaseAppCheckTokenVerifierTest extends TestCase
{
    private string $privateKey;

    /**
     * @var array<string, mixed>
     */
    private array $keySet;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'app_check.project_number' => '952667093663',
            'app_check.allowed_app_ids' => [
                '1:952667093663:android:allowed',
            ],
            'app_check.jwks_url' => 'https://firebaseappcheck.test/v1/jwks',
            'app_check.jwks_cache_ttl' => 21600,
            'app_check.jwks_stale_ttl' => 604800,
        ]);
        Cache::clear();

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($key);
        $privateKey = '';
        $this->assertTrue(openssl_pkey_export($key, $privateKey));
        $this->privateKey = $privateKey;

        $details = openssl_pkey_get_details($key);
        $this->assertIsArray($details);
        $this->keySet = [
            'keys' => [[
                'kty' => 'RSA',
                'kid' => 'test-key',
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => $this->base64UrlEncode($details['rsa']['n']),
                'e' => $this->base64UrlEncode($details['rsa']['e']),
            ]],
        ];
    }

    public function test_it_verifies_and_caches_a_valid_app_check_token(): void
    {
        Http::fake([
            'https://firebaseappcheck.test/v1/jwks' => Http::response($this->keySet),
        ]);
        $token = $this->token();
        $verifier = app(FirebaseAppCheckTokenVerifier::class);

        $this->assertSame('1:952667093663:android:allowed', $verifier->verify($token));
        $this->assertSame('1:952667093663:android:allowed', $verifier->verify($token));
        Http::assertSentCount(1);
    }

    public function test_it_rejects_a_token_for_an_unapproved_app(): void
    {
        Http::fake([
            'https://firebaseappcheck.test/v1/jwks' => Http::response($this->keySet),
        ]);

        $this->expectException(UnexpectedValueException::class);
        app(FirebaseAppCheckTokenVerifier::class)->verify(
            $this->token(['sub' => '1:952667093663:android:other'])
        );
    }

    public function test_it_rejects_a_token_for_another_firebase_project(): void
    {
        Http::fake([
            'https://firebaseappcheck.test/v1/jwks' => Http::response($this->keySet),
        ]);

        $this->expectException(UnexpectedValueException::class);
        app(FirebaseAppCheckTokenVerifier::class)->verify(
            $this->token(['aud' => ['projects/111111111111']])
        );
    }

    public function test_it_can_use_recent_cached_keys_during_a_jwks_outage(): void
    {
        Cache::put('firebase-app-check:jwks:stale', $this->keySet, now()->addDay());
        Http::fake([
            'https://firebaseappcheck.test/v1/jwks' => Http::response([], 503),
        ]);

        $this->assertSame(
            '1:952667093663:android:allowed',
            app(FirebaseAppCheckTokenVerifier::class)->verify($this->token())
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function token(array $overrides = []): string
    {
        $now = time();
        $claims = array_merge([
            'iss' => 'https://firebaseappcheck.googleapis.com/952667093663',
            'aud' => ['projects/952667093663'],
            'sub' => '1:952667093663:android:allowed',
            'iat' => $now - 5,
            'exp' => $now + 3600,
        ], $overrides);

        return JWT::encode($claims, $this->privateKey, 'RS256', 'test-key');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
