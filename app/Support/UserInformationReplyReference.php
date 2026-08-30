<?php

namespace App\Support;

use InvalidArgumentException;

final class UserInformationReplyReference
{
    public static function forRequests(iterable $requestIds, string $barcode): string
    {
        $requestIds = collect($requestIds)
            ->map(fn ($requestId) => filter_var($requestId, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]))
            ->filter(fn ($requestId) => $requestId !== false)
            ->map(fn ($requestId) => (int) $requestId);
        $requestId = $requestIds->min();
        $barcode = trim($barcode);

        if (! is_int($requestId) || preg_match('/^\d{8,14}$/D', $barcode) !== 1) {
            throw new InvalidArgumentException(
                'An information-reply reference requires a request ID and an exact 8-14 digit barcode.'
            );
        }

        return "HK-INFO-{$requestId}-{$barcode}";
    }

    public static function outboundMessageId(int $deliveryId, string $eventKey): string
    {
        $eventKey = strtolower(trim($eventKey));
        if ($deliveryId < 1 || preg_match('/^[a-f0-9]{64}$/D', $eventKey) !== 1) {
            throw new InvalidArgumentException(
                'An information-reply Message-ID requires a delivery ID and a SHA-256 event key.'
            );
        }

        return sprintf('<hk-info-%d-%s@halalkiwi.com>', $deliveryId, substr($eventKey, 0, 16));
    }

    public static function normalizeMessageId(string $messageId): string
    {
        $messageId = strtolower(trim($messageId));
        $messageId = '<'.trim($messageId, "<> \t\n\r\0\x0B").'>';

        if (strlen($messageId) > 998
            || preg_match('/^<[^<>\s]+@[^<>\s]+>$/D', $messageId) !== 1) {
            throw new InvalidArgumentException('The outbound information-request Message-ID is invalid.');
        }

        return $messageId;
    }
}
