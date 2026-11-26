<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountInvitation extends Notification
{
    public function __construct(
        #[\SensitiveParameter] private readonly string $temporaryPassword,
        private readonly string $expiresAt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your FacultyConnect invitation')
            ->view(['mail.invitation', 'mail.invitation-text'], [
                'name' => $notifiable->full_name,
                'username' => $notifiable->email,
                'role' => $notifiable->role_label,
                'temporaryPassword' => $this->temporaryPassword,
                'expiresAt' => $this->expiresAt,
                'loginUrl' => rtrim(config('app.url'), '/').'/login',
                'support' => config('account-security.support_email'),
            ]);
    }
}
