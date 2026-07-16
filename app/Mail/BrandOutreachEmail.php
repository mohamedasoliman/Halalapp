<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
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
    ) {}

    public function build()
    {
        $prefix = $this->kind === 'follow_up' ? 'Follow-up: ' : '';

        return $this->subject("{$prefix}Halal Suitability Inquiry [{$this->reference}] - {$this->brandName}")
            ->from(config('outreach.from_address'), config('outreach.from_name'))
            ->replyTo(config('outreach.reply_to'), config('outreach.from_name'))
            ->view('brand_outreach_email');
    }
}
