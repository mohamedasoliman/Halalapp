<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class ManufacturerReplyMigrationTest extends TestCase
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
            $table->string('email_message_id')->nullable();
            $table->timestamps();
        });
        Schema::create('prioritisation_requests', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('resolved_status')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_migration_aborts_on_normalized_duplicate_message_ids(): void
    {
        DB::table('brand_communications')->insert([
            ['email_message_id' => ' <DUPLICATE@Example.com> '],
            ['email_message_id' => '<duplicate@example.com>'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate manufacturer email Message-ID');

        $this->migration()->up();
    }

    public function test_migration_normalizes_ids_and_enforces_database_uniqueness(): void
    {
        DB::table('brand_communications')->insert(['email_message_id' => ' <Unique@Example.com> ']);

        $this->migration()->up();

        $this->assertSame('<unique@example.com>', DB::table('brand_communications')->value('email_message_id'));
        $this->assertTrue(Schema::hasColumns('brand_communications', [
            'proof_path',
            'processing_status',
            'processed_at',
        ]));
        $this->assertTrue(Schema::hasTable('request_notification_deliveries'));
        $this->assertTrue(Schema::hasColumn('request_notification_deliveries', 'uncertain_at'));

        $this->expectException(QueryException::class);
        DB::table('brand_communications')->insert(['email_message_id' => '<unique@example.com>']);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_07_20_000001_harden_manufacturer_reply_processing.php');
    }
}
