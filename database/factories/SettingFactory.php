<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'scope_type' => 'global',
            'scope_id' => null,
            'key' => fake()->unique()->slug(),
            'value' => ['enabled' => true],
        ];
    }
}