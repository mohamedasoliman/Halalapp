<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Reset your Halal Kiwi admin password')
            ->line('A password reset was requested for your Halal Kiwi administrator account.')
            ->action('Reset password', $url)
            ->line(
                'This link expires in '.config('auth.passwords.admins.expire').' minutes. '.
                'If you did not request this, no action is required.'
            );
    }
}
