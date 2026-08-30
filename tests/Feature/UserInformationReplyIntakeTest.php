<?php

namespace Tests\Feature;

use App\Models\PrioritisationRequest;
use App\Models\RequestNotificationDelivery;
use App\Models\UserInformationReply;
use App\Models\UserInformationReplyAttachment;
use App\Services\UserInformationReplyAttachmentService;
use App\Services\UserInformationReplyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserInformationReplyIntakeTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'UTC',
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'prioritisation.mailbox_address' => 'products@halalkiwi.com',
            'prioritisation.attachment_max_bytes' => 5 * 1024 * 1024,
            'prioritisation.attachment_max_count' => 12,
            'prioritisation.attachment_total_max_bytes' => 60 * 1024 * 1024,
            'prioritisation.attachment_max_dimension' => 4096,
            'prioritisation.attachment_daily_per_email_count' => 20,
            'prioritisation.attachment_daily_per_email_bytes' => 60 * 1024 * 1024,
            'prioritisation.attachment_daily_global_count' => 100,
            'prioritisation.attachment_daily_global_bytes' => 500 * 1024 * 1024,
            'prioritisation.attachment_min_free_bytes' => 1,
        ]);
        DB::purge('sqlite');
        $this->createTables();

        Storage::fake('local');
        $this->temporaryDirectory = storage_path('framework/testing/user-information-replies-'.uniqid());
        File::ensureDirectoryExists($this->temporaryDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryDirectory);
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_preview_with_a_valid_attachment_performs_no_writes(): void
    {
        $request = $this->request();
        $delivery = $this->delivery($request);
        $input = $this->writeInput($delivery);
        $photo = $this->writeJpeg('preview.jpg');

        $this->artisan('requests:record-information-reply', [
            '--input' => $input,
            '--attachment' => [$photo],
        ])->assertSuccessful()->expectsOutputToContain('Preview complete');

        $this->assertDatabaseCount('user_information_replies', 0);
        $this->assertDatabaseCount('user_information_reply_attachments', 0);
        $this->assertDatabaseCount('prioritisation_request_photos', 0);
        $this->assertSame(0, $request->fresh()->information_reply_count);
        $this->assertNull($request->fresh()->information_received_at);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_exact_outbound_message_id_capture_promotes_a_decoded_jpeg(): void
    {
        $request = $this->request();
        $delivery = $this->delivery($request);
        $input = $this->writeInput($delivery);
        $photo = $this->writeJpeg('front.jpg', 80, 60);

        $this->record($input, [$photo])->assertSuccessful();

        $reply = UserInformationReply::with('attachments.photo')->sole();
        $attachment = $reply->attachments->sole();
        $this->assertSame($request->id, $reply->request_id);
        $this->assertSame($delivery->id, $reply->request_notification_delivery_id);
        $this->assertSame('outbound_message_id', $reply->match_method);
        $this->assertSame('pending_review', $reply->processing_status);
        $this->assertSame('accepted_photo', $attachment->security_status);
        $this->assertNotNull($attachment->prioritisation_request_photo_id);
        $this->assertSame('image/jpeg', $attachment->photo->mime_type);
        $this->assertSame('user_information_reply', $attachment->photo->source);
        Storage::disk('local')->assertExists($attachment->path);
        Storage::disk('local')->assertExists($attachment->photo->path);
        $this->assertSame(1, $request->fresh()->information_reply_count);
        $this->assertNotNull($request->fresh()->information_received_at);
        $this->assertSame($attachment->photo->path, $request->fresh()->photo_path);
    }

    public function test_duplicate_message_id_and_payload_are_idempotent_and_do_not_increment_the_count(): void
    {
        $request = $this->request();
        $delivery = $this->delivery($request);
        $input = $this->writeInput($delivery);
        $photo = $this->writeJpeg('duplicate.jpg');

        $this->record($input, [$photo])->assertSuccessful();
        $this->record($input, [$photo])->assertSuccessful()->expectsOutputToContain('Duplicate Message-ID');

        $this->assertDatabaseCount('user_information_replies', 1);
        $this->assertDatabaseCount('user_information_reply_attachments', 1);
        $this->assertDatabaseCount('prioritisation_request_photos', 1);
        $this->assertSame(1, $request->fresh()->information_reply_count);
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    public function test_exact_thread_from_an_unrecorded_sender_is_rejected(): void
    {
        $request = $this->request();
        $delivery = $this->delivery($request, recipient: 'requester@example.com');
        $input = $this->writeInput($delivery, from: 'attacker@example.net');

        $this->record($input)->assertFailed();

        $this->assertDatabaseCount('user_information_replies', 0);
        $this->assertSame(0, $request->fresh()->information_reply_count);
    }

    public function test_legacy_sender_and_barcode_match_requires_explicit_flag_then_succeeds(): void
    {
        $request = $this->request();
        $delivery = $this->delivery($request, withThreadIdentity: false);
        $input = $this->writeInput(
            $delivery,
            inReplyTo: null,
            subject: 'Re: Photos for '.$request->barcode,
            body: 'Here is the packaging for '.$request->barcode.'.',
        );

        $this->record($input)->assertFailed()->expectsOutputToContain('--allow-legacy-match');
        $this->assertDatabaseCount('user_information_replies', 0);

        $this->record($input, allowLegacy: true)->assertSuccessful();

        $reply = UserInformationReply::sole();
        $this->assertSame('legacy_sender_barcode', $reply->match_method);
        $this->assertSame('reviewed_legacy', $reply->match_confidence);
        $this->assertSame($request->id, $reply->request_id);
    }

    public function test_recording_information_for_a_resolved_request_does_not_reopen_it(): void
    {
        $request = $this->request(status: 'resolved');
        $delivery = $this->delivery($request);
        $input = $this->writeInput($delivery);

        $this->record($input)->assertSuccessful();

        $request->refresh();
        $this->assertSame('resolved', $request->status);
        $this->assertSame(1, $request->information_reply_count);
        $this->assertNotNull($request->information_received_at);
    }

    public function test_unsupported_attachment_is_kept_private_and_quarantined_without_photo_promotion(): void
    {
        $request = $this->request();
        $delivery = $this->delivery($request);
        $input = $this->writeInput($delivery);
        $attachmentPath = $this->writeFile('claim.pdf', "%PDF-1.4\nnot a product photo\n%%EOF");

        $this->record($input, [$attachmentPath])->assertSuccessful();

        $attachment = UserInformationReplyAttachment::sole();
        $this->assertSame('quarantined', $attachment->security_status);
        $this->assertNull($attachment->prioritisation_request_photo_id);
        $this->assertNotNull($attachment->rejection_reason);
        $this->assertDatabaseCount('prioritisation_request_photos', 0);
        $this->assertNull($request->fresh()->photo_path);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_terminal_disposition_closes_only_the_reply_checklist(): void
    {
        $request = $this->request();
        $delivery = $this->delivery($request);
        $this->record($this->writeInput($delivery))->assertSuccessful();
        $reply = UserInformationReply::sole();

        $this->artisan('requests:information-reply-disposition', [
            'reply' => $reply->id,
            'outcome' => 'processed',
            '--reason' => 'Reviewed the submitted packaging photos.',
        ])->assertSuccessful();

        $reply->refresh();
        $this->assertSame('processed', $reply->processing_status);
        $this->assertSame('Reviewed the submitted packaging photos.', $reply->review_notes);
        $this->assertNotNull($reply->processed_at);
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_message_id_claimed_by_manufacturer_flow_cannot_be_recorded_as_user_information(): void
    {
        $request = $this->request();
        $delivery = $this->delivery($request);
        $messageId = '<manufacturer-owned@example.com>';
        DB::table('brand_communications')->insert([
            'email_message_id' => $messageId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $input = $this->writeInput($delivery, messageId: $messageId);

        $this->record($input)->assertFailed()->expectsOutputToContain('manufacturer-reply flow');

        $this->assertDatabaseCount('user_information_replies', 0);
        $this->assertSame(0, $request->fresh()->information_reply_count);
    }

    public function test_capture_does_not_change_product_verdict_or_brand_evidence(): void
    {
        $request = $this->request();
        $delivery = $this->delivery($request);
        DB::table('products')->insert([
            'Barcode' => $request->barcode,
            'product_name' => 'Protected Product',
            'halal_status' => 2,
            'notes' => 'Existing note',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('brands')->insert([
            'name' => 'Protected Brand',
            'response' => 'Existing response',
            'response_scope' => 'exact product',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->record($this->writeInput($delivery))->assertSuccessful();

        $this->assertSame(2, DB::table('products')->value('halal_status'));
        $this->assertSame('Existing note', DB::table('products')->value('notes'));
        $this->assertSame('Existing response', DB::table('brands')->value('response'));
        $this->assertSame('exact product', DB::table('brands')->value('response_scope'));
        $this->assertDatabaseCount('brand_communications', 0);
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_terminal_reply_cannot_receive_new_unreviewed_attachments_on_replay(): void
    {
        $request = $this->request();
        $delivery = $this->delivery($request);
        $input = $this->writeInput($delivery);
        $this->record($input)->assertSuccessful();
        $reply = UserInformationReply::sole();
        app(UserInformationReplyService::class)->disposition($reply, 'processed', 'Reviewed without attachments.');

        $newPhoto = $this->writeJpeg('late-evidence.jpg');
        $this->record($input, [$newPhoto])
            ->assertFailed()
            ->expectsOutputToContain('already terminal');

        $this->assertDatabaseCount('user_information_reply_attachments', 0);
        $this->assertDatabaseCount('prioritisation_request_photos', 0);
        $this->assertSame(1, $request->fresh()->information_reply_count);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_attachment_changed_after_preflight_is_rejected_atomically(): void
    {
        $request = $this->request();
        $delivery = $this->delivery($request);
        $inputPath = $this->writeInput($delivery);
        $photo = $this->writeJpeg('swapped.jpg');
        $attachmentService = app(UserInformationReplyAttachmentService::class);
        $inspected = $attachmentService->inspectPaths([$photo]);
        File::put($photo, 'different bytes after preflight');
        $input = json_decode(File::get($inputPath), true, 64, JSON_THROW_ON_ERROR);
        $input['received_at'] = Carbon::parse($input['received_at']);

        try {
            app(UserInformationReplyService::class)->capture($input, $inspected);
            $this->fail('Changed attachment bytes must fail closed.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('changed after preflight', $exception->getMessage());
        }

        $this->assertDatabaseCount('user_information_replies', 0);
        $this->assertDatabaseCount('user_information_reply_attachments', 0);
        $this->assertSame(0, $request->fresh()->information_reply_count);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_exact_reply_can_be_captured_without_rewriting_an_uncertain_delivery(): void
    {
        $request = $this->request();
        $delivery = $this->delivery($request);
        $delivery->update(['status' => 'uncertain', 'uncertain_at' => now()]);

        $this->record($this->writeInput($delivery))->assertSuccessful();

        $this->assertSame($delivery->id, UserInformationReply::sole()->request_notification_delivery_id);
        $this->assertSame('uncertain', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->uncertain_at);
    }

    public function test_message_id_only_match_does_not_jump_to_an_unrelated_active_same_barcode_request(): void
    {
        $original = $this->request(status: 'resolved');
        $delivery = $this->delivery($original);
        $delivery->update(['reply_reference' => null]);
        $later = $this->request();

        $this->record($this->writeInput($delivery))->assertSuccessful();

        $reply = UserInformationReply::sole();
        $this->assertSame($original->id, $reply->request_id);
        $this->assertSame(1, $original->fresh()->information_reply_count);
        $this->assertSame(0, $later->fresh()->information_reply_count);
    }

    public function test_multiple_in_reply_to_parents_fail_closed_before_matching(): void
    {
        $firstRequest = $this->request();
        $firstDelivery = $this->delivery($firstRequest);
        $secondRequest = $this->request();
        $secondDelivery = $this->delivery($secondRequest);
        $input = $this->writeInput(
            $firstDelivery,
            inReplyTo: $firstDelivery->outbound_message_id.' '.$secondDelivery->outbound_message_id,
        );

        $this->record($input)
            ->assertFailed()
            ->expectsOutputToContain('more than one parent Message-ID');

        $this->assertDatabaseCount('user_information_replies', 0);
        $this->assertSame(0, $firstRequest->fresh()->information_reply_count);
        $this->assertSame(0, $secondRequest->fresh()->information_reply_count);
    }

    private function request(string $status = 'pending'): PrioritisationRequest
    {
        return PrioritisationRequest::create([
            'barcode' => '9400000000001',
            'barcode_key' => '9400000000001',
            'product_name' => 'Information Product',
            'brand_name' => 'Protected Brand',
            'user_email' => 'requester@example.com',
            'status' => $status,
            'information_reply_count' => 0,
        ]);
    }

    private function delivery(
        PrioritisationRequest $request,
        string $recipient = 'requester@example.com',
        bool $withThreadIdentity = true,
    ): RequestNotificationDelivery {
        $eventKey = hash('sha256', 'information-request:test:'.$request->id.':'.$recipient);
        $outboundMessageId = $withThreadIdentity
            ? '<hk-info-'.$request->id.'-'.substr($eventKey, 0, 16).'@halalkiwi.com>'
            : null;

        return RequestNotificationDelivery::create([
            'event_key' => $eventKey,
            'event_reference' => 'information-request:test:'.$request->id,
            'request_ids' => [$request->id],
            'notification_type' => 'information_request',
            'recipient_email' => $recipient,
            'normalized_email' => strtolower($recipient),
            'recipient_hash' => hash('sha256', strtolower($recipient)),
            'product_name' => $request->product_name,
            'barcode' => $request->barcode,
            'reply_reference' => $withThreadIdentity
                ? "HK-INFO-{$request->id}-{$request->barcode}"
                : null,
            'outbound_message_id' => $outboundMessageId,
            'outbound_message_id_hash' => $outboundMessageId ? hash('sha256', $outboundMessageId) : null,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    private function writeInput(
        RequestNotificationDelivery $delivery,
        string $from = 'requester@example.com',
        ?string $inReplyTo = '__delivery__',
        string $subject = 'Re: More information needed',
        string $body = 'Here are the requested product details.',
        ?string $messageId = null,
    ): string {
        static $sequence = 0;
        $sequence++;
        $messageId ??= '<user-information-'.$sequence.'@example.com>';
        $inReplyTo = $inReplyTo === '__delivery__' ? $delivery->outbound_message_id : $inReplyTo;
        $path = $this->temporaryDirectory.'/message-'.$sequence.'.json';
        File::put($path, json_encode([
            'mailbox_address' => 'products@halalkiwi.com',
            'delivered_to' => 'Halal Kiwi Products <products@halalkiwi.com>',
            'message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'references' => $inReplyTo ? [$inReplyTo] : [],
            'from_name' => 'Requesting User',
            'from_address' => $from,
            'subject' => $subject,
            'body' => $body,
            'received_at' => '2026-08-30T09:15:00+12:00',
            'raw_headers' => ['x-test-message' => (string) $sequence],
        ], JSON_THROW_ON_ERROR));

        return $path;
    }

    private function record(string $input, array $attachments = [], bool $allowLegacy = false)
    {
        return $this->artisan('requests:record-information-reply', array_filter([
            '--input' => $input,
            '--record' => true,
            '--since' => '2026-08-29T00:00:00+12:00',
            '--attachment' => $attachments,
            '--allow-legacy-match' => $allowLegacy ?: null,
        ], fn ($value) => $value !== null));
    }

    private function writeJpeg(string $name, int $width = 60, int $height = 40): string
    {
        $path = $this->temporaryDirectory.'/'.$name;
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 245, 210, 80));
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        return $path;
    }

    private function writeFile(string $name, string $contents): string
    {
        $path = $this->temporaryDirectory.'/'.$name;
        File::put($path, $contents);

        return $path;
    }

    private function createTables(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('Barcode', 20);
            $table->string('product_name')->nullable();
            $table->tinyInteger('halal_status')->default(2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('response')->nullable();
            $table->string('response_scope')->nullable();
            $table->timestamps();
        });
        Schema::create('brand_communications', function (Blueprint $table) {
            $table->id();
            $table->text('email_message_id')->nullable();
            $table->timestamps();
        });
        Schema::create('prioritisation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 20);
            $table->string('barcode_key', 20)->nullable();
            $table->string('product_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('status')->default('pending');
            $table->string('photo_path', 1000)->nullable();
            $table->timestamp('information_received_at')->nullable();
            $table->unsignedInteger('information_reply_count')->default(0);
            $table->timestamps();
        });
        Schema::create('prioritisation_request_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->string('path', 1000);
            $table->string('original_name', 500)->nullable();
            $table->string('mime_type', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('source', 50)->nullable();
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
            $table->string('barcode', 20);
            $table->tinyInteger('halal_status')->nullable();
            $table->string('reply_reference', 100)->nullable();
            $table->text('outbound_message_id')->nullable();
            $table->char('outbound_message_id_hash', 64)->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('uncertain_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
        Schema::create('user_information_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id')->nullable();
            $table->unsignedBigInteger('request_notification_delivery_id')->nullable();
            $table->string('mailbox_address', 320);
            $table->text('message_id');
            $table->char('message_id_hash', 64)->unique();
            $table->char('payload_hash', 64);
            $table->text('in_reply_to')->nullable();
            $table->char('in_reply_to_hash', 64)->nullable();
            $table->json('references_header')->nullable();
            $table->string('from_name', 255)->nullable();
            $table->string('from_address', 320);
            $table->string('normalized_from_address', 320);
            $table->char('normalized_from_address_hash', 64);
            $table->string('to_address', 320);
            $table->json('delivered_to')->nullable();
            $table->string('subject', 500);
            $table->longText('body');
            $table->string('barcode', 20)->nullable();
            $table->string('match_method', 40)->nullable();
            $table->string('match_confidence', 20)->nullable();
            $table->string('processing_status', 30)->default('pending_review');
            $table->json('raw_headers')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('user_information_reply_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reply_id');
            $table->unsignedBigInteger('prioritisation_request_photo_id')->nullable();
            $table->string('disk', 40)->default('local');
            $table->string('path', 1000);
            $table->string('original_name', 500);
            $table->string('declared_mime_type', 255)->nullable();
            $table->string('detected_mime_type', 255)->nullable();
            $table->string('security_status', 30)->default('pending_review');
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamps();
            $table->unique(['reply_id', 'sha256']);
        });
    }
}
