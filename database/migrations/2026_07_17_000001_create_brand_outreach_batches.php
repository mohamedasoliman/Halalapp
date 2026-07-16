<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('contact_research_status', 30)->default('pending')->after('contact_type');
            $table->string('contact_source', 500)->nullable()->after('contact_research_status');
            $table->timestamp('contact_verified_at')->nullable()->after('contact_source');
            $table->timestamp('next_follow_up_at')->nullable()->after('last_contacted_at');
            $table->unsignedTinyInteger('follow_up_count')->default(0)->after('next_follow_up_at');
            $table->timestamp('outreach_paused_at')->nullable()->after('follow_up_count');
        });

        // Existing email contacts were already researched manually before this workflow existed.
        DB::table('brands')
            ->where('contact_type', 'email')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where('email', 'not like', 'http%')
            ->update([
                'contact_research_status' => 'verified',
                'contact_source' => 'Existing brand record',
                'contact_verified_at' => now(),
            ]);

        Schema::create('brand_outreach_batches', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 80)->unique();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->string('kind', 30)->default('initial');
            $table->unsignedTinyInteger('follow_up_number')->default(0);
            $table->string('status', 30)->default('draft')->index();
            $table->string('recipient_email');
            $table->string('subject', 500);
            $table->json('products');
            $table->json('request_ids');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'kind', 'status']);
        });

        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_outreach_batches');

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn([
                'contact_research_status',
                'contact_source',
                'contact_verified_at',
                'next_follow_up_at',
                'follow_up_count',
                'outreach_paused_at',
            ]);
        });
    }
};
