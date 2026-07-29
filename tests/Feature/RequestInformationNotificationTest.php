<?php

namespace Tests\Feature;

use App\Mail\UserNotificationEmail;
use App\Models\PrioritisationRequest;
use App\Models\RequestNotificationDelivery;
use App\Models\RequestWatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RequestInformationNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'outreach.reply_to' => 'products@halalkiwi.com',
        ]);
        DB::purge('sqlite');

        $this->createTables();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_information_request_has_a_complete_template_and_reply_address(): void
    {
        $mail = (new UserNotificationEmail(
            UserNotificationEmail::TYPE_INFORMATION_REQUEST,
            'Example Product',
            '9400000000001',
        ))->build();

        $body = $mail->render();

        $this->assertSame('More information needed: Example Product', $mail->subject);
        $this->assertStringContainsString('front of the product packaging', $body);
        $this->assertStringContainsString('complete ingredients, allergen and manufacturer information', $body);
        $this->assertStringContainsString('barcode, with all digits clearly visible', $body);
        $this->assertSame('products@halalkiwi.com', $mail->replyTo[0]['address']);
    }

    public function test_legacy_photo_request_uses_the_information_request_template(): void
    {
        $mail = (new UserNotificationEmail(
            UserNotificationEmail::TYPE_LEGACY_PHOTO_REQUEST,
            'Legacy Product',
            '9400000000002',
        ))->build();

        $this->assertSame('More information needed: Legacy Product', $mail->subject);
        $this->assertStringContainsString('Once received, we can continue reviewing your request.', $mail->render());
    }

    public function test_command_is_preview_only_by_default(): void
    {
        Mail::fake();
        $this->request('9400000000003', 'Preview Product', 'customer@example.com');

        $exitCode = Artisan::call('requests:request-information', [
            'barcode' => '9400000000003',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Preview complete', Artisan::output());
        $this->assertDatabaseCount('request_notification_deliveries', 0);
        Mail::assertNothingSent();
    }

    public function test_command_sends_once_to_normalized_union_and_records_delivery_state(): void
    {
        Mail::fake();
        $request = $this->request('9400000000004', 'Information Product', ' Customer@Example.com ');
        RequestWatcher::create(['request_id' => $request->id, 'user_email' => 'customer@example.COM']);
        RequestWatcher::create(['request_id' => $request->id, 'user_email' => 'watcher@example.com']);
        RequestWatcher::create(['request_id' => $request->id, 'user_email' => 'invalid-email']);
        RequestWatcher::create(['request_id' => $request->id, 'user_email' => 'anonymous@halalkiwi.com']);

        $arguments = [
            'barcode' => '9400000000004',
            '--event' => 'information-request:test:9400000000004',
            '--send' => true,
        ];
        $firstExitCode = Artisan::call('requests:request-information', $arguments);
        $secondExitCode = Artisan::call('requests:request-information', $arguments);

        $this->assertSame(0, $firstExitCode);
        $this->assertSame(0, $secondExitCode);
        $this->assertDatabaseCount('request_notification_deliveries', 2);
        $this->assertSame(2, RequestNotificationDelivery::where('status', 'sent')->count());
        $this->assertSame(
            ['customer@example.com', 'watcher@example.com'],
            RequestNotificationDelivery::orderBy('recipient_email')->pluck('recipient_email')->all(),
        );
        $this->assertTrue(
            RequestNotificationDelivery::get()
                ->every(fn (RequestNotificationDelivery $delivery) => $delivery->notification_type === 'information_request')
        );
        Mail::assertSent(UserNotificationEmail::class, 2);
        Mail::assertSent(UserNotificationEmail::class, function (UserNotificationEmail $mail) {
            return $mail->notificationType === UserNotificationEmail::TYPE_INFORMATION_REQUEST
                && str_contains($mail->render(), 'complete ingredients');
        });
    }

    public function test_event_reference_cannot_be_reused_for_a_different_product(): void
    {
        Mail::fake();
        $this->request('9400000000005', 'First Product', 'customer@example.com');
        $this->request('9400000000006', 'Second Product', 'customer@example.com');
        $eventReference = 'information-request:test:fixed-event';

        $firstExitCode = Artisan::call('requests:request-information', [
            'barcode' => '9400000000005',
            '--event' => $eventReference,
            '--send' => true,
        ]);
        $secondExitCode = Artisan::call('requests:request-information', [
            'barcode' => '9400000000006',
            '--event' => $eventReference,
            '--send' => true,
        ]);

        $this->assertSame(0, $firstExitCode);
        $this->assertSame(1, $secondExitCode);
        $this->assertStringContainsString('already assigned to a different notification', Artisan::output());
        $this->assertDatabaseCount('request_notification_deliveries', 1);
        Mail::assertSent(UserNotificationEmail::class, 1);
    }

    private function request(string $barcode, string $productName, ?string $email): PrioritisationRequest
    {
        return PrioritisationRequest::create([
            'barcode' => $barcode,
            'product_name' => $productName,
            'user_email' => $email,
            'status' => 'pending',
        ]);
    }

    private function createTables(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('Barcode');
            $table->string('product_name')->nullable();
            $table->timestamps();
        });
        Schema::create('prioritisation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 20);
            $table->string('product_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        Schema::create('request_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id');
            $table->string('user_email');
            $table->timestamps();
        });
        Schema::create('request_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->char('event_key', 64);
            $table->string('event_reference');
            $table->json('request_ids')->nullable();
            $table->unsignedBigInteger('brand_communication_id')->nullable();
            $table->string('notification_type');
            $table->string('recipient_email');
            $table->string('normalized_email');
            $table->char('recipient_hash', 64);
            $table->string('product_name');
            $table->string('barcode');
            $table->tinyInteger('halal_status')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('uncertain_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['event_key', 'recipient_hash']);
        });
    }
}
