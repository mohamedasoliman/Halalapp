<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE prioritisation_requests MODIFY COLUMN status ENUM('pending', 'ready_for_outreach', 'contacted', 'ready_for_review', 'resolved', 'dead_end') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE prioritisation_requests MODIFY COLUMN status ENUM('pending', 'ready_for_outreach', 'contacted', 'ready_for_review', 'resolved') NOT NULL DEFAULT 'pending'");
    }
};
