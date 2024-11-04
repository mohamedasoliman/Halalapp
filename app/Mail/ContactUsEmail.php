<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ContactUsEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $name;
    public $email;
    public $message;
    public $request;
    public $attachmentPath;

    /**
     * Create a new message instance.
     */
    public function __construct($request, $attachmentPath)
{
    $this->request = $request;
    $this->attachmentPath = $attachmentPath;
}


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $message = $this->subject('Contact Us - ' . $this->request->subject)
            ->from($this->request->email, $this->request->name)
            ->view('contact_email');

        if ($this->attachmentPath) {
            $attachmentName = pathinfo($this->attachmentPath, PATHINFO_BASENAME);

            // Make sure the file exists before attaching
            if (file_exists(storage_path("app/$this->attachmentPath"))) {
                $message->attach(storage_path("app/$this->attachmentPath"), [
                    'as' => $attachmentName,
                ]);
            } else {
                // Log or handle the case where the file is not found
                \Illuminate\Support\Facades\Log::error("Attachment not found: $this->attachmentPath");
            }
        }

        return $message;
    }




}
