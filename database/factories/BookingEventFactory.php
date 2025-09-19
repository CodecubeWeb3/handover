<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingEventFactory extends Factory
{
    protected $model = BookingEvent::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'event_type' => 'STATE_CHANGE',
            'payload_json' => ['status' => 'Scheduled'],
            'chain_index' => 0,
            'prev_hash' => null,
            'this_hash' => random_bytes(32),
            'created_at' => now(),
        ];
    }
}