<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MasjidTimingCorrectionTest extends TestCase
{
    private const API_KEY = 'test-mobile-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.api_key' => self::API_KEY,
            'app.key' => 'base64:test-application-key',
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'mobile_api.require_version' => false,
            'awqat.base_url' => 'http://awqat.test',
            'awqat.read_path' => '/read',
            'awqat.update_path' => '/update',
            'awqat.cache_store' => 'array',
            'awqat.read_cache_ttl' => 300,
            'awqat.correction_cache_max_age' => 120,
        ]);
        DB::purge('sqlite');

        Schema::create('masjids', function (Blueprint $table) {
            $table->id();
            $table->string('Masjid_name');
            $table->string('Address');
            $table->string('Area_id');
            $table->string('Area_name')->nullable();
            $table->string('Website');
            $table->string('Fajar')->nullable();
            $table->string('Duhur')->nullable();
            $table->string('Asr')->nullable();
            $table->string('Maghrib')->nullable();
            $table->string('Ishaa')->nullable();
            $table->string('Jumaa')->nullable();
            $table->string('Latitude')->nullable();
            $table->string('Longitude')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('masjid_timing_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('masjid_id', 32);
            $table->string('area_id', 32);
            $table->string('masjid_name');
            $table->string('status', 32);
            $table->json('original_times')->nullable();
            $table->json('submitted_changes');
            $table->json('verified_times')->nullable();
            $table->char('request_fingerprint', 64);
            $table->char('install_fingerprint', 64)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });

        DB::table('masjids')->insert([
            'Masjid_name' => 'Hendon Musallah',
            'Address' => '82 Hendon Avenue',
            'Area_id' => '13',
            'Area_name' => 'Auckland',
            'Website' => '16135',
            'Latitude' => '-36.88',
            'Longitude' => '174.69',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_live_times_are_relayed_without_exposing_the_admin_identifier(): void
    {
        Http::fake([
            'http://awqat.test/read*' => Http::response([
                'Success' => 'true',
                'ResultData' => [$this->awqatRecord('06:20 AM')],
            ]),
        ]);

        $response = $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/masjid/timings', [
                'masjid_id' => '16135',
                'area_id' => '13',
            ])
            ->assertOk()
            ->assertJsonPath('data.masjid_name', 'Hendon Musallah')
            ->assertJsonPath('data.times.fajr', '06:20 AM')
            ->assertJsonPath('data.times.jumma', '01:20 PM');

        $this->assertStringNotContainsString('54908', $response->getContent());
    }

    public function test_a_warm_confirmed_correction_publishes_without_redundant_reads(): void
    {
        $readCount = 0;

        Http::fake(function (Request $request) use (&$readCount) {
            if ($request->method() === 'GET') {
                $readCount++;

                return Http::response([
                    'Success' => 'true',
                    'ResultData' => [
                        $this->awqatRecord('06:20 AM'),
                    ],
                ]);
            }

            return Http::response([
                'Success' => 'true',
                'Message' => 'Update done',
            ]);
        });

        $headers = [
            'X-API-Key' => self::API_KEY,
            'X-Install-ID' => '1de95843-f630-4f5d-9f48-c1acde55fb5c',
        ];

        $this->withHeaders($headers)
            ->postJson('/api/masjid/timings', [
                'masjid_id' => '16135',
                'area_id' => '13',
            ])
            ->assertOk()
            ->assertJsonPath('data.times.fajr', '06:20 AM');

        $response = $this->withHeaders([
            'X-API-Key' => self::API_KEY,
            'X-Install-ID' => '1de95843-f630-4f5d-9f48-c1acde55fb5c',
        ])->postJson('/api/masjid/timings/correct', [
            'masjid_id' => '16135',
            'area_id' => '13',
            'confirmed' => true,
            'current_times' => $this->publicTimes('06:20 AM'),
            'changes' => ['fajr' => '06:15 AM'],
        ])->assertOk()
            ->assertJsonPath('data.times.fajr', '06:15 AM')
            ->assertJsonPath('data.times.zohar', '01:15 PM');

        $this->assertStringNotContainsString('54908', $response->getContent());

        $this->withHeaders($headers)
            ->postJson('/api/masjid/timings', [
                'masjid_id' => '16135',
                'area_id' => '13',
            ])
            ->assertOk()
            ->assertJsonPath('data.times.fajr', '06:15 AM');

        $this->assertSame(1, $readCount);
        Http::assertSentCount(2);

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || $request->url() !== 'http://awqat.test/update') {
                return false;
            }

            $data = $request->data();

            return $data['MasjidAdminID'] === '54908'
                && $data['MasjidID'] === '16135'
                && $data['AzaanFajr'] === '05:50 AM'
                && $data['JamaatFajr'] === '06:15 AM'
                && $data['JamaatZohar'] === '01:15 PM'
                && $data['JamaatAsr'] === '05:00 PM'
                && $data['JamaatMagrib'] === '08:10 PM'
                && $data['JamaatIsha'] === '09:30 PM'
                && $data['KhutbaJumma'] === '01:20 PM'
                && $data['JamaatEid'] === '08:00 AM';
        });

        $audit = DB::table('masjid_timing_corrections')->first();
        $this->assertSame('published_acknowledged', $audit->status);
        $this->assertSame(
            ['fajr' => '06:15 AM'],
            json_decode($audit->submitted_changes, true),
        );
        $this->assertNull($audit->verified_times);
        $this->assertSame(64, strlen($audit->request_fingerprint));
        $this->assertSame(64, strlen($audit->install_fingerprint));
    }

    public function test_repeated_reads_use_the_shared_area_cache(): void
    {
        $readCount = 0;

        Http::fake(function () use (&$readCount) {
            $readCount++;

            return Http::response([
                'Success' => 'true',
                'ResultData' => [$this->awqatRecord('06:20 AM')],
            ]);
        });

        $payload = [
            'masjid_id' => '16135',
            'area_id' => '13',
        ];

        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/masjid/timings', $payload)
            ->assertOk();
        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/masjid/timings', $payload)
            ->assertOk();

        $this->assertSame(1, $readCount);
        Http::assertSentCount(1);
    }

    public function test_a_stale_schedule_is_rejected_before_any_write(): void
    {
        Http::fake([
            'http://awqat.test/read*' => Http::response([
                'Success' => 'true',
                'ResultData' => [$this->awqatRecord('06:10 AM')],
            ]),
        ]);

        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/masjid/timings/correct', [
                'masjid_id' => '16135',
                'area_id' => '13',
                'confirmed' => true,
                'current_times' => $this->publicTimes('06:20 AM'),
                'changes' => ['fajr' => '06:15 AM'],
            ])
            ->assertConflict()
            ->assertJsonPath('data.times.fajr', '06:10 AM');

        Http::assertNotSent(
            fn (Request $request): bool => $request->method() === 'POST'
        );
        $this->assertDatabaseHas('masjid_timing_corrections', [
            'masjid_id' => '16135',
            'status' => 'conflict',
        ]);
    }

    public function test_confirmation_valid_times_and_a_halal_kiwi_masjid_are_required(): void
    {
        Http::fake();

        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/masjid/timings/correct', [
                'masjid_id' => '16135',
                'area_id' => '13',
                'confirmed' => false,
                'current_times' => $this->publicTimes('06:20 AM'),
                'changes' => ['fajr' => '25:99 PM'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirmed', 'changes.fajr']);

        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/masjid/timings', [
                'masjid_id' => '999999',
                'area_id' => '13',
            ])
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_the_existing_mobile_api_key_is_required(): void
    {
        Http::fake();

        $this->postJson('/api/masjid/timings/correct', [
            'masjid_id' => '16135',
            'area_id' => '13',
            'confirmed' => true,
            'current_times' => $this->publicTimes('06:20 AM'),
            'changes' => ['fajr' => '06:15 AM'],
        ])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_repeated_correction_attempts_are_rate_limited_per_masjid(): void
    {
        Http::fake();
        $payload = [
            'masjid_id' => '16135',
            'area_id' => '13',
            'confirmed' => false,
            'current_times' => $this->publicTimes('06:20 AM'),
            'changes' => ['fajr' => '06:15 AM'],
        ];

        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/masjid/timings/correct', $payload)
            ->assertUnprocessable();
        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/masjid/timings/correct', $payload)
            ->assertUnprocessable();
        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/masjid/timings/correct', $payload)
            ->assertTooManyRequests();

        Http::assertNothingSent();
    }

    private function awqatRecord(string $fajr): array
    {
        return [
            'MasjidAdminID' => '54908',
            'MasjidID' => '16135',
            'MasjidName' => 'Hendon Musallah',
            'AreaID' => '13',
            'AzaanFajr' => '05:50 AM',
            'AzaanZohar' => '01:00 PM',
            'AzaanAsr' => '04:45 PM',
            'AzaanMagrib' => '08:05 PM',
            'AzaanIsha' => '09:15 PM',
            'AzaanJumma' => '01:10 PM',
            'JamaatFajr' => $fajr,
            'JamaatZohar' => '01:15 PM',
            'JamaatAsr' => '05:00 PM',
            'JamaatMagrib' => '08:10 PM',
            'JamaatIsha' => '09:30 PM',
            'KhutbaJumma' => '01:20 PM',
            'JamaatEid' => '08:00 AM',
        ];
    }

    private function publicTimes(string $fajr): array
    {
        return [
            'fajr' => $fajr,
            'zohar' => '01:15 PM',
            'asr' => '05:00 PM',
            'magrib' => '08:10 PM',
            'isha' => '09:30 PM',
            'jumma' => '01:20 PM',
        ];
    }
}
