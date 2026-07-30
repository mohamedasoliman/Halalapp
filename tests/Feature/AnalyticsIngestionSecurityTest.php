<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AnalyticsIngestionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AnalyticsIngestionSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        $eventsMigration = require database_path('migrations/2026_07_12_000001_create_analytics_events_table.php');
        $summariesMigration = require database_path('migrations/2026_07_12_000002_create_analytics_daily_summaries_table.php');
        $eventsMigration->up();
        $summariesMigration->up();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_unknown_event_names_are_rejected(): void
    {
        $this->expectException(ValidationException::class);

        (new AnalyticsIngestionController)->store(
            Request::create('/api/analytics/events', 'POST', [
                'events' => [$this->event(['event_name' => 'attacker_created_event'])],
            ])
        );
    }

    public function test_barcodes_and_other_sensitive_properties_are_not_stored(): void
    {
        $response = (new AnalyticsIngestionController)->store(
            Request::create('/api/analytics/events', 'POST', [
                'events' => [$this->event([
                    'event_name' => 'barcode_scanned',
                    'properties' => [
                        'barcode' => '9401234567890',
                        'product_name' => 'Safe Product',
                        'found' => true,
                    ],
                ])],
            ])
        );

        $this->assertSame(202, $response->getStatusCode());

        $properties = json_decode(
            DB::table('analytics_events')->value('properties'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $this->assertArrayNotHasKey('barcode', $properties);
        $this->assertSame('Safe Product', $properties['product_name']);
        $this->assertTrue($properties['found']);
    }

    private function event(array $overrides = []): array
    {
        return array_merge([
            'event_uuid' => (string) Str::uuid(),
            'anonymous_id' => (string) Str::uuid(),
            'session_id' => (string) Str::uuid(),
            'event_name' => 'app_launched',
            'occurred_at' => now()->toIso8601String(),
            'platform' => 'android',
            'app_version' => '10.2.5+50',
            'properties' => [],
        ], $overrides);
    }
}
