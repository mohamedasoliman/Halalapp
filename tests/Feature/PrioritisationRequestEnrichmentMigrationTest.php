<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PrioritisationRequestEnrichmentMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('Barcode', 20);
            $table->string('barcode_key', 20)->nullable();
            $table->string('product_name')->nullable();
            $table->string('brand')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('prioritisation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 20);
            $table->string('product_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('user_name')->nullable();
            $table->string('photo_path', 500)->nullable();
            $table->string('type')->default('silent');
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('request_watchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->string('user_email');
            $table->string('user_name')->nullable();
            $table->timestamps();
            $table->unique(['request_id', 'user_email']);
        });
        Schema::create('brand_outreach_batches', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->json('request_ids');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_migration_backfills_photos_merges_duplicates_and_enforces_one_active_key(): void
    {
        $now = now();
        DB::table('prioritisation_requests')->insert([
            [
                'id' => 10,
                'barcode' => '078895743050',
                'product_name' => 'Short Name',
                'brand_name' => null,
                'user_email' => 'first@example.com',
                'photo_path' => 'prioritisation_photos/front.jpg',
                'type' => 'silent',
                'status' => 'pending',
                'notes' => 'First request history.',
                'created_at' => $now->copy()->subMinute(),
                'updated_at' => $now->copy()->subMinute(),
            ],
            [
                'id' => 11,
                'barcode' => '0078895743050',
                'product_name' => 'Long Authoritative Product Name',
                'brand_name' => 'Known Brand',
                'user_email' => 'SECOND@example.com',
                'photo_path' => 'prioritisation_photos/back.jpg',
                'type' => 'prioritise',
                'status' => 'ready_for_outreach',
                'notes' => 'Second request history.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        DB::table('request_watchers')->insert([
            [
                'request_id' => 10,
                'user_email' => 'shared@example.com',
                'user_name' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'request_id' => 11,
                'user_email' => 'SHARED@example.com',
                'user_name' => 'Named Watcher',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        DB::table('brand_outreach_batches')->insert([
            'status' => 'draft',
            'request_ids' => json_encode([10, 11]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->migration()->up();

        $active = DB::table('prioritisation_requests')
            ->whereIn('status', ['pending', 'ready_for_outreach', 'contacted', 'ready_for_review'])
            ->first();
        $duplicate = DB::table('prioritisation_requests')->where('status', 'dead_end')->first();
        $this->assertSame(11, $active->id);
        $this->assertSame('prioritise', $active->type);
        $this->assertSame('ready_for_outreach', $active->status);
        $this->assertSame('Known Brand', $active->brand_name);
        $this->assertSame('prioritisation_photos/back.jpg', $active->photo_path);
        $this->assertStringContainsString('First request history.', $active->notes);
        $this->assertStringContainsString('Second request history.', $active->notes);
        $this->assertStringContainsString('Merged into active request #11', $duplicate->notes);
        $this->assertSame(2, DB::table('prioritisation_request_photos')->where('request_id', 11)->count());
        $this->assertSame(3, DB::table('request_watchers')->where('request_id', 11)->count());
        $this->assertDatabaseHas('request_watchers', [
            'request_id' => 11,
            'user_email' => 'SHARED@example.com',
            'user_name' => 'Named Watcher',
        ]);
        $this->assertSame(
            [11],
            json_decode((string) DB::table('brand_outreach_batches')->value('request_ids'), true),
        );

        try {
            DB::table('prioritisation_requests')->insert([
                'barcode' => '78895743050',
                'type' => 'silent',
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->fail('A duplicate active canonical barcode should violate the unique key.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        DB::table('prioritisation_requests')->insert([
            'barcode' => '78895743050',
            'type' => 'silent',
            'status' => 'resolved',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('prioritisation_requests')->where('id', $active->id)->update(['status' => 'resolved']);
        DB::table('prioritisation_requests')->insert([
            'barcode' => '00078895743050',
            'type' => 'silent',
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->assertSame(4, DB::table('prioritisation_requests')->count());
    }

    public function test_migration_keeps_the_request_referenced_by_an_in_flight_batch(): void
    {
        $now = now();
        DB::table('prioritisation_requests')->insert([
            [
                'id' => 20,
                'barcode' => '012345678905',
                'type' => 'silent',
                'status' => 'pending',
                'created_at' => $now->copy()->subMinute(),
                'updated_at' => $now->copy()->subMinute(),
            ],
            [
                'id' => 21,
                'barcode' => '0012345678905',
                'type' => 'prioritise',
                'status' => 'ready_for_review',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        DB::table('brand_outreach_batches')->insert([
            'status' => 'sending',
            'request_ids' => json_encode([20]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->migration()->up();

        $this->assertDatabaseHas('prioritisation_requests', [
            'id' => 20,
            'type' => 'prioritise',
            'status' => 'ready_for_review',
        ]);
        $this->assertDatabaseHas('prioritisation_requests', [
            'id' => 21,
            'status' => 'dead_end',
        ]);
        $this->assertSame(
            [20],
            json_decode((string) DB::table('brand_outreach_batches')->value('request_ids'), true),
        );
    }

    public function test_migration_does_not_merge_legacy_barcodes_with_no_canonical_key(): void
    {
        $now = now();
        DB::table('prioritisation_requests')->insert([
            [
                'barcode' => '00000000',
                'type' => 'silent',
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'barcode' => '0000000000000',
                'type' => 'silent',
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->migration()->up();

        $this->assertSame(2, DB::table('prioritisation_requests')->where('status', 'pending')->count());
        $this->assertSame(0, DB::table('prioritisation_requests')->where('status', 'dead_end')->count());
    }

    public function test_migration_promotes_legacy_watched_silent_requests_into_the_correct_lane(): void
    {
        $now = now();
        DB::table('products')->insert([
            'Barcode' => '9400000000061',
            'barcode_key' => '9400000000061',
            'product_name' => 'Authoritative Migrated Product',
            'brand' => 'Authoritative Migrated Brand',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('prioritisation_requests')->insert([
            [
                'id' => 30,
                'barcode' => '9400000000061',
                'type' => 'silent',
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 31,
                'barcode' => '1234567890123',
                'type' => 'silent',
                'status' => 'ready_for_outreach',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        DB::table('request_watchers')->insert([
            [
                'request_id' => 30,
                'user_email' => 'known@example.com',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'request_id' => 31,
                'user_email' => 'unknown@example.com',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->migration()->up();

        $this->assertDatabaseHas('prioritisation_requests', [
            'id' => 30,
            'barcode' => '9400000000061',
            'product_name' => 'Authoritative Migrated Product',
            'brand_name' => 'Authoritative Migrated Brand',
            'type' => 'prioritise',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('prioritisation_requests', [
            'id' => 31,
            'type' => 'new_product',
            'status' => 'pending',
        ]);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_08_13_000002_add_prioritisation_request_photos_and_active_uniqueness.php');
    }
}
