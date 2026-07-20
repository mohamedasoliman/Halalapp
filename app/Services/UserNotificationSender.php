<?php

namespace App\Services;

use App\Mail\UserNotificationEmail;
use App\Models\RequestNotificationDelivery;
use Illuminate\Contracts\Mail\Mailer;

class UserNotificationSender
{
    public function __construct(private readonly Mailer $mailer) {}

    public function send(RequestNotificationDelivery $delivery): void
    {
        $this->mailer->to($delivery->recipient_email)->send(new UserNotificationEmail(
            $delivery->notification_type,
            $delivery->product_name,
            $delivery->barcode,
            $delivery->halal_status === null ? null : (string) $delivery->halal_status,
        ));
    }
}
