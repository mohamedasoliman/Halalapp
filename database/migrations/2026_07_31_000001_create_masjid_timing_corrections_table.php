<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('masjid_timing_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('masjid_id', 32);
            $table->string('area_id', 32);
            $table->string('masjid_name');
            $table->string('status', 32)->default('pending');
            $table->json('original_times')->nullable();
            $table->json('submitted_changes');
            $table->json('verified_times')->nullable();
            $table->char('request_fingerprint', 64);
            $table->char('install_fingerprint', 64)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['masjid_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['request_fingerprint', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masjid_timing_corrections');
    }
};
