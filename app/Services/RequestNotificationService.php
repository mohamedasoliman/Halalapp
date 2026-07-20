<?php

namespace App\Services;

use App\Models\RequestNotificationDelivery;
use Illuminate\Support\Collection;
use Throwable;

class RequestNotificationService
{
    public function __construct(
        private readonly RequestRecipientService $recipients,
        private readonly UserNotificationSender $sender,
    ) {}

    public function prepareEvent(
        string $eventReference,
        Collection $requests,
        string $notificationType,
        string $productName,
        string $barcode,
        ?string $halalStatus = null,
        ?int $brandCommunicationId = null,
    ): Collection {
        $eventKey = $this->eventKey($eventReference);
        $requestIds = $requests->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        return $this->recipients->collect($requests)->map(function (string $email) use (
            $eventKey,
            $eventReference,
            $requestIds,
            $brandCommunicationId,
            $notificationType,
            $productName,
            $barcode,
            $halalStatus,
        ) {
            return RequestNotificationDelivery::firstOrCreate(
                [
                    'event_key' => $eventKey,
                    'recipient_hash' => hash('sha256', $email),
                ],
                [
                    'event_reference' => $eventReference,
                    'request_ids' => $requestIds,
                    'brand_communication_id' => $brandCommunicationId,
                    'notification_type' => $notificationType,
                    'recipient_email' => $email,
                    'normalized_email' => $email,
                    'product_name' => $productName,
                    'barcode' => $barcode,
                    'halal_status' => $halalStatus === null ? null : (int) $halalStatus,
                    'status' => 'pending',
                ],
            );
        });
    }

    public function deliverEvent(string $eventReference): array
    {
        $eventKey = $this->eventKey($eventReference);
        $deliveries = RequestNotificationDelivery::query()
            ->where('event_key', $eventKey)
            ->whereIn('status', ['pending', 'failed'])
            ->orderBy('id')
            ->get();

        $result = [
            'sent' => 0,
            'failed' => 0,
            'uncertain' => RequestNotificationDelivery::query()
                ->where('event_key', $eventKey)
                ->where('status', 'uncertain')
                ->count(),
            'sending' => RequestNotificationDelivery::query()
                ->where('event_key', $eventKey)
                ->where('status', 'sending')
                ->count(),
            'skipped' => 0,
        ];
        foreach ($deliveries as $delivery) {
            if (! $this->claim($delivery)) {
                $result['skipped']++;

                continue;
            }

            try {
                $this->sender->send($delivery->fresh());
                $delivery->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'error' => null,
                ]);
                $result['sent']++;
            } catch (Throwable $exception) {
                $delivery->update([
                    // A transport exception cannot prove that the remote server rejected the message.
                    'status' => 'uncertain',
                    'uncertain_at' => now(),
                    'error' => mb_substr($exception->getMessage(), 0, 5000),
                ]);
                $result['uncertain']++;
            }
        }

        return $result;
    }

    public function eventKey(string $eventReference): string
    {
        return hash('sha256', strtolower(trim($eventReference)));
    }

    private function claim(RequestNotificationDelivery $delivery): bool
    {
        $updated = RequestNotificationDelivery::query()
            ->whereKey($delivery->id)
            ->whereIn('status', ['pending', 'failed'])
            ->update([
                'status' => 'sending',
                'attempts' => $delivery->attempts + 1,
                'last_attempted_at' => now(),
            ]);

        return $updated === 1;
    }
}
