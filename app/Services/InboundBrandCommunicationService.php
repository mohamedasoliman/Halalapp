<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\BrandCommunication;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InboundBrandCommunicationService
{
    public function __construct(
        private readonly BrandCommunicationDispositionService $dispositions,
    ) {}

    public function record(
        Brand $brand,
        string $messageId,
        ?string $subject,
        ?string $bodySummary,
        array $barcodes,
        ?string $proofPath = null,
    ): BrandCommunication {
        $normalizedMessageId = strtolower(trim($messageId));
        if ($normalizedMessageId === '') {
            throw new InvalidArgumentException('A manufacturer reply Message-ID is required.');
        }

        $barcodes = collect($barcodes)
            ->map(fn ($barcode) => trim((string) $barcode))
            ->filter()
            ->uniqueStrict()
            ->values();
        if ($barcodes->contains(fn (string $barcode) => preg_match('/^\d{8,14}$/D', $barcode) !== 1)) {
            throw new InvalidArgumentException('Manufacturer reply barcodes must be exact 8-14 digit values.');
        }

        $attributes = ['email_message_id' => $normalizedMessageId];
        $values = [
            'brand_id' => $brand->id,
            'direction' => 'inbound',
            'subject' => $subject,
            'body_preview' => $bodySummary,
            'barcodes_mentioned' => $barcodes->all(),
            'proof_path' => $proofPath,
            'processing_status' => 'pending_review',
        ];

        try {
            return DB::transaction(function () use ($attributes, $values, $barcodes) {
                $existing = BrandCommunication::query()
                    ->where($attributes)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    $this->assertCompatibleDuplicate($existing, $values);
                    $this->fillMissingEvidence($existing, $values);
                    $this->dispositions->seedBarcodeRows($existing, $barcodes->all());

                    return $existing->fresh();
                }

                $communication = BrandCommunication::create([...$attributes, ...$values]);
                $this->dispositions->seedBarcodeRows($communication, $barcodes->all());

                return $communication;
            });
        } catch (QueryException $exception) {
            $existing = BrandCommunication::where($attributes)->first();
            if ($existing) {
                $this->assertCompatibleDuplicate($existing, $values);

                return DB::transaction(function () use ($existing, $values, $barcodes) {
                    $locked = BrandCommunication::query()->lockForUpdate()->findOrFail($existing->id);
                    $this->fillMissingEvidence($locked, $values);
                    $this->dispositions->seedBarcodeRows($locked, $barcodes->all());

                    return $locked->fresh();
                });
            }

            throw $exception;
        }
    }

    private function assertCompatibleDuplicate(BrandCommunication $existing, array $values): void
    {
        $existingBarcodes = collect($existing->barcodes_mentioned ?? [])
            ->map(fn ($barcode) => trim((string) $barcode))
            ->filter()
            ->uniqueStrict()
            ->sort()
            ->values()
            ->all();
        $newBarcodes = collect($values['barcodes_mentioned'])->sort()->values()->all();

        if ($existing->direction !== 'inbound'
            || (int) $existing->brand_id !== (int) $values['brand_id']
            || $existingBarcodes !== $newBarcodes) {
            throw new InvalidArgumentException(
                'This Message-ID is already recorded with a different brand or exact-barcode scope.'
            );
        }
    }

    private function fillMissingEvidence(BrandCommunication $existing, array $values): void
    {
        $updates = [];
        foreach (['subject', 'body_preview', 'proof_path'] as $field) {
            if (blank($existing->{$field}) && filled($values[$field] ?? null)) {
                $updates[$field] = $values[$field];
            }
        }
        if ($updates !== []) {
            $existing->update($updates);
        }
    }
}
