<?php

namespace Database\Factories;

use App\Models\CountryProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class CountryProfileFactory extends Factory
{
    protected $model = CountryProfile::class;

    public function definition(): array
    {
        return [
            'country' => strtoupper(fake()->countryCode()),
            'legal_age_min' => 18,
            'kyc_level' => 'basic',
            'vat_on_platform' => true,
            'retention_days' => 365,
            'grace_a_min' => 5,
            'grace_b_min' => 5,
            'wait_cap_a_min' => 15,
            'wait_cap_b_min' => 15,
            'geofence_m' => 100,
            'buffer_min' => 2,
            'slot_minutes' => 10,
            'late_fee_base' => 300,
            'late_fee_per_min' => 50,
            'travel_stipend' => 200,
            'min_capture_pct_no_show' => 50,
            'platform_pct' => 10,
        ];
    }
}