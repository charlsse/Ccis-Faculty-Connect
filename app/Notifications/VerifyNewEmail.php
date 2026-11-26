<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyNewEmail extends Notification
{
    public function __construct(
        private readonly int $userId,
        #[\SensitiveParameter] private readonly string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your new FacultyConnect email address')
            ->line('A change to this email address was requested from your FacultyConnect profile.')
            ->action('Verify new email address', route('profile.email.verify', ['user' => $this->userId, 'token' => $this->token]))
            ->line('This single-use link expires in '.config('account-security.email_change_expire_minutes').' minutes. Ignore this message if you did not request the change.');
    }
}
