<?php

namespace Tests\Feature\Scans;

use App\Domain\Bookings\Services\PassTokenManager;
use App\Models\Booking;
use App\Models\CountryProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingScanApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(now()->setSeconds(0));
    }

    public function test_parent_can_retrieve_pass_payload(): void
    {
        $booking = $this->makeBookingReadyForLeg('A');
        $parent = $booking->slot->request->parent;

        Sanctum::actingAs($parent, ['*']);

        $response = $this->getJson(route('booking.pass.show', ['booking' => $booking->id, 'leg' => 'A']));

        $response->assertOk()->assertJsonStructure([
            'data' => [
                'token_id',
                'leg',
                'rotated_at',
                'expires_at',
                'otpauth_uri',
                'qr_payload',
                'secret',
                'offline_pin',
                'deeplink',
            ],
        ]);
    }

    public function test_operative_can_scan_booking_successfully(): void
    {
        $booking = $this->makeBookingReadyForLeg('A');
        $operative = $booking->operative;

        Sanctum::actingAs($operative, ['*']);

        $payload = app(PassTokenManager::class)->ensurePayloadFor($booking, 'A');
        $secret = Arr::get($payload, 'payload.secret');
        $code = $this->generateTotpCode($secret);
        $eventUuid = (string) Str::uuid();

        $response = $this->postJson(route('booking.scan', ['booking' => $booking->id, 'leg' => 'A']), [
            'event_uuid' => $eventUuid,
            'location' => $booking->meetingCoordinates(),
            'device' => ['attested' => true],
            'token' => ['code' => $code],
        ]);

        $response->assertOk()->assertJsonPath('data.event_uuid', $eventUuid);

        $this->assertSame('A_SCANNED', $booking->fresh()->status);
        $this->assertDatabaseHas('booking_events', [
            'booking_id' => $booking->id,
            'event_uuid' => $eventUuid,
        ]);
    }

    public function test_replay_returns_existing_event_without_side_effects(): void
    {
        $booking = $this->makeBookingReadyForLeg('A');
        $operative = $booking->operative;

        Sanctum::actingAs($operative, ['*']);

        $payload = app(PassTokenManager::class)->ensurePayloadFor($booking, 'A');
        $secret = Arr::get($payload, 'payload.secret');
        $code = $this->generateTotpCode($secret);
        $eventUuid = (string) Str::uuid();

        $body = [
            'event_uuid' => $eventUuid,
            'location' => $booking->meetingCoordinates(),
            'device' => ['attested' => true],
            'token' => ['code' => $code],
        ];

        $first = $this->postJson(route('booking.scan', ['booking' => $booking->id, 'leg' => 'A']), $body);
        $first->assertOk();

        $second = $this->postJson(route('booking.scan', ['booking' => $booking->id, 'leg' => 'A']), $body);
        $second->assertOk()->assertJsonPath('data.idempotent', true);

        $this->assertCount(1, $booking->fresh()->events()->where('event_uuid', $eventUuid)->get());
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