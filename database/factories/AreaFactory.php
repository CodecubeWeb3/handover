<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AreaFactory extends Factory
{
    protected $model = Area::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->operative(),
            'geom' => [
                [fake()->longitude(), fake()->latitude()],
                [fake()->longitude(), fake()->latitude()],
                [fake()->longitude(), fake()->latitude()],
            ],
        ];
    }
}