<?php

namespace App\Notifications;

use App\Models\MessageFlag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MessageFlaggedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly MessageFlag $flag)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->flag->message;
        $thread = $message?->thread;

        return (new MailMessage())
            ->subject('Message flagged for review')
            ->line('A conversation message has been flagged by a participant.')
            ->line('Reason: '.$this->flag->reason)
            ->line('Message preview: '.$message?->body)
            ->action('View booking', url('/moderation/flags?thread='.$thread?->id))
            ->line('Reporter ID: '.$this->flag->reporter_id)
            ->line('Flagged at: '.$this->flag->created_at);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message_id' => $this->flag->message_id,
            'thread_id' => $this->flag->message?->thread_id,
            'reason' => $this->flag->reason,
            'reporter_id' => $this->flag->reporter_id,
            'flagged_at' => optional($this->flag->created_at)->toIso8601String(),
        ];
    }
}