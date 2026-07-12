<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->char('anonymous_id', 64)->index();
            $table->char('session_id', 64)->index();
            $table->string('event_name', 100)->index();
            $table->string('entity_type', 30)->default('')->index();
            $table->string('entity_key', 191)->default('')->index();
            $table->string('entity_label', 191)->default('');
            $table->json('properties')->nullable();
            $table->string('platform', 20)->default('unknown');
            $table->string('app_version', 30)->default('unknown');
            $table->dateTime('occurred_at')->index();
            $table->timestamps();

            $table->index(
                ['entity_type', 'entity_key', 'occurred_at'],
                'analytics_entity_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
