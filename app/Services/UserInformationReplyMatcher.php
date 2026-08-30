<?php

namespace App\Services;

use App\Mail\UserNotificationEmail;
use App\Models\PrioritisationRequest;
use App\Models\RequestNotificationDelivery;
use App\Support\ProductBarcode;
use App\Support\UserInformationReplyReference;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class UserInformationReplyMatcher
{
    /**
     * @return array{
     *   matched: bool,
     *   request: ?PrioritisationRequest,
     *   delivery: ?RequestNotificationDelivery,
     *   barcode: ?string,
     *   method: ?string,
     *   confidence: ?string,
     *   requires_legacy_approval: bool,
     *   reason: ?string
     * }
     */
    public function match(array $message): array
    {
        $sender = $this->normalizeEmail((string) ($message['from_address'] ?? ''));
        $thread = $this->matchThreadHeaders($message, $sender);
        $reference = $this->matchReplyReference($message, $sender);

        if ($thread['matched'] && $reference['matched']) {
            if ((int) $thread['request']->id !== (int) $reference['request']->id
                || ProductBarcode::key($thread['barcode']) !== ProductBarcode::key($reference['barcode'])) {
                throw new InvalidArgumentException(
                    'The email thread headers and HK-INFO reference point to different requests. Manual review is required.'
                );
            }

            return $thread;
        }
        if ($thread['matched'] && $reference['reason'] !== null) {
            throw new InvalidArgumentException(
                'The email thread matches one request but its HK-INFO reference is invalid or conflicting. Manual review is required.'
            );
        }
        if ($reference['matched'] && $thread['reason'] !== null) {
            throw new InvalidArgumentException(
                'The HK-INFO reference matches one request but the email thread headers are ambiguous. Manual review is required.'
            );
        }
        if ($thread['matched']) {
            return $thread;
        }
        if ($reference['matched']) {
            return $reference;
        }

        if ($reference['reason'] !== null) {
            return $this->unmatched($reference['reason']);
        }
        if ($thread['reason'] !== null) {
            return $this->unmatched($thread['reason']);
        }

        if ($this->hasManufacturerReference($message)) {
            return $this->unmatched(
                'The message contains a manufacturer-outreach reference and no valid user-information thread. Route it to manufacturer review.'
            );
        }

        return $this->matchLegacySenderAndBarcode($message, $sender);
    }

    private function matchThreadHeaders(array $message, string $sender): array
    {
        $messageIds = $this->threadMessageIds($message);
        if ($messageIds->isEmpty()) {
            return $this->unmatched();
        }

        $deliveries = RequestNotificationDelivery::query()
            ->whereIn('outbound_message_id_hash', $messageIds->map(fn (string $id) => hash('sha256', $id)))
            ->whereIn('notification_type', $this->informationTypes())
            ->whereIn('status', $this->matchableDeliveryStatuses())
            ->get()
            ->filter(fn (RequestNotificationDelivery $delivery) => $this->deliverySenderMatches($delivery, $sender))
            ->values();

        if ($deliveries->count() > 1) {
            return $this->unmatched('Multiple outbound information requests match the email thread headers.');
        }
        if ($deliveries->isEmpty()) {
            return $this->unmatched();
        }

        $delivery = $deliveries->first();
        $request = $this->requestForDelivery($delivery);
        if (! $request) {
            return $this->unmatched('The matched outbound delivery no longer maps to one exact prioritisation request.');
        }

        return $this->matched($request, $delivery, (string) $delivery->barcode, 'outbound_message_id', 'high');
    }

    private function matchReplyReference(array $message, string $sender): array
    {
        $references = $this->replyReferences($message);
        if ($references->isEmpty()) {
            return $this->unmatched();
        }
        if ($references->count() > 1) {
            return $this->unmatched('More than one HK-INFO reference appears in the message.');
        }

        $reference = $references->first();
        $deliveries = RequestNotificationDelivery::query()
            ->where('reply_reference', $reference['value'])
            ->whereIn('notification_type', $this->informationTypes())
            ->whereIn('status', $this->matchableDeliveryStatuses())
            ->get()
            ->filter(fn (RequestNotificationDelivery $delivery) => $this->deliverySenderMatches($delivery, $sender))
            ->values();

        if ($deliveries->count() !== 1) {
            return $this->unmatched(
                $deliveries->isEmpty()
                    ? 'The HK-INFO reference is not backed by a sent information request to this sender.'
                    : 'The HK-INFO reference is ambiguous for this sender.'
            );
        }

        $delivery = $deliveries->first();
        if (ProductBarcode::key((string) $delivery->barcode) !== ProductBarcode::key($reference['barcode'])) {
            return $this->unmatched('The HK-INFO barcode conflicts with the matched delivery.');
        }
        if (! collect($delivery->request_ids ?? [])->map(fn ($id) => (int) $id)->contains($reference['request_id'])) {
            return $this->unmatched('The HK-INFO request ID conflicts with the matched delivery.');
        }

        $request = $this->requestForDelivery($delivery, $reference['request_id'], $reference['barcode']);
        if (! $request) {
            return $this->unmatched('The HK-INFO reference no longer maps to one exact prioritisation request.');
        }

        return $this->matched($request, $delivery, $reference['barcode'], 'reply_reference', 'high');
    }

    private function matchLegacySenderAndBarcode(array $message, string $sender): array
    {
        $barcodes = $this->exactBarcodes($message);
        if ($barcodes->count() !== 1) {
            return $this->unmatched(
                $barcodes->isEmpty()
                    ? 'No exact barcode could be found for the legacy fallback.'
                    : 'Multiple exact barcodes make the legacy fallback ambiguous.'
            );
        }

        $barcode = $barcodes->first();
        $deliveries = RequestNotificationDelivery::query()
            ->whereIn('notification_type', $this->informationTypes())
            ->whereIn('status', $this->matchableDeliveryStatuses())
            ->whereNull('outbound_message_id_hash')
            ->get()
            ->filter(fn (RequestNotificationDelivery $delivery) => $this->deliverySenderMatches($delivery, $sender))
            ->filter(fn (RequestNotificationDelivery $delivery) => ProductBarcode::key((string) $delivery->barcode) === ProductBarcode::key($barcode))
            ->values();

        $matches = $deliveries->map(function (RequestNotificationDelivery $delivery) use ($barcode) {
            $request = $this->requestForDelivery($delivery, null, $barcode);

            return $request ? compact('delivery', 'request') : null;
        })->filter()->unique(fn (array $match) => $match['request']->id)->values();

        if ($matches->count() !== 1) {
            return $this->unmatched(
                $matches->isEmpty()
                    ? 'No legacy sent information request matches this sender and exact barcode.'
                    : 'More than one request matches the legacy sender-and-barcode fallback.'
            );
        }

        $match = $matches->first();

        return $this->matched(
            $match['request'],
            $match['delivery'],
            $barcode,
            'legacy_sender_barcode',
            'reviewed_legacy',
            true,
        );
    }

    private function requestForDelivery(
        RequestNotificationDelivery $delivery,
        ?int $preferredRequestId = null,
        ?string $barcode = null,
    ): ?PrioritisationRequest {
        $barcode ??= (string) $delivery->barcode;
        $requestIds = collect($delivery->request_ids ?? [])->map(fn ($id) => (int) $id)->filter()->unique();
        if ($preferredRequestId === null) {
            $preferredRequestId = $this->requestIdFromReplyReference(
                (string) ($delivery->reply_reference ?? ''),
                $barcode,
            );
        }
        if ($preferredRequestId !== null && ! $requestIds->contains($preferredRequestId)) {
            return null;
        }

        $requests = PrioritisationRequest::query()
            ->whereIn('id', $requestIds)
            ->orderBy('id')
            ->get()
            ->filter(fn (PrioritisationRequest $request) => ProductBarcode::key((string) $request->barcode) === ProductBarcode::key($barcode));

        if ($preferredRequestId !== null) {
            $preferred = $requests->firstWhere('id', $preferredRequestId);
            if (! $preferred) {
                return null;
            }
            if (! in_array($preferred->status, ['dead_end'], true)) {
                return $preferred;
            }
            if ($successor = $this->auditedMergedSuccessor($preferred, $barcode)) {
                return $successor;
            }

            return $preferred;
        }

        // Never jump to a later same-barcode request merely because it is
        // active. The delivery's immutable request_ids define the scope;
        // only an explicit merge audit note may route to a successor.
        $active = $requests->reject(fn (PrioritisationRequest $request) => in_array(
            $request->status,
            ['resolved', 'dead_end'],
            true,
        ));
        if ($active->count() === 1) {
            return $active->first();
        }

        if ($requests->count() === 1) {
            return $requests->first();
        }

        return null;
    }

    private function requestIdFromReplyReference(string $reference, string $barcode): ?int
    {
        if (preg_match('/^HK-INFO-(\d+)-(\d{8,14})$/D', trim($reference), $match) !== 1
            || ProductBarcode::key($match[2]) !== ProductBarcode::key($barcode)) {
            return null;
        }

        return (int) $match[1];
    }

    private function auditedMergedSuccessor(
        PrioritisationRequest $request,
        string $barcode,
    ): ?PrioritisationRequest {
        if (preg_match('/\bMerged into active request #(\d+)\b/', (string) $request->notes, $match) !== 1) {
            return null;
        }

        $successor = PrioritisationRequest::query()->find((int) $match[1]);
        if (! $successor
            || in_array($successor->status, ['resolved', 'dead_end'], true)
            || ProductBarcode::key((string) $successor->barcode) !== ProductBarcode::key($barcode)) {
            return null;
        }

        return $successor;
    }

    private function deliverySenderMatches(RequestNotificationDelivery $delivery, string $sender): bool
    {
        $recipient = strtolower(trim((string) ($delivery->normalized_email ?: $delivery->recipient_email)));
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL) || str_ends_with($recipient, '@halalkiwi.com')) {
            return false;
        }

        return hash_equals($recipient, $sender);
    }

    private function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || str_ends_with($email, '@halalkiwi.com')) {
            throw new InvalidArgumentException('The reply sender must be a valid external email address.');
        }

        return $email;
    }

    private function threadMessageIds(array $message): Collection
    {
        $values = [(string) ($message['in_reply_to'] ?? '')];
        foreach ((array) ($message['references'] ?? $message['references_header'] ?? []) as $reference) {
            $values[] = (string) $reference;
        }

        return collect($values)
            ->flatMap(function (string $value) {
                preg_match_all('/<[^<>\s]+@[^<>\s]+>/', $value, $matches);

                return $matches[0] ?? [];
            })
            ->map(function (string $messageId) {
                try {
                    return UserInformationReplyReference::normalizeMessageId($messageId);
                } catch (InvalidArgumentException) {
                    return null;
                }
            })
            ->filter()
            ->unique()
            ->values();
    }

    private function replyReferences(array $message): Collection
    {
        $text = implode("\n", [
            (string) ($message['subject'] ?? ''),
            (string) ($message['body'] ?? ''),
            (string) (($message['raw_headers']['x-halal-kiwi-information-reference'] ?? '') ?: ''),
        ]);
        preg_match_all('/\bHK-INFO-(\d+)-(\d{8,14})\b/i', $text, $matches, PREG_SET_ORDER);

        return collect($matches)->map(fn (array $match) => [
            'value' => 'HK-INFO-'.(int) $match[1].'-'.$match[2],
            'request_id' => (int) $match[1],
            'barcode' => $match[2],
        ])->unique('value')->values();
    }

    private function exactBarcodes(array $message): Collection
    {
        preg_match_all('/(?<!\d)(\d{8,14})(?!\d)/', implode("\n", [
            (string) ($message['subject'] ?? ''),
            (string) ($message['body'] ?? ''),
        ]), $matches);

        return collect($matches[1] ?? [])
            ->filter(fn (string $barcode) => ProductBarcode::key($barcode) !== null)
            ->unique(fn (string $barcode) => ProductBarcode::key($barcode))
            ->values();
    }

    private function hasManufacturerReference(array $message): bool
    {
        $text = (string) ($message['subject'] ?? '')."\n".(string) ($message['body'] ?? '');

        return preg_match('/\bHK-(?!INFO-)[A-Z0-9][A-Z0-9-]*\b/i', $text) === 1;
    }

    private function informationTypes(): array
    {
        return [
            UserNotificationEmail::TYPE_INFORMATION_REQUEST,
            UserNotificationEmail::TYPE_LEGACY_PHOTO_REQUEST,
        ];
    }

    private function matchableDeliveryStatuses(): array
    {
        // A reply with an exact stored identity and exact recipient proves
        // that an SMTP-uncertain delivery reached the remote mailbox. Keep
        // the delivery's uncertain audit state unchanged.
        return ['sent', 'uncertain'];
    }

    private function matched(
        PrioritisationRequest $request,
        RequestNotificationDelivery $delivery,
        string $barcode,
        string $method,
        string $confidence,
        bool $legacy = false,
    ): array {
        return [
            'matched' => true,
            'request' => $request,
            'delivery' => $delivery,
            'barcode' => $barcode,
            'method' => $method,
            'confidence' => $confidence,
            'requires_legacy_approval' => $legacy,
            'reason' => null,
        ];
    }

    private function unmatched(?string $reason = null): array
    {
        return [
            'matched' => false,
            'request' => null,
            'delivery' => null,
            'barcode' => null,
            'method' => null,
            'confidence' => null,
            'requires_legacy_approval' => false,
            'reason' => $reason,
        ];
    }
}
