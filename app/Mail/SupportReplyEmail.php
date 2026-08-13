<?php

namespace App\Mail;

use App\Models\SupportMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class SupportReplyEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportMessage $supportMessage,
        public string $transportMessageId,
    ) {}

    public function build(): self
    {
        $mailbox = config('support.mailbox_address');

        return $this->subject($this->supportMessage->subject)
            ->from($mailbox, config('support.mailbox_name'))
            ->replyTo($mailbox, config('support.mailbox_name'))
            ->text('emails.support_reply');
    }

    public function headers(): Headers
    {
        $messageId = trim($this->transportMessageId, '<> ');

        return new Headers(
            messageId: $messageId,
            references: $this->supportMessage->references_header ?? [],
            text: array_filter([
                'In-Reply-To' => $this->supportMessage->in_reply_to,
                'X-Halal-Kiwi-Support-Reference' => $this->supportMessage->ticket->reference,
            ]),
        );
    }
}
