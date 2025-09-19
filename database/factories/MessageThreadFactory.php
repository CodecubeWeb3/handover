<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\MessageThread;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageThreadFactory extends Factory
{
    protected $model = MessageThread::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
        ];
    }
}