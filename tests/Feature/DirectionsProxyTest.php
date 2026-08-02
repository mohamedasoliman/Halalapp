<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DirectionsProxyTest extends TestCase
{
    private const API_KEY = 'test-mobile-key';

    private const INSTALL_ID = '123e4567-e89b-42d3-a456-426614174000';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.api_key' => self::API_KEY,
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'mapbox.directions_enabled' => true,
            'mapbox.access_token' => 'pk.test-server-token',
            'mapbox.directions_endpoint' => 'https://mapbox.test/directions/v5/mapbox/driving',
            'mapbox.directions_monthly_limit' => 90000,
            'mapbox.billing_cycle_day' => 2,
            'mapbox.route_cache_ttl' => 900,
            'mapbox.connect_timeout' => 1,
            'mapbox.timeout' => 2,
        ]);
        DB::purge('sqlite');
        Cache::clear();
        CarbonImmutable::setTestNow('2026-08-05 12:00:00 UTC');

        Schema::create('mapbox_direction_usage', function (Blueprint $table) {
            $table->date('period_start')->primary();
            $table->unsignedInteger('request_count')->default(0);
            $table->timestamp('last_request_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_it_requires_the_mobile_api_key(): void
    {
        Http::fake();

        $this->postJson('/api/directions', $this->validPayload())
            ->assertUnauthorized();

        Http::assertNothingSent();
        $this->assertDatabaseCount('mapbox_direction_usage', 0);
    }

    public function test_it_returns_a_lightweight_route_and_counts_one_request(): void
    {
        Http::fake([
            'https://mapbox.test/*' => Http::response([
                'code' => 'Ok',
                'routes' => [[
                    'distance' => 3900.4,
                    'duration' => 720.2,
                    'geometry' => 'must-not-be-returned',
                ]],
            ]),
        ]);

        $this->postDirections()
            ->assertOk()
            ->assertExactJson([
                'available' => true,
                'distance_meters' => 3900,
                'duration_seconds' => 720,
            ]);

        Http::assertSent(function (Request $request): bool {
            return str_starts_with(
                $request->url(),
                'https://mapbox.test/directions/v5/mapbox/driving/'
            )
                && str_contains($request->url(), '174.700000,-36.900000;174.760000,-36.850000')
                && $request['access_token'] === 'pk.test-server-token'
                && $request['alternatives'] === 'false'
                && $request['overview'] === 'false'
                && $request['steps'] === 'false';
        });
        $this->assertDatabaseHas('mapbox_direction_usage', [
            'period_start' => '2026-08-02',
            'request_count' => 1,
        ]);
    }

    public function test_cached_routes_do_not_consume_more_quota(): void
    {
        Http::fake([
            'https://mapbox.test/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 3900, 'duration' => 720]],
            ]),
        ]);

        $this->postDirections()->assertOk();
        $this->postDirections()->assertOk();

        Http::assertSentCount(1);
        $this->assertDatabaseHas('mapbox_direction_usage', [
            'period_start' => '2026-08-02',
            'request_count' => 1,
        ]);
    }

    public function test_it_fails_closed_at_the_configured_monthly_ceiling(): void
    {
        config(['mapbox.directions_monthly_limit' => 2]);
        DB::table('mapbox_direction_usage')->insert([
            'period_start' => '2026-08-02',
            'request_count' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Http::fake();

        $this->postDirections()
            ->assertOk()
            ->assertExactJson(['available' => false]);

        Http::assertNothingSent();
        $this->assertDatabaseHas('mapbox_direction_usage', [
            'period_start' => '2026-08-02',
            'request_count' => 2,
        ]);
    }

    public function test_disabled_feature_silently_falls_back(): void
    {
        config(['mapbox.directions_enabled' => false]);
        Http::fake();

        $this->postDirections()
            ->assertOk()
            ->assertExactJson(['available' => false]);

        Http::assertNothingSent();
        $this->assertDatabaseCount('mapbox_direction_usage', 0);
    }

    public function test_invalid_or_non_new_zealand_coordinates_are_rejected(): void
    {
        Http::fake();

        $this->postDirections([
            ...$this->validPayload(),
            'from_lat' => 37.785834,
            'from_lon' => -122.406417,
        ])->assertUnprocessable();

        Http::assertNothingSent();
        $this->assertDatabaseCount('mapbox_direction_usage', 0);
    }

    public function test_an_upstream_failure_falls_back_but_still_counts_the_attempt(): void
    {
        Http::fake([
            'https://mapbox.test/*' => Http::response([
                'code' => 'TemporaryError',
            ], 503),
        ]);

        $this->postDirections()
            ->assertOk()
            ->assertExactJson(['available' => false]);

        $this->assertDatabaseHas('mapbox_direction_usage', [
            'period_start' => '2026-08-02',
            'request_count' => 1,
        ]);
    }

    public function test_the_counter_uses_the_configured_billing_cycle_boundary(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 12:00:00 UTC');
        Http::fake([
            'https://mapbox.test/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 3900, 'duration' => 720]],
            ]),
        ]);

        $this->postDirections()->assertOk();

        $this->assertDatabaseHas('mapbox_direction_usage', [
            'period_start' => '2026-07-02',
            'request_count' => 1,
        ]);
    }

    /**
     * @param  array<string, float>|null  $payload
     */
    private function postDirections(?array $payload = null)
    {
        return $this->withHeaders([
            'X-API-Key' => self::API_KEY,
            'X-Install-ID' => self::INSTALL_ID,
        ])->postJson('/api/directions', $payload ?? $this->validPayload());
    }

    /**
     * @return array<string, float>
     */
    private function validPayload(): array
    {
        return [
            'from_lat' => -36.9,
            'from_lon' => 174.7,
            'to_lat' => -36.85,
            'to_lon' => 174.76,
        ];
    }
}
