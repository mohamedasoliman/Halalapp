<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BrandOutreachEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $brandName;
    public array $products;

    public function __construct(string $brandName, array $products)
    {
        $this->brandName = $brandName;
        $this->products = $products;
    }

    public function build()
    {
        return $this->subject("Halal Suitability Inquiry - {$this->brandName}")
            ->from('products@halalkiwi.com', 'Halal Kiwi Products')
            ->view('brand_outreach_email');
    }
}
