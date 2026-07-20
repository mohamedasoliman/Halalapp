<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $seen = [];
        foreach (DB::table('brand_communications')->whereNotNull('email_message_id')->get(['id', 'email_message_id']) as $row) {
            $messageId = strtolower(trim((string) $row->email_message_id));
            if ($messageId === '') {
                DB::table('brand_communications')->where('id', $row->id)->update(['email_message_id' => null]);

                continue;
            }

            if (isset($seen[$messageId])) {
                throw new \RuntimeException(
                    "Duplicate manufacturer email Message-ID detected on communications {$seen[$messageId]} and {$row->id}: {$messageId}"
                );
            }

            $seen[$messageId] = $row->id;
            if ($messageId !== $row->email_message_id) {
                DB::table('brand_communications')->where('id', $row->id)->update(['email_message_id' => $messageId]);
            }
        }

        Schema::table('brand_communications', function (Blueprint $table) {
            $table->text('proof_path')->nullable()->after('email_message_id');
            $table->string('processing_status', 30)->nullable()->after('proof_path');
            $table->timestamp('processed_at')->nullable()->after('processing_status');
            $table->unique('email_message_id');
        });

        Schema::table('prioritisation_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('resolution_communication_id')->nullable()->after('resolved_status');
            $table->index('resolution_communication_id');
        });

        Schema::create('request_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->char('event_key', 64);
            $table->string('event_reference', 500);
            $table->json('request_ids')->nullable();
            $table->unsignedBigInteger('brand_communication_id')->nullable()->index();
            $table->string('notification_type', 30);
            $table->string('recipient_email', 320);
            $table->string('normalized_email', 320);
            $table->char('recipient_hash', 64);
            $table->string('product_name', 500);
            $table->string('barcode', 20);
            $table->tinyInteger('halal_status')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('uncertain_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['event_key', 'recipient_hash'], 'request_notification_event_recipient_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_notification_deliveries');

        Schema::table('prioritisation_requests', function (Blueprint $table) {
            $table->dropIndex(['resolution_communication_id']);
            $table->dropColumn('resolution_communication_id');
        });

        Schema::table('brand_communications', function (Blueprint $table) {
            $table->dropUnique(['email_message_id']);
            $table->dropColumn(['proof_path', 'processing_status', 'processed_at']);
        });
    }
};
