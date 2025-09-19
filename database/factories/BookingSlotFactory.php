<?php

namespace Database\Factories;

use App\Models\BookingSlot;
use App\Models\Request;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingSlotFactory extends Factory
{
    protected $model = BookingSlot::class;

    public function definition(): array
    {
        return [
            'request_id' => Request::factory(),
            'slot_ts' => now()->addDays(3)->setSeconds(0),
            'status' => 'Open',
        ];
    }
}