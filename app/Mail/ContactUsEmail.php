<?php

namespace App\Mail;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class ContactUsEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportTicket $ticket,
        public SupportMessage $supportMessage,
    ) {}

    public function build(): self
    {
        $mail = $this->subject("[{$this->ticket->reference}] {$this->ticket->subject}")
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->replyTo(
                $this->ticket->requester_email,
                $this->ticket->requester_name ?: 'Halal Kiwi app user',
            )
            ->view('contact_email');

        foreach ($this->supportMessage->attachments as $attachment) {
            if ($attachment->security_status !== 'safe') {
                continue;
            }
            $path = storage_path('app/private/'.$attachment->path);
            if (is_file($path)) {
                $mail->attach($path, ['as' => $attachment->original_name, 'mime' => $attachment->mime_type]);
            }
        }

        return $mail;
    }

    public function headers(): Headers
    {
        return new Headers(
            messageId: self::notificationMessageIdFor($this->supportMessage->id),
            text: [
                'X-Halal-Kiwi-Support-Reference' => $this->ticket->reference,
                'X-Halal-Kiwi-Support-Message-ID' => (string) $this->supportMessage->id,
                'X-Halal-Kiwi-Support-Submission' => (string) $this->supportMessage->client_submission_uuid,
                'X-Halal-Kiwi-Support-Notification' => 'internal',
            ],
        );
    }

    public static function notificationMessageIdFor(int $supportMessageId): string
    {
        return "support-notification-{$supportMessageId}@halalkiwi.com";
    }
}
