<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'enable_waitlist', 'value' => true],
            ['key' => 'enable_shared_pay', 'value' => true],
            ['key' => 'enable_photo_proof', 'value' => false],
            ['key' => 'require_webauthn_agent', 'value' => true],
            ['key' => 'enable_travel_stipend', 'value' => true],
            ['key' => 'buffer_minutes', 'value' => 2],
            ['key' => 'geofence_radius_m', 'value' => 100],
            ['key' => 'slot_minutes', 'value' => 10],
            ['key' => 'late_fee_base', 'value' => 300],
            ['key' => 'late_fee_per_min', 'value' => 50],
            ['key' => 'platform_pct', 'value' => 10],
            ['key' => 'min_capture_pct_if_no_show', 'value' => 50],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                [
                    'scope_type' => 'global',
                    'scope_id' => null,
                    'key' => $setting['key'],
                ],
                [
                    'value' => json_encode($setting['value']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}