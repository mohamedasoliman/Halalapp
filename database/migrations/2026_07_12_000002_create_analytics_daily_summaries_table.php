<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('summary_date')->index();
            $table->string('event_name', 100);
            $table->string('entity_type', 30)->default('');
            $table->string('entity_key', 191)->default('');
            $table->string('entity_label', 191)->default('');
            $table->string('dimension_key', 50)->default('');
            $table->string('dimension_value', 191)->default('');
            $table->unsignedBigInteger('event_count')->default(0);
            $table->timestamps();

            $table->unique([
                'summary_date',
                'event_name',
                'entity_type',
                'entity_key',
                'dimension_key',
                'dimension_value',
            ], 'analytics_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_summaries');
    }
};
