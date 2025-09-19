<?php

namespace App\Domain\Bookings\Services;

use App\Domain\Bookings\Enums\BookingEventType;
use App\Models\Booking;
use App\Models\HandoverToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class BookingGuardService
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{time_ok: bool, geo_ok: bool, token_ok: bool, device_ok: bool}
     */
    public function evaluateGuards(Booking $booking, BookingEventType $event, array $context = []): array
    {
        return [
            'time_ok' => $this->timeGuard($booking, $event),
            'geo_ok' => $this->geoGuard($booking, $event, $context),
            'token_ok' => $this->tokenGuard($booking, $event, $context),
            'device_ok' => $this->deviceGuard($context),
        ];
    }

    private function timeGuard(Booking $booking, BookingEventType $event): bool
    {
        $slotAt = CarbonImmutable::parse($booking->slot_ts);
        $profile = $booking->countryProfile();

        return match ($event) {
            BookingEventType::OpenAWindow => now()->between(
                $slotAt->subMinutes($profile->grace_a_min),
                $slotAt->addMinutes($profile->wait_cap_a_min)
            ),
            BookingEventType::ScanAOk => now()->lessThanOrEqualTo($slotAt->addMinutes($profile->wait_cap_a_min)),
            BookingEventType::BufferElapsed, BookingEventType::OpenBWindow => now()->greaterThanOrEqualTo($slotAt),
            BookingEventType::ScanBOk => now()->lessThanOrEqualTo($slotAt->addMinutes($profile->wait_cap_b_min)),
            BookingEventType::TimerAGraceExpired, BookingEventType::TimerBGraceExpired,
            BookingEventType::CancelByParent, BookingEventType::CancelByOperative,
            BookingEventType::CancelByAdmin, BookingEventType::TimeoutAll,
            BookingEventType::GeoFreeze, BookingEventType::Unfreeze,
            BookingEventType::Complete => true,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function geoGuard(Booking $booking, BookingEventType $event, array $context): bool
    {
        if (! in_array($event, [BookingEventType::ScanAOk, BookingEventType::ScanBOk, BookingEventType::GeoFreeze, BookingEventType::Unfreeze], true)) {
            return true;
        }

        $lat = Arr::get($context, 'location.lat');
        $lng = Arr::get($context, 'location.lng');

        if ($lat === null || $lng === null) {
            Log::warning('Geo guard missing coordinates.', [
                'booking_id' => $booking->id,
                'event' => $event->value,
            ]);

            return false;
        }

        $meetPoint = $booking->meetingCoordinates();

        if ($meetPoint === null) {
            Log::notice('Geo guard bypassed due to missing meet point.', [
                'booking_id' => $booking->id,
                'event' => $event->value,
            ]);

            return true;
        }

        $distance = $this->haversineDistance(
            (float) $lat,
            (float) $lng,
            $meetPoint['lat'],
            $meetPoint['lng']
        );

        $profile = $booking->countryProfile();

        if ($distance <= $profile->geofence_m) {
            return true;
        }

        Log::warning('Geofence guard failed.', [
            'booking_id' => $booking->id,
            'event' => $event->value,
            'distance_m' => $distance,
            'threshold_m' => $profile->geofence_m,
        ]);

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function tokenGuard(Booking $booking, BookingEventType $event, array $context): bool
    {
        if (! in_array($event, [BookingEventType::ScanAOk, BookingEventType::ScanBOk], true)) {
            return true;
        }

        $code = Arr::get($context, 'token.code');

        if (! is_string($code)) {
            return false;
        }

        $leg = $event === BookingEventType::ScanAOk ? HandoverToken::LEG_A : HandoverToken::LEG_B;
        $token = $booking->handoverTokens()->firstWhere('leg', $leg);

        if ($token === null) {
            Log::warning('Token guard failed: missing handover token.', [
                'booking_id' => $booking->id,
                'event' => $event->value,
                'leg' => $leg,
            ]);

            return false;
        }

        return app(PassTokenManager::class)->verify($token, $code);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function deviceGuard(array $context): bool
    {
        return (bool) Arr::get($context, 'device.attested', false);
    }

    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}