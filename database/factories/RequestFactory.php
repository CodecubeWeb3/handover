<?php

namespace Database\Factories;

use App\Models\Request;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RequestFactory extends Factory
{
    protected $model = Request::class;

    public function definition(): array
    {
        return [
            'parent_id' => User::factory()->parent(),
            'meet_point' => [
                'lat' => fake()->latitude(),
                'lng' => fake()->longitude(),
            ],
            'notes' => fake()->sentence(),
            'status' => 'open',
        ];
    }
}