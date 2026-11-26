<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WaterPaymentUpdated extends Notification
{
    public function __construct(public int $collectionId, public string $message, public ?array $context = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $allowed = ['water.show', 'water.checkout', 'water.submission', 'water.report', 'water.demo.show', 'water.online.show'];
        $name = in_array($this->context['route'] ?? '', $allowed, true) ? $this->context['route'] : 'water.show';
        $parameters = $this->context['parameters'] ?? [$this->collectionId];
        return (new MailMessage)->subject($this->context['title'] ?? 'FacultyConnect collection update')->line($this->message)
            ->action('View payment update', route($name, $parameters))
            ->line('Sign in to view details. QR payments require Treasurer verification.');
    }
}
