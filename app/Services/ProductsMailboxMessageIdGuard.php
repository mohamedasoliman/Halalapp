<?php

namespace App\Services;

use App\Models\BrandCommunication;
use App\Models\UserInformationReply;
use App\Support\UserInformationReplyReference;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ProductsMailboxMessageIdGuard
{
    public const FLOW_MANUFACTURER = 'manufacturer';

    public const FLOW_USER_INFORMATION = 'user_information';

    public function normalize(string $messageId): string
    {
        try {
            return UserInformationReplyReference::normalizeMessageId($messageId);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException('A single valid email Message-ID is required.');
        }
    }

    public function hash(string $normalizedMessageId): string
    {
        return hash('sha256', $this->normalize($normalizedMessageId));
    }

    public function withClaimLock(string $messageId, string $flow, callable $callback): mixed
    {
        if (! in_array($flow, [self::FLOW_MANUFACTURER, self::FLOW_USER_INFORMATION], true)) {
            throw new InvalidArgumentException('The products-mailbox processing flow is invalid.');
        }

        $normalized = $this->normalize($messageId);
        $hash = hash('sha256', $normalized);
        $lock = Cache::lock(
            "products-mailbox:message-id:{$hash}",
            max((int) config('prioritisation.message_id_lock_seconds', 600), 60),
        );
        if (! $lock->get()) {
            throw new InvalidArgumentException('This products-mailbox message is already being processed. Try again shortly.');
        }

        try {
            $this->assertNotClaimedByOtherFlow($normalized, $hash, $flow);

            return $callback($normalized, $hash);
        } finally {
            $lock->release();
        }
    }

    public function assertNotClaimedByOtherFlow(
        string $normalizedMessageId,
        string $messageIdHash,
        string $flow,
    ): void {
        if ($flow === self::FLOW_USER_INFORMATION
            && Schema::hasTable('brand_communications')
            && Schema::hasColumn('brand_communications', 'email_message_id')
            && BrandCommunication::query()
                ->where(function ($query) use ($normalizedMessageId) {
                    $query->whereRaw('LOWER(email_message_id) = ?', [strtolower($normalizedMessageId)])
                        ->orWhereRaw('LOWER(email_message_id) = ?', [
                            strtolower(trim($normalizedMessageId, '<>')),
                        ]);
                })
                ->exists()) {
            throw new InvalidArgumentException(
                'This Message-ID is already owned by the manufacturer-reply flow; no user-information reply was recorded.'
            );
        }

        if ($flow === self::FLOW_MANUFACTURER
            && Schema::hasTable('user_information_replies')
            && UserInformationReply::query()->where('message_id_hash', $messageIdHash)->exists()) {
            throw new InvalidArgumentException(
                'This Message-ID is already owned by the user-information flow; no manufacturer reply was recorded.'
            );
        }
    }
}
