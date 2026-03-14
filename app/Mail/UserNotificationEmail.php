<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserNotificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $notificationType;
    public string $productName;
    public string $barcode;
    public ?string $halalStatus;

    public function __construct(string $notificationType, string $productName, string $barcode, ?string $halalStatus = null)
    {
        $this->notificationType = $notificationType;
        $this->productName = $productName;
        $this->barcode = $barcode;
        $this->halalStatus = $halalStatus;
    }

    public function build()
    {
        $subject = match ($this->notificationType) {
            'contacted' => "Update on your request: {$this->productName}",
            'resolved' => "Confirmed: {$this->productName} is " . ($this->halalStatus === '0' ? 'Halal' : 'Not Halal'),
            default => "Update: {$this->productName}",
        };

        return $this->subject($subject)
            ->from('halalkiwi@halalkiwi.com', 'Halal Kiwi')
            ->view('user_notification_email');
    }
}
