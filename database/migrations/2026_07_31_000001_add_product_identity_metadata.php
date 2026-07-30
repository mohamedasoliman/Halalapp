<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $addBrand = ! Schema::hasColumn('products', 'brand');
        $addCountry = ! Schema::hasColumn('products', 'country');
        if ($addBrand || $addCountry) {
            Schema::table('products', function (Blueprint $table) use ($addBrand, $addCountry) {
                if ($addBrand) {
                    $table->string('brand', 250)->nullable()->after('product_name');
                }
                if ($addCountry) {
                    $table->string('country', 250)->nullable()->after('category');
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $dropCountry = Schema::hasColumn('products', 'country');
        $dropBrand = Schema::hasColumn('products', 'brand');
        if ($dropCountry || $dropBrand) {
            Schema::table('products', function (Blueprint $table) use ($dropBrand, $dropCountry) {
                if ($dropCountry) {
                    $table->dropColumn('country');
                }
                if ($dropBrand) {
                    $table->dropColumn('brand');
                }
            });
        }
    }
};
