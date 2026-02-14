<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('masjids', function (Blueprint $table) {
            if (!Schema::hasColumn('masjids', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('resturants', function (Blueprint $table) {
            if (!Schema::hasColumn('resturants', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('masjids', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('resturants', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
