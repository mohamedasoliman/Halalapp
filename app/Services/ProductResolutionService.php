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
    public function __construct(private readonly RequestNotificationService $notifications) {}

    public function resolve(
        string $barcode,
        string $status,
        string $notes = '',
        ?string $proofPath = null,
        ?int $brandCommunicationId = null,
        ?string $eventReference = null,
        bool $notify = true,
    ): array {
        if (! in_array($status, ['0', '1'], true)) {
            throw new InvalidArgumentException('Status must be 0 (halal) or 1 (not halal).');
        }

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
                'notes' => $this->appendNote($product->notes, $auditNote),
            ];
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
                $communication->update([
                    'processing_status' => 'applied',
                    'processed_at' => now(),
                    'action_taken' => trim(implode("\n", array_filter([
                        $communication->action_taken,
                        "Approved {$statusLabel} resolution applied to {$barcode}.",
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

    private function appendNote(?string $existing, string $note): string
    {
        return trim(implode("\n", array_filter([trim((string) $existing), trim($note)])));
    }
}
