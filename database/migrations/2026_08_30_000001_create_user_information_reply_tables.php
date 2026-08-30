<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_notification_deliveries', function (Blueprint $table) {
            $table->string('reply_reference', 100)->nullable()->after('event_reference');
            $table->text('outbound_message_id')->nullable()->after('reply_reference');
            $table->char('outbound_message_id_hash', 64)->nullable()->after('outbound_message_id');

            $table->index('reply_reference', 'request_notification_reply_reference_index');
            $table->unique('outbound_message_id_hash', 'request_notification_outbound_message_hash_unique');
        });

        Schema::table('prioritisation_requests', function (Blueprint $table) {
            $table->timestamp('information_received_at')->nullable()->after('photo_path');
            $table->unsignedInteger('information_reply_count')->default(0)->after('information_received_at');

            $table->index('information_received_at', 'prioritisation_information_received_index');
        });

        Schema::create('user_information_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')
                ->nullable()
                ->constrained('prioritisation_requests')
                ->nullOnDelete();
            $table->foreignId('request_notification_delivery_id')->nullable();
            $table->foreign('request_notification_delivery_id', 'uir_delivery_fk')
                ->references('id')
                ->on('request_notification_deliveries')
                ->nullOnDelete();
            $table->string('mailbox_address', 320);
            $table->text('message_id');
            $table->char('message_id_hash', 64)->unique();
            $table->char('payload_hash', 64);
            $table->text('in_reply_to')->nullable();
            $table->char('in_reply_to_hash', 64)->nullable()->index();
            $table->json('references_header')->nullable();
            $table->string('from_name', 255)->nullable();
            $table->string('from_address', 320);
            $table->string('normalized_from_address', 320);
            $table->char('normalized_from_address_hash', 64)->index();
            $table->string('to_address', 320);
            $table->json('delivered_to')->nullable();
            $table->string('subject', 500);
            $table->longText('body');
            $table->string('barcode', 20)->nullable()->index();
            $table->string('match_method', 40)->nullable();
            $table->string('match_confidence', 20)->nullable();
            $table->string('processing_status', 30)->default('pending_review')->index();
            $table->json('raw_headers')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('received_at')->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['request_id', 'received_at'], 'user_information_replies_request_received_index');
            $table->index(
                ['request_notification_delivery_id', 'received_at'],
                'user_information_replies_delivery_received_index',
            );
        });

        Schema::create('user_information_reply_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reply_id')
                ->constrained('user_information_replies')
                ->cascadeOnDelete();
            $table->foreignId('prioritisation_request_photo_id')->nullable();
            $table->foreign('prioritisation_request_photo_id', 'uira_photo_fk')
                ->references('id')
                ->on('prioritisation_request_photos')
                ->nullOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path', 1000);
            $table->string('original_name', 500);
            $table->string('declared_mime_type', 255)->nullable();
            $table->string('detected_mime_type', 255)->nullable();
            $table->string('security_status', 30)->default('pending_review')->index();
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['reply_id', 'sha256'],
                'user_information_reply_attachment_hash_unique',
            );
            $table->index(
                'prioritisation_request_photo_id',
                'user_information_reply_attachment_photo_index',
            );
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_information_reply_attachments');
        Schema::dropIfExists('user_information_replies');

        Schema::table('prioritisation_requests', function (Blueprint $table) {
            $table->dropIndex('prioritisation_information_received_index');
            $table->dropColumn(['information_received_at', 'information_reply_count']);
        });

        Schema::table('request_notification_deliveries', function (Blueprint $table) {
            $table->dropIndex('request_notification_reply_reference_index');
            $table->dropUnique('request_notification_outbound_message_hash_unique');
            $table->dropColumn([
                'reply_reference',
                'outbound_message_id',
                'outbound_message_id_hash',
            ]);
        });
    }
};
