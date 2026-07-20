<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\BrandCommunication;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

class InboundBrandCommunicationService
{
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

        $attributes = ['email_message_id' => $normalizedMessageId];
        $values = [
            'brand_id' => $brand->id,
            'direction' => 'inbound',
            'subject' => $subject,
            'body_preview' => $bodySummary,
            'barcodes_mentioned' => collect($barcodes)
                ->map(fn ($barcode) => trim((string) $barcode))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'proof_path' => $proofPath,
            'processing_status' => 'pending_review',
        ];

        try {
            return BrandCommunication::firstOrCreate($attributes, $values);
        } catch (QueryException $exception) {
            $existing = BrandCommunication::where($attributes)->first();
            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }
}
