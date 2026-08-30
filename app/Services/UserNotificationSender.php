<?php

namespace App\Services;

use App\Mail\UserNotificationEmail;
use App\Models\RequestNotificationDelivery;
use App\Support\UserInformationReplyReference;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Schema;

class UserNotificationSender
{
    public function __construct(private readonly Mailer $mailer) {}

    public function send(RequestNotificationDelivery $delivery): void
    {
        $replyReference = null;
        $outboundMessageId = null;
        if (in_array($delivery->notification_type, [
            UserNotificationEmail::TYPE_INFORMATION_REQUEST,
            UserNotificationEmail::TYPE_LEGACY_PHOTO_REQUEST,
        ], true)) {
            if (! in_array($delivery->status, ['pending', 'failed', 'sending'], true)
                && (blank($delivery->reply_reference) || blank($delivery->outbound_message_id))) {
                throw new \LogicException(
                    'A threading identity cannot be backfilled onto an information request that was already sent.'
                );
            }
            $expectedReplyReference = UserInformationReplyReference::forRequests(
                $delivery->request_ids ?? [],
                (string) $delivery->barcode,
            );
            $replyReference = trim((string) ($delivery->reply_reference ?? ''));
            if ($replyReference !== '' && ! hash_equals($replyReference, $expectedReplyReference)) {
                throw new \LogicException('The stored information-request reply reference is inconsistent.');
            }
            $replyReference = $expectedReplyReference;

            $expectedOutboundMessageId = UserInformationReplyReference::normalizeMessageId(
                UserInformationReplyReference::outboundMessageId(
                    (int) $delivery->id,
                    (string) $delivery->event_key,
                ),
            );
            $outboundMessageId = trim((string) ($delivery->outbound_message_id ?? ''));
            if ($outboundMessageId !== '') {
                $outboundMessageId = UserInformationReplyReference::normalizeMessageId($outboundMessageId);
                if (! hash_equals($outboundMessageId, $expectedOutboundMessageId)) {
                    throw new \LogicException('The stored information-request Message-ID is inconsistent.');
                }
            }
            $outboundMessageId = $expectedOutboundMessageId;

            $updates = [];
            if (Schema::hasColumn('request_notification_deliveries', 'reply_reference')
                && $delivery->reply_reference !== $replyReference) {
                $updates['reply_reference'] = $replyReference;
            }
            if (Schema::hasColumn('request_notification_deliveries', 'outbound_message_id')
                && $delivery->outbound_message_id !== $outboundMessageId) {
                $updates['outbound_message_id'] = $outboundMessageId;
            }
            $outboundMessageIdHash = hash('sha256', $outboundMessageId);
            if (filled($delivery->outbound_message_id_hash)
                && ! hash_equals((string) $delivery->outbound_message_id_hash, $outboundMessageIdHash)) {
                throw new \LogicException('The stored information-request Message-ID hash is inconsistent.');
            }
            if (Schema::hasColumn('request_notification_deliveries', 'outbound_message_id_hash')
                && $delivery->outbound_message_id_hash !== $outboundMessageIdHash) {
                $updates['outbound_message_id_hash'] = $outboundMessageIdHash;
            }
            if ($updates !== []) {
                $delivery->update($updates);
            }
        }

        $this->mailer->to($delivery->recipient_email)->send(new UserNotificationEmail(
            $delivery->notification_type,
            $delivery->product_name,
            $delivery->barcode,
            $delivery->halal_status === null ? null : (string) $delivery->halal_status,
            $replyReference,
            $outboundMessageId,
        ));
    }
}
