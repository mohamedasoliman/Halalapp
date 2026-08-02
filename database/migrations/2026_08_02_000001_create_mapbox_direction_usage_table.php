<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapbox_direction_usage', function (Blueprint $table) {
            $table->date('period_start')->primary();
            $table->unsignedInteger('request_count')->default(0);
            $table->timestamp('last_request_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapbox_direction_usage');
    }
};
