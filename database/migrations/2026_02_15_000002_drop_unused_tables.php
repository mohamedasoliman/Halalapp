<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'ajax_modules',
        'shops',
        'sliders',
        'coupon_codes',
        'brands',
        'shop_inquiries',
        'meta_fields',
        'orders',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // These were empty stub tables — no need to recreate
    }
};
