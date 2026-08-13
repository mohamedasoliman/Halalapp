<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('brand_communication_barcode_dispositions')) {
            Schema::create('brand_communication_barcode_dispositions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('brand_communication_id');
                $table->foreign(
                    'brand_communication_id',
                    'brand_comm_disposition_communication_fk',
                )->references('id')->on('brand_communications')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('barcode', 20)->index();
                $table->string('disposition', 40)->default('pending_review')->index();
                $table->tinyInteger('resolved_status')->nullable();
                $table->text('reason')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['brand_communication_id', 'barcode'],
                    'brand_comm_barcode_disposition_unique',
                );
            });
        }

        $this->backfillInboundCommunications();
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_communication_barcode_dispositions');
    }

    private function backfillInboundCommunications(): void
    {
        if (! Schema::hasTable('brand_communications')) {
            return;
        }

        DB::table('brand_communications')
            ->where('direction', 'inbound')
            ->orderBy('id')
            ->eachById(function (object $communication) {
                $barcodes = $this->barcodes($communication->barcodes_mentioned ?? null);
                if ($barcodes->isEmpty()) {
                    return;
                }

                $actionStatuses = $this->actionStatuses((string) ($communication->action_taken ?? ''));
                foreach ($barcodes as $barcode) {
                    [$disposition, $resolvedStatus, $reason] = $this->historicalDisposition(
                        $communication,
                        $barcode,
                        $actionStatuses->get($barcode, collect()),
                    );

                    DB::table('brand_communication_barcode_dispositions')->updateOrInsert([
                        'brand_communication_id' => $communication->id,
                        'barcode' => $barcode,
                    ], [
                        'product_id' => $this->exactProductId($barcode),
                        'disposition' => $disposition,
                        'resolved_status' => $resolvedStatus,
                        'reason' => $reason,
                        'decided_at' => $disposition === 'applied'
                            ? ($communication->processed_at ?? $communication->updated_at ?? now())
                            : null,
                        'created_at' => $communication->created_at ?? now(),
                        'updated_at' => now(),
                    ]);
                }

                // Message-level state used to hide unfinished exact barcodes.
                // Derive it from the conservative per-barcode backfill instead.
                $this->recomputeParent((int) $communication->id, $communication);
            }, 100, 'id', 'id');
    }

    private function historicalDisposition(
        object $communication,
        string $barcode,
        Collection $actionStatuses,
    ): array {
        $requestStatuses = $this->linkedRequestStatuses((int) $communication->id, $barcode);
        $evidenceStatuses = $requestStatuses->merge($actionStatuses)->unique()->values();

        if ($evidenceStatuses->count() === 1) {
            $sources = collect([
                $requestStatuses->isNotEmpty() ? 'linked resolved request' : null,
                $actionStatuses->isNotEmpty() ? 'exact generated action record' : null,
            ])->filter()->implode(' and ');

            return [
                'applied',
                (int) $evidenceStatuses->first(),
                "Backfilled from {$sources}.",
            ];
        }

        if ($evidenceStatuses->count() > 1) {
            return [
                'review_required',
                null,
                'Historical exact-barcode resolution evidence conflicts and requires review.',
            ];
        }

        if ($this->legacyStatusNeedsExactReview($communication->processing_status ?? null)) {
            return [
                'review_required',
                null,
                'Legacy message-level processing state requires confirmation for this exact barcode.',
            ];
        }

        return ['pending_review', null, null];
    }

    private function barcodes(mixed $value): Collection
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return collect(is_array($value) ? $value : [])
            ->map(fn ($barcode) => trim((string) $barcode))
            ->filter(fn (string $barcode) => $barcode !== '' && strlen($barcode) <= 20)
            ->uniqueStrict()
            ->values();
    }

    private function actionStatuses(string $action): Collection
    {
        preg_match_all(
            '/(?:^|\R)Approved (Halal|Not Halal) resolution applied to ([0-9]{1,20})\.(?=\R|$)/',
            $action,
            $matches,
            PREG_SET_ORDER,
        );

        return collect($matches)->groupBy(fn (array $match) => $match[2])->map(
            fn (Collection $barcodeMatches) => $barcodeMatches
                ->map(fn (array $match) => $match[1] === 'Halal' ? 0 : 1)
                ->unique()
                ->values(),
        );
    }

    private function linkedRequestStatuses(int $communicationId, string $barcode): Collection
    {
        if (! Schema::hasTable('prioritisation_requests')
            || ! Schema::hasColumn('prioritisation_requests', 'resolution_communication_id')) {
            return collect();
        }

        return DB::table('prioritisation_requests')
            ->where('resolution_communication_id', $communicationId)
            ->where('barcode', $barcode)
            ->where('status', 'resolved')
            ->whereIn('resolved_status', [0, 1])
            ->pluck('resolved_status')
            ->map(fn ($status) => (int) $status)
            ->unique()
            ->values();
    }

    private function exactProductId(string $barcode): ?int
    {
        if (! Schema::hasTable('products')) {
            return null;
        }

        $id = DB::table('products')->where('Barcode', $barcode)->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    private function legacyStatusNeedsExactReview(?string $status): bool
    {
        return in_array($status, [
            'applied',
            'processed',
            'no_action',
            'kept_unreviewed',
            'awaiting_clarification',
            'awaiting_user_information',
            'needs_exact_barcode',
        ], true);
    }

    private function recomputeParent(int $communicationId, object $communication): void
    {
        $rows = DB::table('brand_communication_barcode_dispositions')
            ->where('brand_communication_id', $communicationId)
            ->get(['disposition', 'decided_at']);
        $applied = $rows->where('disposition', 'applied')->count();
        $reviewRequired = $rows->where('disposition', 'review_required')->count();
        $pending = $rows->where('disposition', 'pending_review')->count();
        $incomplete = $reviewRequired + $pending;

        if ($incomplete === 0) {
            DB::table('brand_communications')->where('id', $communicationId)->update([
                'processing_status' => 'applied',
                'processed_at' => $communication->processed_at
                    ?? $rows->max('decided_at')
                    ?? $communication->updated_at
                    ?? now(),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('brand_communications')->where('id', $communicationId)->update([
            'processing_status' => $applied > 0
                ? 'partially_processed'
                : ($reviewRequired > 0 ? 'review_required' : 'pending_review'),
            'processed_at' => null,
            'updated_at' => now(),
        ]);
    }
};
