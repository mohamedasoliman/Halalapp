<?php

namespace Tests\Feature;

use App\Models\SupportAttachment;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class SupportAttachmentIntakeTest extends TestCase
{
    private const API_KEY = 'support-attachment-test-key';

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.api_key' => self::API_KEY,
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'support.mailbox_address' => 'appsupport@halalkiwi.com',
            'support.mail_enabled' => false,
            'support.attachment_max_bytes' => 5 * 1024 * 1024,
            'support.attachment_daily_per_email_count' => 5,
            'support.attachment_daily_per_email_bytes' => 15 * 1024 * 1024,
            'support.attachment_daily_global_count' => 250,
            'support.attachment_daily_global_bytes' => 500 * 1024 * 1024,
            'support.attachment_min_free_bytes' => 1,
        ]);
        DB::purge('sqlite');
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_08_13_000003_create_app_support_tables.php'))->up();

        Storage::fake('local');
        Mail::fake();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->temporaryDirectory = storage_path('framework/testing/support-attachment-intake-'.uniqid());
        File::ensureDirectoryExists($this->temporaryDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryDirectory);
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_http_storage_failure_rolls_back_every_database_row_and_cleans_the_private_file(): void
    {
        $writtenFiles = [];
        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->andReturnUsing(function (string $path) use (&$writtenFiles) {
            // Model a filesystem that wrote data but reported failure.
            $writtenFiles[$path] = true;

            return false;
        });
        $disk->shouldReceive('delete')->once()->andReturnUsing(function (string $path) use (&$writtenFiles) {
            unset($writtenFiles[$path]);

            return true;
        });
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->withHeader('X-API-Key', self::API_KEY)
            ->post('/api/contact-us', array_merge($this->appPayload(), [
                'attachment' => UploadedFile::fake()->image('failure.png'),
            ]))
            ->assertServerError();

        $this->assertSame([], $writtenFiles);
        $this->assertSame(0, SupportTicket::count());
        $this->assertSame(0, SupportMessage::count());
        $this->assertSame(0, SupportAttachment::count());
        $this->assertSame(0, DB::table('support_ticket_events')->count());
        $this->assertSame(0, DB::table('support_deliveries')->count());
        Mail::assertNothingSent();
    }

    public function test_http_attachment_row_failure_removes_the_file_and_rolls_back_capture(): void
    {
        // This fails after INSERT, before Model::create() returns. It proves
        // the outer transaction's write tracker cleans a file even while the
        // uncommitted attachment row is still visible to the current query.
        SupportAttachment::created(fn () => throw new \RuntimeException('forced attachment row failure'));
        try {
            $this->withHeader('X-API-Key', self::API_KEY)
                ->post('/api/contact-us', array_merge($this->appPayload(), [
                    'attachment' => UploadedFile::fake()->image('row-failure.png'),
                ]))
                ->assertServerError();
        } finally {
            SupportAttachment::flushEventListeners();
        }

        $this->assertSame(0, SupportTicket::count());
        $this->assertSame(0, SupportMessage::count());
        $this->assertSame(0, SupportAttachment::count());
        $this->assertSame([], Storage::disk('local')->allFiles('support'));
        Mail::assertNothingSent();
    }

    public function test_http_exact_uuid_payload_and_attachment_replay_bypasses_exhausted_quota(): void
    {
        config(['support.attachment_daily_per_email_count' => 1]);
        $payload = array_merge($this->appPayload(), [
            'attachment' => UploadedFile::fake()->image('same.png'),
        ]);

        $this->withHeader('X-API-Key', self::API_KEY)
            ->post('/api/contact-us', $payload)
            ->assertOk()
            ->assertJson(['duplicate' => false]);
        $this->withHeader('X-API-Key', self::API_KEY)
            ->post('/api/contact-us', $payload)
            ->assertOk()
            ->assertJson(['duplicate' => true]);

        $this->assertSame(1, SupportTicket::count());
        $this->assertSame(1, SupportMessage::count());
        $this->assertSame(1, SupportAttachment::count());
        $this->assertCount(1, Storage::disk('local')->allFiles('support'));
    }

    public function test_cli_preflights_complete_attachment_batch_before_capturing_a_new_message(): void
    {
        config(['support.attachment_daily_per_email_count' => 1]);
        $input = $this->writeMailboxJson('batch@example.com', 'Batch quota');
        $firstAttachment = $this->writeFile('first.txt', 'first attachment');
        $secondAttachment = $this->writeFile('second.txt', 'second attachment');

        $this->artisan('support:record-email', [
            '--input' => $input,
            '--record' => true,
            '--since' => '2026-08-12T00:00:00Z',
            '--attachment' => [$firstAttachment, $secondAttachment],
        ])->assertFailed();

        $this->assertSame(0, SupportTicket::count());
        $this->assertSame(0, SupportMessage::count());
        $this->assertSame(0, SupportAttachment::count());
        $this->assertSame([], Storage::disk('local')->allFiles('support'));
    }

    public function test_cli_exact_message_and_hash_retry_bypasses_quota_but_new_message_does_not(): void
    {
        config(['support.attachment_daily_per_email_count' => 1]);
        $input = $this->writeMailboxJson('retry@example.com', 'First import');
        $attachment = $this->writeFile('retry.txt', 'stable attachment bytes');
        $arguments = [
            '--input' => $input,
            '--record' => true,
            '--since' => '2026-08-12T00:00:00.25+00:00',
            '--attachment' => [$attachment],
        ];

        $this->artisan('support:record-email', $arguments)->assertSuccessful();
        $this->artisan('support:record-email', $arguments)->assertSuccessful();

        $secondInput = $this->writeMailboxJson('new-after-quota@example.com', 'Second import');
        $secondAttachment = $this->writeFile('new.txt', 'new attachment bytes');
        $this->artisan('support:record-email', [
            '--input' => $secondInput,
            '--record' => true,
            '--since' => '2026-08-12T00:00:00Z',
            '--attachment' => [$secondAttachment],
        ])->assertFailed();

        // Quota rejection happens before capture. If file I/O had failed after
        // capture, the command deliberately leaves that message auditable for
        // an idempotent retry instead.
        $this->assertSame(1, SupportTicket::count());
        $this->assertSame(1, SupportMessage::count());
        $this->assertSame(1, SupportAttachment::count());
        $this->assertCount(1, Storage::disk('local')->allFiles('support'));
    }

    public function test_cli_accepts_timezone_qualified_rfc3339_variants(): void
    {
        foreach ([
            '2026-08-13T10:20:30Z',
            '2026-08-13T10:20:30.1Z',
            '2026-08-13T10:20:30.123456789+12:45',
            '2026-08-13t10:20:30.25z',
        ] as $index => $receivedAt) {
            $input = $this->writeMailboxJson(
                'timestamp-'.$index.'@example.com',
                'Timestamp '.$index,
                $receivedAt,
            );

            $this->artisan('support:record-email', ['--input' => $input])->assertSuccessful();
        }

        $recordInput = $this->writeMailboxJson(
            'timestamp-record@example.com',
            'Timestamp record',
            '2026-08-13T10:20:30.123456789Z',
        );
        $this->artisan('support:record-email', [
            '--input' => $recordInput,
            '--record' => true,
            '--since' => '2026-08-12T23:59:59.5+12:00',
        ])->assertSuccessful();

        $this->assertSame('2026-08-13 10:20:30', SupportTicket::firstOrFail()->received_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_cli_rejects_ambiguous_or_timezone_less_timestamps(): void
    {
        foreach ([
            '2026-08-13',
            '2026-08-13T10:20:30',
            '2026-08-13 10:20:30Z',
            '2026-08-13T10:20:30+1200',
            '2026-02-30T10:20:30Z',
        ] as $index => $receivedAt) {
            $input = $this->writeMailboxJson(
                'invalid-timestamp-'.$index.'@example.com',
                'Invalid timestamp '.$index,
                $receivedAt,
            );

            $this->artisan('support:record-email', ['--input' => $input])->assertFailed();
        }

        $validInput = $this->writeMailboxJson('invalid-cutover@example.com', 'Invalid cutover');
        $this->artisan('support:record-email', [
            '--input' => $validInput,
            '--record' => true,
            '--since' => '2026-08-12 00:00:00',
        ])->assertFailed();

        $this->assertSame(0, SupportTicket::count());
    }

    private function appPayload(): array
    {
        return [
            'name' => 'Amina',
            'email' => 'amina@example.com',
            'subject' => 'Scanner issue',
            'body' => 'The scanner closes after opening.',
            'category' => 'bug_report',
            'submission_uuid' => '85a5c5b6-e7db-4a2f-8891-01a5c89067bb',
        ];
    }

    private function writeMailboxJson(
        string $messageLocalPart,
        string $subject,
        string $receivedAt = '2026-08-13T10:20:30Z',
    ): string {
        $path = $this->temporaryDirectory.'/'.preg_replace('/[^a-z0-9]+/i', '-', $messageLocalPart).'.json';
        File::put($path, json_encode([
            'mailbox_address' => 'appsupport@halalkiwi.com',
            'delivered_to' => 'Halal Kiwi App Support <appsupport@halalkiwi.com>',
            'message_id' => '<'.$messageLocalPart.'>',
            'from_name' => 'Customer',
            'from_address' => 'customer@example.com',
            'subject' => $subject,
            'body' => 'Mailbox message body.',
            'received_at' => $receivedAt,
        ], JSON_THROW_ON_ERROR));

        return $path;
    }

    private function writeFile(string $name, string $contents): string
    {
        $path = $this->temporaryDirectory.'/'.$name;
        File::put($path, $contents);

        return $path;
    }
}
