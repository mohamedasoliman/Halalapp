<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('brands')) {
            Schema::create('brands', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('email')->nullable();
                $table->enum('contact_type', ['email', 'form'])->default('email');
                $table->enum('response', ['halal', 'not_halal', 'partial'])->nullable();
                $table->enum('response_scope', ['blanket', 'partial'])->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('last_contacted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('prioritisation_requests')) {
            Schema::create('prioritisation_requests', function (Blueprint $table) {
                $table->id();
                $table->string('barcode', 20)->index();
                $table->string('product_name')->nullable();
                $table->string('brand_name')->nullable();
                $table->string('user_email')->nullable();
                $table->string('user_name')->nullable();
                $table->string('photo_path', 500)->nullable();
                $table->enum('type', ['prioritise', 'new_product', 'silent'])->default('prioritise');
                $table->enum('status', ['pending', 'ready_for_outreach', 'contacted', 'ready_for_review', 'resolved'])->default('pending');
                $table->tinyInteger('resolved_status')->nullable();
                $table->text('notes')->nullable();
                $table->string('source', 50)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('brand_communications')) {
            Schema::create('brand_communications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
                $table->enum('direction', ['inbound', 'outbound']);
                $table->string('subject', 500)->nullable();
                $table->text('body_preview')->nullable();
                $table->json('barcodes_mentioned')->nullable();
                $table->text('action_taken')->nullable();
                $table->string('email_message_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('request_watchers')) {
            Schema::create('request_watchers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('request_id')->constrained('prioritisation_requests')->cascadeOnDelete();
                $table->string('user_email');
                $table->string('user_name')->nullable();
                $table->timestamps();
                $table->unique(['request_id', 'user_email']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('request_watchers');
        Schema::dropIfExists('brand_communications');
        Schema::dropIfExists('prioritisation_requests');
        Schema::dropIfExists('brands');
    }
};
