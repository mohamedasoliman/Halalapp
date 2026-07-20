<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'halal_status')) {
            return;
        }

        DB::table('products')
            ->whereNull('halal_status')
            ->update(['halal_status' => '2']);

        Schema::table('products', function (Blueprint $table) {
            $table->string('halal_status')->default('2')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'halal_status')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('halal_status')->nullable()->default(null)->change();
        });
    }
};
