<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'slot_id' => BookingSlot::factory(),
            'operative_id' => User::factory()->operative(),
            'slot_ts' => now()->addDays(3)->setSeconds(0),
            'status' => 'Scheduled',
            'meet_qr' => fake()->uuid(),
            'meet_point' => [
                'lat' => fake()->latitude(),
                'lng' => fake()->longitude(),
            ],
            'buffer_minutes' => 2,
            'geofence_radius_m' => 100,
        ];
    }
}