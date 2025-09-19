<?php

namespace Tests\Unit\Domain\Bookings;

use App\Domain\Bookings\Enums\BookingEventType;
use App\Domain\Bookings\Services\BookingGuardService;
use App\Domain\Bookings\Services\PassTokenManager;
use App\Models\Booking;
use App\Models\CountryProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingGuardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(now()->setSeconds(0));
    }

    public function test_it_passes_all_guards_with_valid_context(): void
    {
        $booking = $this->makeBookingReadyForLeg('A');

        $payload = app(PassTokenManager::class)->ensurePayloadFor($booking, 'A');
        $secret = Arr::get($payload, 'payload.secret');
        $code = $this->generateTotpCode($secret);

        $context = [
            'location' => $booking->meetingCoordinates(),
            'device' => ['attested' => true],
            'token' => ['code' => $code],
        ];

        $results = app(BookingGuardService::class)->evaluateGuards(
            $booking->fresh(),
            BookingEventType::ScanAOk,
            $context
        );

        $this->assertSame([
            'time_ok' => true,
            'geo_ok' => true,
            'token_ok' => true,
            'device_ok' => true,
        ], $results);
    }

    public function test_geo_guard_fails_when_outside_radius(): void
    {
        $booking = $this->makeBookingReadyForLeg('A');

        $payload = app(PassTokenManager::class)->ensurePayloadFor($booking, 'A');
        $secret = Arr::get($payload, 'payload.secret');
        $code = $this->generateTotpCode($secret);

        $context = [
            'location' => [
                'lat' => $booking->meetingCoordinates()['lat'] + 2,
                'lng' => $booking->meetingCoordinates()['lng'] + 2,
            ],
            'device' => ['attested' => true],
            'token' => ['code' => $code],
        ];

        $results = app(BookingGuardService::class)->evaluateGuards(
            $booking->fresh(),
            BookingEventType::ScanAOk,
            $context
        );

        $this->assertFalse($results['geo_ok']);
        $this->assertTrue($results['time_ok']);
        $this->assertTrue($results['token_ok']);
    }

    public function test_token_guard_fails_with_incorrect_code(): void
    {
        $booking = $this->makeBookingReadyForLeg('A');

        $context = [
            'location' => $booking->meetingCoordinates(),
            'device' => ['attested' => true],
            'token' => ['code' => '000000'],
        ];

        $results = app(BookingGuardService::class)->evaluateGuards(
            $booking->fresh(),
            BookingEventType::ScanAOk,
            $context
        );

        $this->assertFalse($results['token_ok']);
        $this->assertTrue($results['geo_ok']);
    }

    private function makeBookingReadyForLeg(string $leg): Booking
    {
        $status = match (strtoupper($leg)) {
            'A' => 'A_WINDOW_OPEN',
            'B' => 'B_WINDOW_OPEN',
            default => 'A_WINDOW_OPEN',
        };

        $booking = Booking::factory()->create([
            'status' => $status,
            'slot_ts' => now()->addMinutes(5),
        ]);

        CountryProfile::factory()->create([
            'country' => $booking->slot->request->parent->country,
            'geofence_m' => 200,
        ]);

        return $booking->fresh();
    }

    private function generateTotpCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
        $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $bits = '';

        foreach (str_split($secret) as $char) {
            $bits .= str_pad(decbin($alphabet[$char]), 5, '0', STR_PAD_LEFT);
        }

        $binarySecret = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binarySecret .= chr(bindec($chunk));
            }
        }

        $counter = intdiv($timestamp, 30);
        $high = ($counter >> 32) & 0xFFFFFFFF;
        $low = $counter & 0xFFFFFFFF;
        $binaryCounter = pack('NN', $high, $low);
        $hash = hash_hmac('sha1', $binaryCounter, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $slice = substr($hash, $offset, 4);
        $value = unpack('N', $slice)[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }
}