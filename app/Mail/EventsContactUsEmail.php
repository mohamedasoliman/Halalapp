<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EventsContactUsEmail extends Mailable
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
        $subject = Str::limit(preg_replace('/[\r\n]+/', ' ', (string) $this->request->subject), 160, '');
        $replyName = Str::limit(preg_replace('/[\r\n]+/', ' ', (string) $this->request->eventName), 100, '');

        $message = $this->subject('Event Submission - '.$subject)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->replyTo($this->request->email, $replyName)
            ->view('events_contact_email');

        if ($this->attachmentPath) {
            $attachmentName = pathinfo($this->attachmentPath, PATHINFO_BASENAME);

            $filePath = storage_path("app/private/$this->attachmentPath");
            if (! file_exists($filePath)) {
                $filePath = storage_path("app/$this->attachmentPath");
            }

            if (file_exists($filePath)) {
                $message->attach($filePath, [
                    'as' => $attachmentName,
                ]);
            } else {
                Log::error("Attachment not found: $this->attachmentPath");
            }
        }

        return $message;
    }
}
