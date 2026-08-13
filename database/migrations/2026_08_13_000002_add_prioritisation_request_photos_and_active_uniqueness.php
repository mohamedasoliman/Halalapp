<?php

use App\Support\ProductBarcode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACTIVE_STATUSES = [
        'pending',
        'ready_for_outreach',
        'contacted',
        'ready_for_review',
    ];

    private const ACTIVE_KEY_INDEX = 'prioritisation_requests_active_barcode_key_unique';

    public function up(): void
    {
        if (! Schema::hasTable('prioritisation_requests')) {
            return;
        }

        $this->createPhotoTable();
        $this->backfillLegacyPhotos();
        $this->mergeActiveDuplicates();
        $this->promoteLegacyWatchedSilentRequests();

        if (! Schema::hasColumn('prioritisation_requests', 'active_barcode_key')) {
            $this->addGeneratedActiveBarcodeKey();
        }

        if (! Schema::hasIndex('prioritisation_requests', self::ACTIVE_KEY_INDEX)) {
            Schema::table('prioritisation_requests', function (Blueprint $table) {
                $table->unique('active_barcode_key', self::ACTIVE_KEY_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('prioritisation_requests')
            && Schema::hasColumn('prioritisation_requests', 'active_barcode_key')) {
            if (Schema::hasIndex('prioritisation_requests', self::ACTIVE_KEY_INDEX)) {
                Schema::table('prioritisation_requests', function (Blueprint $table) {
                    $table->dropUnique(self::ACTIVE_KEY_INDEX);
                });
            }

            Schema::table('prioritisation_requests', function (Blueprint $table) {
                $table->dropColumn('active_barcode_key');
            });
        }

        Schema::dropIfExists('prioritisation_request_photos');
    }

    private function createPhotoTable(): void
    {
        if (Schema::hasTable('prioritisation_request_photos')) {
            return;
        }

        Schema::create('prioritisation_request_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('prioritisation_requests')->cascadeOnDelete();
            $table->string('path', 500);
            $table->string('original_name', 500)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('source', 50)->default('app');
            $table->timestamps();
            $table->index(['request_id', 'created_at'], 'prioritisation_request_photos_request_created_index');
        });
    }

    private function backfillLegacyPhotos(): void
    {
        if (! Schema::hasColumn('prioritisation_requests', 'photo_path')) {
            return;
        }

        DB::table('prioritisation_requests')
            ->whereNotNull('photo_path')
            ->where('photo_path', '!=', '')
            ->orderBy('id')
            ->eachById(function ($request) {
                $path = trim((string) $request->photo_path);
                if ($path === '') {
                    return;
                }
                $exists = DB::table('prioritisation_request_photos')
                    ->where('request_id', $request->id)
                    ->where('path', $path)
                    ->exists();
                if (! $exists) {
                    DB::table('prioritisation_request_photos')->insert([
                        'request_id' => $request->id,
                        'path' => $path,
                        'source' => 'legacy_photo_path',
                        'created_at' => $request->created_at ?? now(),
                        'updated_at' => $request->updated_at ?? now(),
                    ]);
                }
            }, 500, 'id', 'id');
    }

    private function mergeActiveDuplicates(): void
    {
        $groups = DB::table('prioritisation_requests')
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->orderBy('id')
            ->get()
            ->filter(fn ($request) => ProductBarcode::key((string) $request->barcode) !== null)
            ->groupBy(fn ($request) => ProductBarcode::key((string) $request->barcode))
            ->filter(fn (Collection $requests) => $requests->count() > 1);

        foreach ($groups as $key => $requests) {
            DB::transaction(function () use ($key, $requests) {
                $requests = DB::table('prioritisation_requests')
                    ->whereIn('id', $requests->pluck('id'))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                $survivor = $this->chooseSurvivor($requests);
                $duplicates = $requests->where('id', '!=', $survivor->id);

                $this->mergeRecipients($requests, (int) $survivor->id);
                $this->reassignOpenOutreachBatches($duplicates->pluck('id'), (int) $survivor->id);
                DB::table('prioritisation_request_photos')
                    ->whereIn('request_id', $duplicates->pluck('id'))
                    ->update(['request_id' => $survivor->id, 'updated_at' => now()]);

                $mergedType = $this->mergedType($requests);
                $mergedStatus = $this->strongestStatus($requests);
                $photoPath = filled($survivor->photo_path)
                    ? trim((string) $survivor->photo_path)
                    : DB::table('prioritisation_request_photos')
                        ->where('request_id', $survivor->id)
                        ->orderBy('id')
                        ->value('path');

                DB::table('prioritisation_requests')->where('id', $survivor->id)->update([
                    'barcode' => ProductBarcode::canonical((string) $survivor->barcode),
                    'product_name' => $this->longest($requests->pluck('product_name')),
                    'brand_name' => $this->firstMeaningful($requests->pluck('brand_name')),
                    'photo_path' => $photoPath,
                    'type' => $mergedType,
                    'status' => $mergedStatus,
                    'notes' => $this->mergedNotes($requests->pluck('notes')),
                    'updated_at' => now(),
                ]);

                foreach ($duplicates as $duplicate) {
                    $note = "Merged into active request #{$survivor->id} for canonical barcode {$key}.";
                    DB::table('prioritisation_requests')->where('id', $duplicate->id)->update([
                        'status' => 'dead_end',
                        'notes' => trim(implode("\n", array_filter([$duplicate->notes, $note]))),
                        'updated_at' => now(),
                    ]);
                }
            });
        }
    }

    private function chooseSurvivor(Collection $requests): object
    {
        $statusPriority = array_flip(self::ACTIVE_STATUSES);
        $typePriority = ['silent' => 0, 'new_product' => 1, 'prioritise' => 2];
        $referencedIds = $this->openOutreachRequestIds($requests->pluck('id'));

        return $requests->sort(function ($left, $right) use ($statusPriority, $typePriority, $referencedIds) {
            $referenceComparison = (int) $referencedIds->contains((int) $right->id)
                <=> (int) $referencedIds->contains((int) $left->id);
            if ($referenceComparison !== 0) {
                return $referenceComparison;
            }

            $statusComparison = ($statusPriority[$right->status] ?? -1) <=> ($statusPriority[$left->status] ?? -1);
            if ($statusComparison !== 0) {
                return $statusComparison;
            }

            $typeComparison = ($typePriority[$right->type] ?? -1) <=> ($typePriority[$left->type] ?? -1);

            return $typeComparison !== 0 ? $typeComparison : $left->id <=> $right->id;
        })->first();
    }

    private function promoteLegacyWatchedSilentRequests(): void
    {
        if (! Schema::hasTable('request_watchers')) {
            return;
        }

        $hasProducts = Schema::hasTable('products') && Schema::hasColumn('products', 'Barcode');
        $hasProductKey = $hasProducts && Schema::hasColumn('products', 'barcode_key');
        $hasDeletedAt = $hasProducts && Schema::hasColumn('products', 'deleted_at');
        $productColumns = ['Barcode'];
        foreach (['product_name', 'brand'] as $column) {
            if ($hasProducts && Schema::hasColumn('products', $column)) {
                $productColumns[] = $column;
            }
        }

        DB::table('prioritisation_requests')
            ->where('type', 'silent')
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('request_watchers')
                    ->whereColumn('request_watchers.request_id', 'prioritisation_requests.id');
            })
            ->orderBy('id')
            ->eachById(function ($request) use ($hasProducts, $hasProductKey, $hasDeletedAt, $productColumns) {
                $product = $this->matchingProduct(
                    (string) $request->barcode,
                    $hasProducts,
                    $hasProductKey,
                    $hasDeletedAt,
                    $productColumns,
                );
                if (! $product) {
                    DB::table('prioritisation_requests')->where('id', $request->id)->update([
                        'type' => 'new_product',
                        'status' => 'pending',
                        'updated_at' => now(),
                    ]);

                    return;
                }

                $updates = [
                    'barcode' => (string) $product->Barcode,
                    'type' => 'prioritise',
                    'updated_at' => now(),
                ];
                if (filled($product->product_name ?? null)) {
                    $updates['product_name'] = trim((string) $product->product_name);
                }
                if (filled($product->brand ?? null)) {
                    $updates['brand_name'] = trim((string) $product->brand);
                }

                DB::table('prioritisation_requests')->where('id', $request->id)->update($updates);
            }, 100, 'id', 'id');
    }

    private function matchingProduct(
        string $barcode,
        bool $hasProducts,
        bool $hasProductKey,
        bool $hasDeletedAt,
        array $columns,
    ): ?object {
        if (! $hasProducts) {
            return null;
        }

        $query = DB::table('products');
        if ($hasDeletedAt) {
            $query->whereNull('deleted_at');
        }

        $key = ProductBarcode::key($barcode);
        if ($key !== null && $hasProductKey) {
            $query->where('barcode_key', $key);
        } else {
            $query->where('Barcode', ProductBarcode::clean($barcode));
        }

        return $query->orderBy('id')->first($columns);
    }

    private function mergeRecipients(Collection $requests, int $survivorId): void
    {
        if (! Schema::hasTable('request_watchers')) {
            return;
        }

        $watchers = DB::table('request_watchers')
            ->whereIn('request_id', $requests->pluck('id'))
            ->get(['user_email', 'user_name']);
        foreach ($requests as $request) {
            if (filled($request->user_email)) {
                $watchers->push((object) [
                    'user_email' => $request->user_email,
                    'user_name' => $request->user_name,
                ]);
            }
        }

        foreach ($watchers->groupBy(fn ($watcher) => strtolower(trim((string) $watcher->user_email))) as $email => $matchingWatchers) {
            if ($email === '') {
                continue;
            }
            $name = $matchingWatchers->pluck('user_name')->first(fn ($value) => filled($value));

            $existing = DB::table('request_watchers')
                ->where('request_id', $survivorId)
                ->whereRaw('LOWER(user_email) = ?', [$email])
                ->first();
            if (! $existing) {
                DB::table('request_watchers')->insert([
                    'request_id' => $survivorId,
                    'user_email' => $email,
                    'user_name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif (blank($existing->user_name) && filled($name)) {
                DB::table('request_watchers')->where('id', $existing->id)->update([
                    'user_name' => $name,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function mergedType(Collection $requests): string
    {
        foreach (['prioritise', 'new_product', 'silent'] as $type) {
            if ($requests->contains(fn ($request) => $request->type === $type)) {
                return $type;
            }
        }

        return 'silent';
    }

    private function openOutreachRequestIds(Collection $candidateIds): Collection
    {
        if (! Schema::hasTable('brand_outreach_batches')) {
            return collect();
        }

        $candidateIds = $candidateIds->map(fn ($id) => (int) $id);

        return DB::table('brand_outreach_batches')
            ->whereIn('status', [
                'draft',
                'approved',
                'review_required',
                'queued',
                'sending',
                'uncertain',
                'failed',
            ])
            ->pluck('request_ids')
            ->flatMap(fn ($ids) => json_decode((string) $ids, true) ?: [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $candidateIds->contains($id))
            ->unique()
            ->values();
    }

    private function reassignOpenOutreachBatches(Collection $duplicateIds, int $survivorId): void
    {
        if (! Schema::hasTable('brand_outreach_batches') || $duplicateIds->isEmpty()) {
            return;
        }

        $duplicateIds = $duplicateIds->map(fn ($id) => (int) $id);
        DB::table('brand_outreach_batches')
            ->whereIn('status', [
                'draft',
                'approved',
                'review_required',
                'queued',
            ])
            ->orderBy('id')
            ->eachById(function ($batch) use ($duplicateIds, $survivorId) {
                $requestIds = collect(json_decode((string) $batch->request_ids, true) ?: [])
                    ->map(fn ($id) => (int) $id);
                if (! $requestIds->contains(fn (int $id) => $duplicateIds->contains($id))) {
                    return;
                }

                $requestIds = $requestIds
                    ->map(fn (int $id) => $duplicateIds->contains($id) ? $survivorId : $id)
                    ->unique()
                    ->values()
                    ->all();
                DB::table('brand_outreach_batches')->where('id', $batch->id)->update([
                    'request_ids' => json_encode($requestIds),
                    'updated_at' => now(),
                ]);
            }, 100, 'id', 'id');
    }

    private function strongestStatus(Collection $requests): string
    {
        foreach (array_reverse(self::ACTIVE_STATUSES) as $status) {
            if ($requests->contains(fn ($request) => $request->status === $status)) {
                return $status;
            }
        }

        return 'pending';
    }

    private function firstMeaningful(Collection $values): ?string
    {
        $value = $values->first(fn ($value) => filled($value));

        return $value === null ? null : trim((string) $value);
    }

    private function longest(Collection $values): ?string
    {
        $value = $values
            ->filter(fn ($value) => filled($value))
            ->sortByDesc(fn ($value) => mb_strlen(trim((string) $value)))
            ->first();

        return $value === null ? null : trim((string) $value);
    }

    private function mergedNotes(Collection $values): ?string
    {
        $notes = $values
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->implode("\n");

        return $notes === '' ? null : $notes;
    }

    private function addGeneratedActiveBarcodeKey(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE prioritisation_requests
                ADD COLUMN active_barcode_key VARCHAR(20)
                GENERATED ALWAYS AS (
                    CASE
                        WHEN status IN ('pending', 'ready_for_outreach', 'contacted', 'ready_for_review')
                        THEN NULLIF(TRIM(LEADING '0' FROM TRIM(barcode)), '')
                        ELSE NULL
                    END
                ) STORED
            SQL);

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                ALTER TABLE prioritisation_requests
                ADD COLUMN active_barcode_key TEXT
                GENERATED ALWAYS AS (
                    CASE
                        WHEN status IN ('pending', 'ready_for_outreach', 'contacted', 'ready_for_review')
                        THEN NULLIF(ltrim(trim(barcode), '0'), '')
                        ELSE NULL
                    END
                ) VIRTUAL
            SQL);

            return;
        }

        throw new RuntimeException('Active prioritisation uniqueness requires a generated-column implementation for this database driver.');
    }
};
