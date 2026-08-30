<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class UserNotificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public const TYPE_CONTACTED = 'contacted';

    public const TYPE_INFORMATION_REQUEST = 'information_request';

    public const TYPE_LEGACY_PHOTO_REQUEST = 'photo_request';

    public const TYPE_RESOLVED = 'resolved';

    public string $notificationType;

    public string $productName;

    public string $barcode;

    public ?string $halalStatus;

    public ?string $replyReference;

    public ?string $outboundMessageId;

    public function __construct(
        string $notificationType,
        string $productName,
        string $barcode,
        ?string $halalStatus = null,
        ?string $replyReference = null,
        ?string $outboundMessageId = null,
    ) {
        $this->notificationType = $notificationType;
        $this->productName = $productName;
        $this->barcode = $barcode;
        $this->halalStatus = $halalStatus;
        $this->replyReference = $replyReference;
        $this->outboundMessageId = $outboundMessageId;
    }

    public function build()
    {
        $subject = match ($this->notificationType) {
            self::TYPE_CONTACTED => "Update on your request: {$this->productName}",
            self::TYPE_INFORMATION_REQUEST,
            self::TYPE_LEGACY_PHOTO_REQUEST => "More information needed: {$this->productName}".
                ($this->replyReference ? " [{$this->replyReference}]" : ''),
            self::TYPE_RESOLVED => "Confirmed: {$this->productName} is ".($this->halalStatus === '0' ? 'Halal' : 'Not Halal'),
            default => "Update: {$this->productName}",
        };

        $email = $this->subject($subject)
            ->from('halalkiwi@halalkiwi.com', 'Halal Kiwi')
            ->view('user_notification_email');

        if (in_array($this->notificationType, [
            self::TYPE_INFORMATION_REQUEST,
            self::TYPE_LEGACY_PHOTO_REQUEST,
        ], true)) {
            $email->replyTo(config('outreach.reply_to', 'products@halalkiwi.com'), 'Halal Kiwi Products');
        }

        return $email;
    }

    public function headers(): Headers
    {
        if (! $this->isInformationRequest() || ! $this->replyReference || ! $this->outboundMessageId) {
            return new Headers;
        }

        return new Headers(
            messageId: trim($this->outboundMessageId, '<> '),
            text: [
                'X-Halal-Kiwi-Information-Reference' => $this->replyReference,
            ],
        );
    }

    private function isInformationRequest(): bool
    {
        return in_array($this->notificationType, [
            self::TYPE_INFORMATION_REQUEST,
            self::TYPE_LEGACY_PHOTO_REQUEST,
        ], true);
    }
}
