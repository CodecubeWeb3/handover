<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\MessageFlag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFlagFactory extends Factory
{
    protected $model = MessageFlag::class;

    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'reporter_id' => User::factory(),
            'reason' => fake()->sentence(3),
            'created_at' => now(),
        ];
    }
}