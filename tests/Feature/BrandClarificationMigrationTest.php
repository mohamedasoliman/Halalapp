<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BrandClarificationMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('brand_outreach_batches', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->unsignedBigInteger('brand_id');
            $table->string('kind')->default('initial');
            $table->string('status')->default('draft');
            $table->string('recipient_email');
            $table->string('subject', 500);
            $table->json('products');
            $table->json('request_ids');
            $table->timestamps();
        });

        DB::table('brand_outreach_batches')->insert([
            'reference' => 'HK-EXISTING',
            'brand_id' => 1,
            'kind' => 'initial',
            'status' => 'sent',
            'recipient_email' => 'quality@example.com',
            'subject' => 'Existing outreach',
            'products' => '[]',
            'request_ids' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_migration_preserves_existing_batches_and_adds_idempotent_clarification_fields(): void
    {
        $this->migration()->up();

        $this->assertSame('sent', DB::table('brand_outreach_batches')->where('reference', 'HK-EXISTING')->value('status'));
        $this->assertTrue(Schema::hasColumns('brand_outreach_batches', [
            'message_body',
            'source_communication_id',
            'event_reference',
            'event_key',
            'in_reply_to_message_id',
            'reference_message_ids',
        ]));

        DB::table('brand_outreach_batches')->insert($this->clarification('HK-CLARIFICATION-1'));

        $this->expectException(QueryException::class);
        DB::table('brand_outreach_batches')->insert($this->clarification('HK-CLARIFICATION-2'));
    }

    private function clarification(string $reference): array
    {
        return [
            'reference' => $reference,
            'brand_id' => 1,
            'kind' => 'clarification',
            'status' => 'draft',
            'recipient_email' => 'quality@example.com',
            'subject' => 'Re: Clarification',
            'message_body' => 'Question',
            'products' => '[]',
            'request_ids' => '[]',
            'event_reference' => 'manufacturer-clarification:test',
            'event_key' => hash('sha256', 'manufacturer-clarification:test'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_08_03_000001_add_clarification_fields_to_brand_outreach_batches.php');
    }
}
