<?php

namespace App\Mail;

use App\Support\BrandOutreachMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class BrandOutreachEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $brandName,
        public array $products,
        public string $reference,
        public string $kind = 'initial',
        public int $followUpNumber = 0,
        public ?string $subjectOverride = null,
        public ?string $body = null,
        public ?string $inReplyTo = null,
        public array $references = [],
    ) {}

    public function build()
    {
        $prefix = $this->kind === 'follow_up' ? 'Follow-up: ' : '';
        $subject = $this->subjectOverride ?: "{$prefix}Halal Suitability Inquiry [{$this->reference}] - {$this->brandName}";
        $view = BrandOutreachMessage::usesCustomBody($this->kind, $this->body)
            ? 'brand_clarification_email'
            : 'brand_outreach_email';

        return $this->subject($subject)
            ->from(config('outreach.from_address'), config('outreach.from_name'))
            ->replyTo(config('outreach.reply_to'), config('outreach.from_name'))
            ->view($view);
    }

    public function headers(): Headers
    {
        $text = $this->inReplyTo ? ['In-Reply-To' => $this->inReplyTo] : [];

        return new Headers(references: $this->references, text: $text);
    }
}
