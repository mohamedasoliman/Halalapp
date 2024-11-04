<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('json2', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jsondata_id');

            $table->foreign('jsondata_id')->references('id')->on('jsondatas');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('json2');
    }
};
