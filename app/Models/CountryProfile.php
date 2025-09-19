<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CountryProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'country',
        'legal_age_min',
        'kyc_level',
        'vat_on_platform',
        'retention_days',
        'grace_a_min',
        'grace_b_min',
        'wait_cap_a_min',
        'wait_cap_b_min',
        'geofence_m',
        'buffer_min',
        'slot_minutes',
        'late_fee_base',
        'late_fee_per_min',
        'travel_stipend',
        'min_capture_pct_no_show',
        'platform_pct',
    ];

    protected $casts = [
        'vat_on_platform' => 'boolean',
    ];
}