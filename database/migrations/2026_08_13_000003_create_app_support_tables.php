<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->nullable()->unique();
            $table->string('mailbox_address', 320);
            $table->string('source', 30)->default('mailbox')->index();
            $table->uuid('client_submission_uuid')->nullable()->unique();
            $table->char('payload_hash', 64)->nullable();
            $table->text('first_message_id')->nullable();
            $table->char('first_message_id_hash', 64)->nullable()->unique();
            $table->string('requester_name', 255)->nullable();
            $table->string('requester_email', 320);
            // Keep the full address for audit/display, but index only its
            // fixed-width digest for MySQL 5.7/utf8mb4 portability.
            $table->string('normalized_requester_email', 320);
            $table->char('normalized_requester_email_hash', 64)->index();
            $table->string('subject', 500);
            $table->text('summary')->nullable();
            $table->string('category', 40)->default('general_inquiry')->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('status', 30)->default('new')->index();
            // Production's legacy admins.id is a signed int while fresh
            // installs use an unsigned bigint. Keep portable audit IDs without
            // a cross-version FK dependency on that table.
            $table->bigInteger('assigned_to')->nullable()->index();
            $table->string('submission_context_type', 40)->nullable();
            $table->string('submission_context_id', 255)->nullable();
            $table->string('submission_context_label', 255)->nullable();
            $table->string('submitted_barcode', 14)->nullable();
            $table->string('linked_entity_type', 40)->nullable();
            $table->string('linked_entity_id', 100)->nullable();
            $table->string('linked_barcode', 20)->nullable();
            $table->string('proposed_handoff', 60)->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority', 'received_at'], 'support_tickets_queue_index');
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->string('direction', 30)->index();
            $table->text('message_id')->nullable();
            $table->char('message_id_hash', 64)->nullable()->unique();
            $table->uuid('client_submission_uuid')->nullable()->unique();
            $table->string('from_name', 255)->nullable();
            $table->string('from_address', 320);
            $table->string('to_address', 320);
            $table->string('subject', 500);
            $table->longText('body');
            $table->text('in_reply_to')->nullable();
            $table->char('in_reply_to_hash', 64)->nullable()->index();
            $table->json('references_header')->nullable();
            $table->json('raw_headers')->nullable();
            $table->bigInteger('created_by')->nullable()->index();
            $table->string('approval_reference', 500)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('drafted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['support_ticket_id', 'created_at'], 'support_messages_ticket_time_index');
        });

        Schema::create('support_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('support_message_id')->constrained('support_messages')->cascadeOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path', 1000);
            $table->string('original_name', 500);
            $table->string('mime_type', 255)->nullable();
            $table->string('security_status', 30)->default('pending_review')->index();
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->timestamps();

            $table->unique(['support_message_id', 'sha256'], 'support_attachment_message_hash_unique');
            $table->index(['support_ticket_id', 'sha256'], 'support_attachment_ticket_hash_index');
            $table->index('created_at');
        });

        Schema::create('support_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('support_message_id')->nullable()->constrained('support_messages')->nullOnDelete();
            $table->string('kind', 40)->index();
            $table->char('event_key', 64)->unique();
            $table->string('event_reference', 500);
            $table->string('mailer', 60);
            $table->string('recipient_address', 320);
            $table->string('normalized_recipient_address', 320);
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('approval_reference', 500)->nullable();
            $table->text('transport_message_id')->nullable();
            $table->char('transport_message_id_hash', 64)->nullable()->unique();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('uncertain_at')->nullable();
            $table->string('reconciliation_outcome', 30)->nullable();
            $table->text('reconciliation_reason')->nullable();
            $table->bigInteger('reconciled_by')->nullable()->index();
            $table->timestamp('reconciled_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['support_ticket_id', 'status'], 'support_deliveries_ticket_status_index');
        });

        Schema::create('support_ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->string('event_type', 50)->index();
            $table->bigInteger('actor_admin_id')->nullable()->index();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->text('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_events');
        Schema::dropIfExists('support_deliveries');
        Schema::dropIfExists('support_attachments');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
