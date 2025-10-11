<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class KiwiSaverContactUsEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $request;

    /**
     * Create a new message instance.
     */
    public function __construct($request)
    {
        $this->request = $request;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('KiwiSaver Enquiry')
            ->from($this->request->email, trim(($this->request->first_name ?? '') . ' ' . ($this->request->last_name ?? '')))
            ->view('kiwisaver_contact_email');
    }
}


