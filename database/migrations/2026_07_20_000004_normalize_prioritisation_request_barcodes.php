<?php

use App\Support\ProductBarcode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prioritisation_requests') || ! Schema::hasColumn('prioritisation_requests', 'barcode')) {
            return;
        }

        $this->restoreRequestsMergedByTheProductCleanup();
        $this->normalizeRequestBarcodes();

        if (! Schema::hasColumn('prioritisation_requests', 'barcode_key')) {
            $this->addGeneratedBarcodeKey();
        }

        if (! Schema::hasIndex('prioritisation_requests', 'prioritisation_requests_barcode_key_index')) {
            Schema::table('prioritisation_requests', function (Blueprint $table) {
                $table->index('barcode_key', 'prioritisation_requests_barcode_key_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('prioritisation_requests', 'barcode_key')) {
            return;
        }

        if (Schema::hasIndex('prioritisation_requests', 'prioritisation_requests_barcode_key_index')) {
            Schema::table('prioritisation_requests', function (Blueprint $table) {
                $table->dropIndex('prioritisation_requests_barcode_key_index');
            });
        }

        Schema::table('prioritisation_requests', function (Blueprint $table) {
            $table->dropColumn('barcode_key');
        });
    }

    private function restoreRequestsMergedByTheProductCleanup(): void
    {
        DB::table('prioritisation_requests')
            ->where('status', 'dead_end')
            ->where('notes', 'like', '%Merged into request #% during barcode cleanup.%')
            ->orderBy('id')
            ->eachById(function ($request) {
                if (! preg_match('/Merged into request #(\d+) during barcode cleanup\./', (string) $request->notes, $matches)) {
                    return;
                }

                $survivorStatus = DB::table('prioritisation_requests')->where('id', (int) $matches[1])->value('status');
                if (! in_array($survivorStatus, ['pending', 'ready_for_outreach', 'contacted', 'ready_for_review'], true)) {
                    return;
                }

                $notes = preg_replace(
                    '/(?:^|\R)Merged into request #\d+ during barcode cleanup\.(?:\R|$)/',
                    "\n",
                    (string) $request->notes
                );
                $notes = trim((string) $notes);

                DB::table('prioritisation_requests')->where('id', $request->id)->update([
                    'status' => $survivorStatus,
                    'notes' => $notes === '' ? null : $notes,
                    'updated_at' => now(),
                ]);
            }, 100, 'id', 'id');
    }

    private function normalizeRequestBarcodes(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $productsByKey = DB::table('products')
            ->whereNull('deleted_at')
            ->get(['Barcode'])
            ->mapWithKeys(function ($product) {
                $key = ProductBarcode::key((string) $product->Barcode);

                return $key === null ? [] : [$key => (string) $product->Barcode];
            });

        DB::table('prioritisation_requests')->orderBy('id')->eachById(function ($request) use ($productsByKey) {
            $currentBarcode = ProductBarcode::clean((string) $request->barcode);
            $key = ProductBarcode::key($currentBarcode);
            if ($key === null || ! $productsByKey->has($key)) {
                return;
            }

            $normalizedBarcode = $productsByKey->get($key);
            if ($normalizedBarcode === $currentBarcode) {
                return;
            }

            DB::table('prioritisation_requests')->where('id', $request->id)->update([
                'barcode' => $normalizedBarcode,
                'updated_at' => now(),
            ]);
        }, 500, 'id', 'id');
    }

    private function addGeneratedBarcodeKey(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE prioritisation_requests
                ADD COLUMN barcode_key VARCHAR(20)
                GENERATED ALWAYS AS (NULLIF(TRIM(LEADING '0' FROM TRIM(barcode)), '')) STORED
            SQL);

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                ALTER TABLE prioritisation_requests
                ADD COLUMN barcode_key TEXT
                GENERATED ALWAYS AS (NULLIF(ltrim(trim(barcode), '0'), '')) VIRTUAL
            SQL);

            return;
        }

        Schema::table('prioritisation_requests', function (Blueprint $table) {
            $table->string('barcode_key', 20)->nullable();
        });
    }
};
