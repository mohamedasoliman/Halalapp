<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_outreach_batches', function (Blueprint $table) {
            $table->longText('message_body')->nullable()->after('subject');
            $table->unsignedBigInteger('source_communication_id')->nullable()->after('request_ids');
            $table->string('event_reference', 500)->nullable()->after('source_communication_id');
            $table->char('event_key', 64)->nullable()->after('event_reference');
            $table->string('in_reply_to_message_id', 998)->nullable()->after('event_key');
            $table->text('reference_message_ids')->nullable()->after('in_reply_to_message_id');

            $table->index('source_communication_id', 'brand_outreach_source_communication_index');
            $table->unique('event_key', 'brand_outreach_event_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('brand_outreach_batches', function (Blueprint $table) {
            $table->dropUnique('brand_outreach_event_key_unique');
            $table->dropIndex('brand_outreach_source_communication_index');
            $table->dropColumn([
                'message_body',
                'source_communication_id',
                'event_reference',
                'event_key',
                'in_reply_to_message_id',
                'reference_message_ids',
            ]);
        });
    }
};
