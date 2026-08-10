<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_outreach_batches', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('request_ids');
            $table->timestamp('not_before_at')->nullable()->after('approved_at');
            $table->string('approval_reference', 500)->nullable()->after('not_before_at');
            $table->timestamp('review_required_at')->nullable()->after('approval_reference');

            $table->index(['status', 'not_before_at'], 'brand_outreach_due_approval_index');
        });
    }

    public function down(): void
    {
        Schema::table('brand_outreach_batches', function (Blueprint $table) {
            $table->dropIndex('brand_outreach_due_approval_index');
            $table->dropColumn([
                'approved_at',
                'not_before_at',
                'approval_reference',
                'review_required_at',
            ]);
        });
    }
};
