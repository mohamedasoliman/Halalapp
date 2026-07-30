<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

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
        $replyName = Str::limit(
            preg_replace(
                '/[\r\n]+/',
                ' ',
                trim(($this->request->first_name ?? '').' '.($this->request->last_name ?? ''))
            ),
            100,
            ''
        );

        return $this->subject('KiwiSaver Enquiry')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->replyTo($this->request->email, $replyName)
            ->view('kiwisaver_contact_email');
    }
}
