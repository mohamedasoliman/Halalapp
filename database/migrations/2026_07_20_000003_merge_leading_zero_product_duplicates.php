<?php

use App\Support\ProductBarcode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACTIVE_REQUEST_STATUSES = [
        'pending',
        'ready_for_outreach',
        'contacted',
        'ready_for_review',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'Barcode')) {
            return;
        }

        $groups = $this->duplicateGroups();
        $this->assertSafeToMerge($groups);

        DB::transaction(function () use ($groups) {
            foreach ($groups as $key => $productIds) {
                $this->mergeProducts((string) $key, $productIds);
            }
        });

        if (! Schema::hasColumn('products', 'barcode_key')) {
            $this->addGeneratedBarcodeKey();
        }

        if (! Schema::hasIndex('products', 'products_barcode_key_unique')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unique('barcode_key', 'products_barcode_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'barcode_key')) {
            return;
        }

        if (Schema::hasIndex('products', 'products_barcode_key_unique')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique('products_barcode_key_unique');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('barcode_key');
        });
    }

    private function duplicateGroups(): Collection
    {
        return DB::table('products')
            ->whereNull('deleted_at')
            ->get(['id', 'Barcode'])
            ->groupBy(fn ($product) => ProductBarcode::key((string) $product->Barcode))
            ->filter(fn (Collection $products, $key) => $key !== '' && $key !== null && $products->count() > 1)
            ->map(fn (Collection $products) => $products->pluck('id')->map(fn ($id) => (int) $id)->values());
    }

    private function assertSafeToMerge(Collection $groups): void
    {
        foreach ($groups as $key => $productIds) {
            $products = DB::table('products')->whereIn('id', $productIds)->get();
            $statuses = $products->pluck('halal_status')->map(fn ($status) => (string) $status)->unique();
            if ($statuses->count() !== 1) {
                throw new \RuntimeException("Barcode variants for {$key} have conflicting halal statuses.");
            }

            $proofs = $products->pluck('proof')->filter()->map(fn ($proof) => trim((string) $proof))->unique();
            if ($proofs->count() > 1) {
                throw new \RuntimeException("Barcode variants for {$key} have conflicting proof records.");
            }

            $variants = $products->pluck('Barcode')->map(fn ($barcode) => (string) $barcode)->all();
            if ($this->hasCommunicationReferences($variants)
                || $this->hasNotificationReferences($variants)
                || $this->hasOutreachBatchReferences($variants)) {
                throw new \RuntimeException("Barcode variants for {$key} are referenced by an outreach or notification audit record.");
            }
        }
    }

    private function mergeProducts(string $key, Collection $productIds): void
    {
        $products = DB::table('products')->whereIn('id', $productIds)->orderBy('id')->get();
        $survivor = $products->first();
        $duplicates = $products->slice(1);
        $canonicalBarcode = ProductBarcode::canonical($key);

        $notes = $this->mergedText($products->pluck('notes'), 250, $key);
        $updates = [
            'Barcode' => $canonicalBarcode,
            'product_name' => $this->longestValue($products->pluck('product_name')) ?? $survivor->product_name,
            'product_image' => $this->bestImage($products->pluck('product_image')),
            'proof' => $this->firstMeaningfulValue($products->pluck('proof')),
            'Certification_Status' => $this->firstMeaningfulValue($products->pluck('Certification_Status')) ?? '_',
            'category' => $this->firstMeaningfulValue($products->pluck('category')),
            'notes' => $notes,
            'ingredient' => $this->longestValue($products->pluck('ingredient')),
            'created_at' => $survivor->created_at,
            'updated_at' => now(),
        ];

        DB::table('products')->where('id', $survivor->id)->update($updates);

        foreach ($duplicates as $duplicate) {
            DB::table('products')->where('id', $duplicate->id)->update([
                'deleted_at' => now(),
                'created_at' => $duplicate->created_at,
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('prioritisation_requests')) {
            $variants = $products->pluck('Barcode')->map(fn ($barcode) => (string) $barcode)->all();
            DB::table('prioritisation_requests')->whereIn('barcode', $variants)->update([
                'barcode' => $canonicalBarcode,
                'updated_at' => now(),
            ]);
            $this->mergeActiveRequests($canonicalBarcode);
        }
    }

    private function mergeActiveRequests(string $barcode): void
    {
        $requests = DB::table('prioritisation_requests')
            ->where('barcode', $barcode)
            ->whereIn('status', self::ACTIVE_REQUEST_STATUSES)
            ->orderBy('id')
            ->get();
        if ($requests->count() < 2) {
            return;
        }

        $survivor = $requests->firstWhere('type', 'prioritise') ?? $requests->first();
        $duplicates = $requests->where('id', '!=', $survivor->id);
        $statusPriority = ['pending', 'ready_for_outreach', 'contacted', 'ready_for_review'];
        $status = $requests->pluck('status')->sortByDesc(
            fn ($value) => array_search($value, $statusPriority, true)
        )->first();

        DB::table('prioritisation_requests')->where('id', $survivor->id)->update([
            'product_name' => $this->longestValue($requests->pluck('product_name')),
            'brand_name' => $this->firstMeaningfulValue($requests->pluck('brand_name')),
            'type' => $requests->contains(fn ($request) => $request->type === 'prioritise') ? 'prioritise' : $survivor->type,
            'status' => $status,
            'notes' => $this->mergedText($requests->pluck('notes'), null, $barcode),
            'updated_at' => now(),
        ]);

        foreach ($duplicates as $duplicate) {
            $this->copyRequestRecipients($duplicate, (int) $survivor->id);
            DB::table('prioritisation_requests')->where('id', $duplicate->id)->update([
                'status' => 'dead_end',
                'notes' => $this->mergedText(collect([
                    $duplicate->notes,
                    "Merged into request #{$survivor->id} during barcode cleanup.",
                ]), null, $barcode),
                'updated_at' => now(),
            ]);
        }
    }

    private function copyRequestRecipients(object $request, int $survivorId): void
    {
        if (! Schema::hasTable('request_watchers')) {
            return;
        }

        $recipients = DB::table('request_watchers')
            ->where('request_id', $request->id)
            ->get(['user_email', 'user_name']);
        if (! empty($request->user_email)) {
            $recipients->push((object) [
                'user_email' => $request->user_email,
                'user_name' => $request->user_name,
            ]);
        }

        foreach ($recipients as $recipient) {
            $existing = DB::table('request_watchers')
                ->where('request_id', $survivorId)
                ->where('user_email', $recipient->user_email)
                ->first();
            if (! $existing) {
                DB::table('request_watchers')->insert([
                    'request_id' => $survivorId,
                    'user_email' => $recipient->user_email,
                    'user_name' => $recipient->user_name,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            } elseif (empty($existing->user_name) && ! empty($recipient->user_name)) {
                DB::table('request_watchers')->where('id', $existing->id)->update([
                    'user_name' => $recipient->user_name,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function hasCommunicationReferences(array $barcodes): bool
    {
        if (! Schema::hasTable('brand_communications')) {
            return false;
        }

        return DB::table('brand_communications')->get(['barcodes_mentioned'])->contains(function ($communication) use ($barcodes) {
            $mentioned = json_decode((string) $communication->barcodes_mentioned, true) ?: [];

            return count(array_intersect($barcodes, array_map('strval', $mentioned))) > 0;
        });
    }

    private function hasNotificationReferences(array $barcodes): bool
    {
        return Schema::hasTable('request_notification_deliveries')
            && DB::table('request_notification_deliveries')->whereIn('barcode', $barcodes)->exists();
    }

    private function hasOutreachBatchReferences(array $barcodes): bool
    {
        if (! Schema::hasTable('brand_outreach_batches') || ! Schema::hasTable('prioritisation_requests')) {
            return false;
        }

        $requestIds = DB::table('prioritisation_requests')->whereIn('barcode', $barcodes)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($requestIds === []) {
            return false;
        }

        return DB::table('brand_outreach_batches')->get(['request_ids'])->contains(function ($batch) use ($requestIds) {
            $batchRequestIds = array_map('intval', json_decode((string) $batch->request_ids, true) ?: []);

            return count(array_intersect($requestIds, $batchRequestIds)) > 0;
        });
    }

    private function addGeneratedBarcodeKey(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE products
                ADD COLUMN barcode_key VARCHAR(20)
                GENERATED ALWAYS AS (
                    CASE
                        WHEN deleted_at IS NULL THEN NULLIF(TRIM(LEADING '0' FROM TRIM(Barcode)), '')
                        ELSE NULL
                    END
                ) STORED
            SQL);

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                ALTER TABLE products
                ADD COLUMN barcode_key TEXT
                GENERATED ALWAYS AS (
                    CASE
                        WHEN deleted_at IS NULL THEN NULLIF(ltrim(trim(Barcode), '0'), '')
                        ELSE NULL
                    END
                ) VIRTUAL
            SQL);

            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('barcode_key', 20)->nullable();
        });

        DB::table('products')->whereNull('deleted_at')->orderBy('id')->eachById(function ($product) {
            DB::table('products')->where('id', $product->id)->update([
                'barcode_key' => ProductBarcode::key((string) $product->Barcode),
            ]);
        }, 500, 'id', 'id');
    }

    private function firstMeaningfulValue(Collection $values): ?string
    {
        $value = $values->first(fn ($value) => $value !== null && trim((string) $value) !== '' && trim((string) $value) !== '_');

        return $value === null ? null : (string) $value;
    }

    private function longestValue(Collection $values): ?string
    {
        $value = $values
            ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
            ->sortByDesc(fn ($value) => strlen((string) $value))
            ->first();

        return $value === null ? null : (string) $value;
    }

    private function bestImage(Collection $images): ?string
    {
        $images = $images->filter(fn ($image) => $image !== null && trim((string) $image) !== '');
        $image = $images->first(fn ($image) => ! str_contains((string) $image, 'No_Image_Available'));

        return $image === null ? $images->first() : (string) $image;
    }

    private function mergedText(Collection $values, ?int $maxLength, string $barcode): ?string
    {
        $merged = $values
            ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->implode("\n");
        if ($merged === '') {
            return null;
        }
        if ($maxLength !== null && strlen($merged) > $maxLength) {
            throw new \RuntimeException("Merged notes for {$barcode} exceed the database limit.");
        }

        return $merged;
    }
};
