<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PassTokenRotatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Booking $booking, private readonly array $payload)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = sprintf('Pass updated for booking #%d', $this->booking->id);

        return (new MailMessage())
            ->subject($subject)
            ->greeting('Hello '.$notifiable->name)
            ->line('A fresh pass token is ready for your upcoming handover.')
            ->line(sprintf('Leg: %s', $this->payload['leg']))
            ->line(sprintf('Offline PIN: %s', $this->payload['offline_pin']))
            ->line('If you use a TOTP authenticator, add the following URI:')
            ->line($this->payload['otpauth_uri'])
            ->line('Deep link: '.$this->payload['deeplink'])
            ->line('This message is sent automatically—no reply is required.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'leg' => $this->payload['leg'],
            'offline_pin' => $this->payload['offline_pin'],
            'expires_at' => $this->payload['expires_at'],
        ];
    }
}