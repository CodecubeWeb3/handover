<?php

namespace App\Domain\Bookings\Services;

use App\Domain\Bookings\Data\BookingTransition;
use App\Domain\Bookings\Enums\BookingEventType;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Exceptions\BookingStateTransitionException;
use App\Domain\Payments\Services\SharedPaymentManager;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingStateMachine
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function apply(Booking $booking, BookingEventType $event, array $context = []): Booking
    {
        $paymentManager = app(SharedPaymentManager::class);

        return DB::transaction(function () use ($booking, $event, $context, $paymentManager) {
            $booking->refresh();

            $currentStatus = BookingStatus::from($booking->status);

            if ($currentStatus->isTerminal()) {
                throw BookingStateTransitionException::alreadyTerminal($currentStatus);
            }

            [$nextStatus, $guards] = $this->determineTransition($currentStatus, $event);

            $guardService = app(BookingGuardService::class);
            $guardResults = $guardService->evaluateGuards($booking, $event, $context);

            $context = array_merge($context, $guardResults, ['guard_results' => $guardResults]);

            $this->assertGuards($guards, $guardResults, $booking, $event);

            $transition = BookingTransition::fromContext($currentStatus, $nextStatus, $event, $context);

            $booking->forceFill(['status' => $nextStatus->value])->save();

            app(BookingEventRecorder::class)->record($booking, $transition, $context);

            $this->handleSideEffects($booking, $event, $paymentManager);

            return $booking;
        });
    }

    /**
     * @return array{0: BookingStatus, 1: array<string>}
     */
    private function determineTransition(BookingStatus $status, BookingEventType $event): array
    {
        $map = [
            BookingStatus::Scheduled->value => [
                BookingEventType::OpenAWindow->value => [BookingStatus::AWindowOpen, ['time']],
                BookingEventType::CancelByParent->value => [BookingStatus::Canceled, []],
                BookingEventType::CancelByOperative->value => [BookingStatus::Canceled, []],
                BookingEventType::CancelByAdmin->value => [BookingStatus::Canceled, []],
                BookingEventType::TimeoutAll->value => [BookingStatus::Expired, []],
            ],
            BookingStatus::AWindowOpen->value => [
                BookingEventType::ScanAOk->value => [BookingStatus::AScanned, ['time', 'geo', 'token', 'device']],
                BookingEventType::TimerAGraceExpired->value => [BookingStatus::NoShowA, []],
                BookingEventType::CancelByParent->value => [BookingStatus::Canceled, []],
                BookingEventType::CancelByOperative->value => [BookingStatus::Canceled, []],
                BookingEventType::CancelByAdmin->value => [BookingStatus::Canceled, []],
            ],
            BookingStatus::AScanned->value => [
                BookingEventType::BufferElapsed->value => [BookingStatus::Buffer, []],
                BookingEventType::GeoFreeze->value => [BookingStatus::Frozen, ['geo']],
                BookingEventType::CancelByAdmin->value => [BookingStatus::Canceled, []],
            ],
            BookingStatus::Buffer->value => [
                BookingEventType::OpenBWindow->value => [BookingStatus::BWindowOpen, ['time']],
                BookingEventType::CancelByAdmin->value => [BookingStatus::Canceled, []],
            ],
            BookingStatus::BWindowOpen->value => [
                BookingEventType::ScanBOk->value => [BookingStatus::BScanned, ['time', 'geo', 'token', 'device']],
                BookingEventType::TimerBGraceExpired->value => [BookingStatus::NoShowB, []],
                BookingEventType::CancelByParent->value => [BookingStatus::Canceled, []],
                BookingEventType::CancelByOperative->value => [BookingStatus::Canceled, []],
                BookingEventType::CancelByAdmin->value => [BookingStatus::Canceled, []],
            ],
            BookingStatus::BScanned->value => [
                BookingEventType::Complete->value => [BookingStatus::Completed, []],
            ],
            BookingStatus::Frozen->value => [
                BookingEventType::Unfreeze->value => [BookingStatus::AScanned, ['geo']],
                BookingEventType::TimerBGraceExpired->value => [BookingStatus::NoShowB, []],
                BookingEventType::CancelByAdmin->value => [BookingStatus::Canceled, []],
            ],
        ];

        $statusMap = $map[$status->value] ?? [];

        if (! isset($statusMap[$event->value])) {
            throw BookingStateTransitionException::illegalTransition($status, $event);
        }

        return $statusMap[$event->value];
    }

    /**
     * @param  list<string>  $guards
     * @param  array{time_ok: bool, geo_ok: bool, token_ok: bool, device_ok: bool}  $guardResults
     */
    private function assertGuards(array $guards, array $guardResults, Booking $booking, BookingEventType $event): void
    {
        $map = [
            'time' => 'time_ok',
            'geo' => 'geo_ok',
            'token' => 'token_ok',
            'device' => 'device_ok',
        ];

        foreach ($guards as $guard) {
            $key = $map[$guard] ?? null;

            if ($key === null) {
                continue;
            }

            $flag = (bool) ($guardResults[$key] ?? false);

            if (! $flag) {
                Log::warning('Booking guard failed.', [
                    'booking_id' => $booking->id,
                    'event' => $event->value,
                    'guard' => $guard,
                    'guard_results' => $guardResults,
                ]);

                throw BookingStateTransitionException::guardFailed("Guard {$guard} failed for booking transition.");
            }
        }
    }

    private function handleSideEffects(Booking $booking, BookingEventType $event, SharedPaymentManager $paymentManager): void
    {
        match ($event) {
            BookingEventType::OpenAWindow => $paymentManager->ensurePreauthorized($booking),
            BookingEventType::ScanBOk, BookingEventType::Complete => $paymentManager->captureForCompletion($booking),
            default => null,
        };
    }
}
