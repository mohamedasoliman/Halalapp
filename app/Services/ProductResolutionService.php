<?php

namespace App\Services;

use App\Models\BrandCommunication;
use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProductResolutionService
{
    public function __construct(
        private readonly RequestNotificationService $notifications,
        private readonly BrandCommunicationDispositionService $dispositions,
    ) {}

    public function resolve(
        string $barcode,
        string $status,
        string $notes = '',
        ?string $proofPath = null,
        ?int $brandCommunicationId = null,
        ?string $eventReference = null,
        bool $notify = true,
        ?string $publicNote = null,
    ): array {
        if (! in_array($status, ['0', '1'], true)) {
            throw new InvalidArgumentException('Status must be 0 (halal) or 1 (not halal).');
        }

        $publicNote = $this->validatedPublicNote($publicNote);
        $eventReference ??= $brandCommunicationId
            ? "resolution:communication:{$brandCommunicationId}:barcode:{$barcode}:status:{$status}"
            : 'resolution:'.Str::uuid();

        $result = DB::transaction(function () use (
            $barcode,
            $status,
            $notes,
            $proofPath,
            $brandCommunicationId,
            $eventReference,
            $publicNote,
        ) {
            $product = Product::query()
                ->where('Barcode', $barcode)
                ->lockForUpdate()
                ->first();
            if (! $product) {
                throw (new ModelNotFoundException)->setModel(Product::class, [$barcode]);
            }

            $communication = null;
            if ($brandCommunicationId) {
                $communication = BrandCommunication::query()->lockForUpdate()->findOrFail($brandCommunicationId);
                $mentionedBarcodes = collect($communication->barcodes_mentioned ?? [])
                    ->map(fn ($mentioned) => trim((string) $mentioned));
                if ($communication->direction !== 'inbound'
                    || ! $mentionedBarcodes->contains(fn (string $mentioned) => $mentioned === $barcode)) {
                    throw new InvalidArgumentException(
                        'The selected inbound communication does not explicitly cover this exact barcode.'
                    );
                }
                $proofPath ??= $communication->proof_path;
                if (! $proofPath) {
                    throw new InvalidArgumentException(
                        'The approved inbound communication must have a saved proof path before resolution.'
                    );
                }
            }

            $requests = PrioritisationRequest::with('watchers')
                ->where('barcode', $barcode)
                ->whereNotIn('status', ['resolved', 'dead_end'])
                ->lockForUpdate()
                ->get();

            $statusLabel = $status === '0' ? 'Halal' : 'Not Halal';
            $auditNote = $this->auditNote($statusLabel, $notes, $proofPath, $brandCommunicationId);
            $productUpdates = [
                'halal_status' => $status,
            ];
            if (filled($publicNote)) {
                $productUpdates['notes'] = trim($publicNote);
            }
            if ($proofPath && empty($product->proof)) {
                $productUpdates['proof'] = $proofPath;
            }
            $product->update($productUpdates);

            foreach ($requests as $request) {
                $request->update([
                    'status' => 'resolved',
                    'resolved_status' => (int) $status,
                    'resolution_communication_id' => $brandCommunicationId,
                    'notes' => $this->appendNote($request->notes, $auditNote),
                ]);
            }

            if ($communication) {
                $this->dispositions->recordApplied(
                    $communication,
                    $barcode,
                    (int) $status,
                    (int) $product->id,
                    filled($notes) ? trim($notes) : null,
                );
                $actionLine = "Approved {$statusLabel} resolution applied to {$barcode}.";
                $existingActions = collect(preg_split('/\R/', (string) $communication->action_taken))
                    ->map(fn ($line) => trim((string) $line));
                $communication->update([
                    'action_taken' => trim(implode("\n", array_filter([
                        $communication->action_taken,
                        $existingActions->containsStrict($actionLine) ? null : $actionLine,
                    ]))),
                ]);
            }

            $deliveries = $this->notifications->prepareEvent(
                $eventReference,
                $requests,
                'resolved',
                $product->product_name ?? 'Unknown Product',
                $barcode,
                $status,
                $brandCommunicationId,
            );

            return [
                'event_reference' => $eventReference,
                'product_name' => $product->product_name ?? 'Unknown Product',
                'requests_resolved' => $requests->count(),
                'recipients_prepared' => $deliveries->count(),
            ];
        });

        Cache::increment('products_cache_version');
        $result['delivery'] = $notify
            ? $this->notifications->deliverEvent($eventReference)
            : ['sent' => 0, 'failed' => 0, 'uncertain' => 0, 'sending' => 0, 'skipped' => 0];

        return $result;
    }

    private function auditNote(
        string $statusLabel,
        string $notes,
        ?string $proofPath,
        ?int $brandCommunicationId,
    ): string {
        $context = collect([
            "Resolved {$statusLabel} on ".now()->toDateString().'.',
            trim($notes),
            $brandCommunicationId ? "Inbound communication #{$brandCommunicationId}." : null,
            $proofPath ? "Proof: {$proofPath}." : null,
        ])->filter()->implode(' ');

        return trim($context);
    }

    private function validatedPublicNote(?string $note): ?string
    {
        $note = trim((string) $note);
        if ($note === '') {
            return null;
        }
        if (Str::length($note) > 255) {
            throw new InvalidArgumentException('The user-facing product note must not exceed 255 characters.');
        }
        if (preg_match('/\b20\d{2}\b|Proof:|Brand_Proofs|\/Users\/|https?:\/\/|Inbound communication #/i', $note)) {
            throw new InvalidArgumentException(
                'The user-facing product note cannot contain dates, proof locations, or internal communication IDs.'
            );
        }

        return $note;
    }

    private function appendNote(?string $existing, string $note): string
    {
        return trim(implode("\n", array_filter([trim((string) $existing), trim($note)])));
    }
}
