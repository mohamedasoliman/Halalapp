<?php

namespace App\Services;

use App\Models\BrandCommunication;
use App\Models\BrandCommunicationBarcodeDisposition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BrandCommunicationDispositionService
{
    public const NON_VERDICT_DISPOSITIONS = [
        'kept_unreviewed',
        'needs_clarification',
        'no_action',
    ];

    public function seedBarcodeRows(BrandCommunication $communication, array $barcodes): void
    {
        if ($communication->direction !== 'inbound') {
            return;
        }

        collect($barcodes)
            ->map(fn ($barcode) => trim((string) $barcode))
            ->filter()
            ->uniqueStrict()
            ->each(function (string $barcode) use ($communication) {
                BrandCommunicationBarcodeDisposition::firstOrCreate([
                    'brand_communication_id' => $communication->id,
                    'barcode' => $barcode,
                ], [
                    'disposition' => 'pending_review',
                ]);
            });
    }

    public function recordApplied(
        BrandCommunication $communication,
        string $barcode,
        int $resolvedStatus,
        int $productId,
        ?string $reason = null,
    ): BrandCommunicationBarcodeDisposition {
        if (! in_array($resolvedStatus, [0, 1], true)) {
            throw new InvalidArgumentException('An applied barcode disposition requires verdict 0 or 1.');
        }

        return $this->record(
            $communication,
            $barcode,
            'applied',
            $resolvedStatus,
            $productId,
            $reason,
        );
    }

    public function recordNonVerdict(
        int $communicationId,
        string $barcode,
        string $disposition,
        ?string $reason = null,
    ): BrandCommunicationBarcodeDisposition {
        if (! in_array($disposition, self::NON_VERDICT_DISPOSITIONS, true)) {
            throw new InvalidArgumentException(
                'Disposition must be kept_unreviewed, needs_clarification, or no_action.'
            );
        }

        return DB::transaction(function () use ($communicationId, $barcode, $disposition, $reason) {
            $communication = BrandCommunication::query()->lockForUpdate()->findOrFail($communicationId);

            return $this->record($communication, $barcode, $disposition, null, null, $reason);
        });
    }

    public function refreshAggregate(BrandCommunication $communication): void
    {
        $rows = BrandCommunicationBarcodeDisposition::query()
            ->where('brand_communication_id', $communication->id)
            ->lockForUpdate()
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        [$status, $complete] = $this->aggregateState($rows);
        $communication->update([
            'processing_status' => $status,
            'processed_at' => $complete ? ($communication->processed_at ?? now()) : null,
        ]);
    }

    private function record(
        BrandCommunication $communication,
        string $barcode,
        string $disposition,
        ?int $resolvedStatus,
        ?int $productId,
        ?string $reason,
    ): BrandCommunicationBarcodeDisposition {
        $barcode = trim($barcode);
        if ($communication->direction !== 'inbound') {
            throw new InvalidArgumentException('Barcode dispositions can only be recorded for inbound communications.');
        }

        $covered = collect($communication->barcodes_mentioned ?? [])
            ->map(fn ($mentioned) => trim((string) $mentioned))
            ->containsStrict($barcode);
        if ($barcode === '' || ! $covered) {
            throw new InvalidArgumentException(
                'The selected inbound communication does not explicitly cover this exact barcode.'
            );
        }

        $row = BrandCommunicationBarcodeDisposition::query()
            ->where('brand_communication_id', $communication->id)
            ->where('barcode', $barcode)
            ->lockForUpdate()
            ->first();
        if (! $row) {
            $row = BrandCommunicationBarcodeDisposition::create([
                'brand_communication_id' => $communication->id,
                'barcode' => $barcode,
                'disposition' => 'pending_review',
            ]);
        }

        if ($row->isTerminal()) {
            $sameDecision = $row->disposition === $disposition
                && ($disposition !== 'applied' || $row->resolved_status === $resolvedStatus);
            if (! $sameDecision) {
                throw new InvalidArgumentException(
                    "Barcode {$barcode} already has a different terminal disposition for this communication."
                );
            }

            $this->refreshAggregate($communication);

            return $row;
        }

        $row->update([
            'product_id' => $productId,
            'disposition' => $disposition,
            'resolved_status' => $resolvedStatus,
            'reason' => filled($reason) ? trim((string) $reason) : null,
            'decided_at' => now(),
        ]);

        $this->refreshAggregate($communication);

        return $row->fresh();
    }

    private function aggregateState(Collection $rows): array
    {
        $terminal = $rows->filter(
            fn (BrandCommunicationBarcodeDisposition $row) => $row->isTerminal()
        );
        $incomplete = $rows->reject(
            fn (BrandCommunicationBarcodeDisposition $row) => $row->isTerminal()
        );

        if ($incomplete->isNotEmpty()) {
            if ($terminal->isNotEmpty()) {
                return ['partially_processed', false];
            }

            return [
                $incomplete->contains('disposition', 'review_required') ? 'review_required' : 'pending_review',
                false,
            ];
        }

        return [
            $terminal->every(fn (BrandCommunicationBarcodeDisposition $row) => $row->disposition === 'applied')
                ? 'applied'
                : 'processed',
            true,
        ];
    }
}
