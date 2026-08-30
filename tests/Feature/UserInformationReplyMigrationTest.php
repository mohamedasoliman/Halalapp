<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserInformationReplyMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('prioritisation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 20);
            $table->string('photo_path', 500)->nullable();
            $table->timestamps();
        });
        Schema::create('prioritisation_request_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('prioritisation_requests')->cascadeOnDelete();
            $table->string('path', 500);
            $table->timestamps();
        });
        Schema::create('request_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('event_reference', 500);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_migration_adds_idempotent_reply_schema_and_allows_a_reference_per_recipient(): void
    {
        $migration = $this->migration();
        $migration->up();

        $this->assertTrue(Schema::hasColumns('prioritisation_requests', [
            'information_received_at',
            'information_reply_count',
        ]));
        $this->assertTrue(Schema::hasColumns('request_notification_deliveries', [
            'reply_reference',
            'outbound_message_id',
            'outbound_message_id_hash',
        ]));
        $this->assertTrue(Schema::hasTable('user_information_replies'));
        $this->assertTrue(Schema::hasTable('user_information_reply_attachments'));

        $now = now();
        $requestId = DB::table('prioritisation_requests')->insertGetId([
            'barcode' => '9400000000001',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        foreach ([1, 2] as $recipient) {
            DB::table('request_notification_deliveries')->insert([
                'event_reference' => 'information-request:test',
                'reply_reference' => "HK-INFO-{$requestId}-9400000000001",
                'outbound_message_id' => "<outbound-{$recipient}@halalkiwi.com>",
                'outbound_message_id_hash' => hash('sha256', "<outbound-{$recipient}@halalkiwi.com>"),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $this->assertSame(2, DB::table('request_notification_deliveries')->count());

        try {
            DB::table('request_notification_deliveries')->insert([
                'event_reference' => 'information-request:duplicate-message-id',
                'outbound_message_id_hash' => hash('sha256', '<outbound-1@halalkiwi.com>'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->fail('Outbound Message-ID hashes must be unique.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $deliveryId = DB::table('request_notification_deliveries')->min('id');
        $reply = [
            'request_id' => $requestId,
            'request_notification_delivery_id' => $deliveryId,
            'mailbox_address' => 'products@halalkiwi.com',
            'message_id' => '<reply@example.com>',
            'message_id_hash' => hash('sha256', '<reply@example.com>'),
            'payload_hash' => hash('sha256', 'payload'),
            'from_address' => 'requester@example.com',
            'normalized_from_address' => 'requester@example.com',
            'normalized_from_address_hash' => hash('sha256', 'requester@example.com'),
            'to_address' => 'products@halalkiwi.com',
            'subject' => 'Product details',
            'body' => 'Requested details.',
            'received_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        DB::table('user_information_replies')->insert($reply);
        try {
            DB::table('user_information_replies')->insert([
                ...$reply,
                'payload_hash' => hash('sha256', 'conflict'),
            ]);
            $this->fail('Inbound Message-ID hashes must be unique.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $migration->down();
        $this->assertFalse(Schema::hasTable('user_information_replies'));
        $this->assertFalse(Schema::hasTable('user_information_reply_attachments'));
        $this->assertFalse(Schema::hasColumn('prioritisation_requests', 'information_reply_count'));
        $this->assertFalse(Schema::hasColumn('request_notification_deliveries', 'reply_reference'));
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_08_30_000001_create_user_information_reply_tables.php');
    }
}
