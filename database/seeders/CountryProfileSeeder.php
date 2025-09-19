<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CountryProfileSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('country_profiles')->updateOrInsert(
            ['country' => 'GB'],
            [
                'legal_age_min' => 18,
                'kyc_level' => 'enhanced',
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
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}