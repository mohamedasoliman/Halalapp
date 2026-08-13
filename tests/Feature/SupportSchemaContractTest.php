<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupportSchemaContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        (require database_path('migrations/2026_08_13_000003_create_app_support_tables.php'))->up();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_support_schema_uses_portable_email_index_and_no_legacy_admin_foreign_keys(): void
    {
        $ticketIndexes = collect(Schema::getIndexes('support_tickets'));
        $this->assertTrue($ticketIndexes->contains(
            fn (array $index) => $index['columns'] === ['normalized_requester_email_hash'],
        ));
        $this->assertFalse($ticketIndexes->contains(
            fn (array $index) => in_array('normalized_requester_email', $index['columns'], true),
        ));

        foreach ([
            'support_tickets' => 'assigned_to',
            'support_messages' => 'created_by',
            'support_deliveries' => 'reconciled_by',
            'support_ticket_events' => 'actor_admin_id',
        ] as $table => $column) {
            $this->assertFalse(collect(Schema::getForeignKeys($table))->contains(
                fn (array $foreign) => in_array($column, $foreign['columns'], true),
            ), "{$table}.{$column} must not depend on the production-specific admins.id type.");
        }

        $migration = file_get_contents(
            database_path('migrations/2026_08_13_000003_create_app_support_tables.php'),
        );
        $this->assertStringContainsString("string('requester_name', 255)", $migration);
        $this->assertStringContainsString("string('submission_context_label', 255)", $migration);
        $this->assertStringContainsString("string('from_name', 255)", $migration);
        $this->assertStringContainsString("bigInteger('assigned_to')", $migration);
        $this->assertStringContainsString("bigInteger('created_by')", $migration);
        $this->assertStringContainsString("bigInteger('reconciled_by')", $migration);
        $this->assertStringContainsString("bigInteger('actor_admin_id')", $migration);
    }
}
