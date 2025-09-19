<?php

namespace App\Jobs;

use App\Domain\Bookings\Services\PassTokenManager;
use App\Models\HandoverToken;
use App\Notifications\PassTokenRotatedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class RotateStalePassTokensJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public function handle(PassTokenManager $passTokenManager): void
    {
        $threshold = now()->subSeconds((int) config('passes.rotation_seconds', 900));

        HandoverToken::query()
            ->with(['booking.slot.request.parent', 'booking.operative'])
            ->whereNotNull('rotated_at')
            ->where('rotated_at', '<=', $threshold)
            ->chunkById(50, function ($tokens) use ($passTokenManager) {
                foreach ($tokens as $token) {
                    $result = $passTokenManager->rotate($token);
                    $booking = $token->booking;
                    $payload = $result['payload'];

                    $notifiables = collect([
                        $booking?->slot?->request?->parent,
                        $booking?->operative,
                    ])->filter();

                    if ($notifiables->isEmpty()) {
                        continue;
                    }

                    Notification::send($notifiables, new PassTokenRotatedNotification($booking, $payload));
                }
            });
    }
}