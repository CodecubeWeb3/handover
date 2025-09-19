<?php

namespace Database\Factories;

use App\Models\ParentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParentProfileFactory extends Factory
{
    protected $model = ParentProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->parent(),
        ];
    }
}