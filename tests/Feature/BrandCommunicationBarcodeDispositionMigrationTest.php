<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BrandCommunicationBarcodeDispositionMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('brand_communications', function (Blueprint $table) {
            $table->id();
            $table->string('direction');
            $table->json('barcodes_mentioned')->nullable();
            $table->text('action_taken')->nullable();
            $table->string('processing_status')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('Barcode');
        });
        Schema::create('prioritisation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 20);
            $table->string('status');
            $table->tinyInteger('resolved_status')->nullable();
            $table->unsignedBigInteger('resolution_communication_id')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_backfill_reopens_ambiguous_multi_barcode_applied_messages(): void
    {
        $communicationId = DB::table('brand_communications')->insertGetId([
            'direction' => 'inbound',
            'barcodes_mentioned' => json_encode(['9400000000100', '9400000000101']),
            'action_taken' => 'Approved Halal resolution applied to 9400000000100.',
            'processing_status' => 'applied',
            'processed_at' => now()->subDay(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        DB::table('products')->insert([
            ['Barcode' => '9400000000100'],
            ['Barcode' => '9400000000101'],
        ]);

        $migration = require database_path(
            'migrations/2026_08_13_000001_create_brand_communication_barcode_dispositions_table.php'
        );
        $migration->up();

        $this->assertDatabaseHas('brand_communication_barcode_dispositions', [
            'brand_communication_id' => $communicationId,
            'barcode' => '9400000000100',
            'disposition' => 'applied',
            'resolved_status' => 0,
        ]);
        $this->assertDatabaseHas('brand_communication_barcode_dispositions', [
            'brand_communication_id' => $communicationId,
            'barcode' => '9400000000101',
            'disposition' => 'review_required',
            'resolved_status' => null,
        ]);
        $this->assertDatabaseHas('brand_communications', [
            'id' => $communicationId,
            'processing_status' => 'partially_processed',
            'processed_at' => null,
        ]);
    }

    public function test_backfill_accepts_only_exact_linked_or_generated_application_evidence(): void
    {
        $communicationId = DB::table('brand_communications')->insertGetId([
            'direction' => 'inbound',
            'barcodes_mentioned' => json_encode(['9400000000200', '9400000000201']),
            'action_taken' => null,
            'processing_status' => 'applied',
            'processed_at' => now()->subDay(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        DB::table('prioritisation_requests')->insert([
            'barcode' => '9400000000200',
            'status' => 'resolved',
            'resolved_status' => 1,
            'resolution_communication_id' => $communicationId,
        ]);

        $migration = require database_path(
            'migrations/2026_08_13_000001_create_brand_communication_barcode_dispositions_table.php'
        );
        $migration->up();

        $this->assertDatabaseHas('brand_communication_barcode_dispositions', [
            'brand_communication_id' => $communicationId,
            'barcode' => '9400000000200',
            'disposition' => 'applied',
            'resolved_status' => 1,
        ]);
        $this->assertDatabaseHas('brand_communication_barcode_dispositions', [
            'brand_communication_id' => $communicationId,
            'barcode' => '9400000000201',
            'disposition' => 'review_required',
        ]);
    }

    public function test_backfill_reopens_other_legacy_message_level_completion_states(): void
    {
        $communicationId = DB::table('brand_communications')->insertGetId([
            'direction' => 'inbound',
            'barcodes_mentioned' => json_encode(['9400000000300']),
            'action_taken' => null,
            'processing_status' => 'processed',
            'processed_at' => now()->subDay(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $migration = require database_path(
            'migrations/2026_08_13_000001_create_brand_communication_barcode_dispositions_table.php'
        );
        $migration->up();

        $this->assertDatabaseHas('brand_communication_barcode_dispositions', [
            'brand_communication_id' => $communicationId,
            'barcode' => '9400000000300',
            'disposition' => 'review_required',
        ]);
        $this->assertDatabaseHas('brand_communications', [
            'id' => $communicationId,
            'processing_status' => 'review_required',
            'processed_at' => null,
        ]);
    }
}
