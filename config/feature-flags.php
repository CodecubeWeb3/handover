<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature Flag Storage
    |--------------------------------------------------------------------------
    |
    | Feature toggles are hydrated from environment variables to allow
    | zero-downtime reconfiguration. Flags are cached through the
    | application cache store defined here for performance.
    |
    */

    'cache_store' => env('FEATURE_FLAG_CACHE_STORE', env('CACHE_STORE', 'database')),

    /*
    |--------------------------------------------------------------------------
    | Toggle Definitions
    |--------------------------------------------------------------------------
    |
    | Each flag has a sensible production default. Casting occurs in the
    | FeatureFlags service to ensure typed access throughout the codebase.
    |
    */

    'flags' => [
        'enable_waitlist' => env('FLAG_ENABLE_WAITLIST', true),
        'enable_shared_pay' => env('FLAG_ENABLE_SHARED_PAY', true),
        'enable_photo_proof' => env('FLAG_ENABLE_PHOTO_PROOF', true),
        'require_webauthn_agent' => env('FLAG_REQUIRE_WEBAUTHN_AGENT', false),
        'enable_travel_stipend' => env('FLAG_ENABLE_TRAVEL_STIPEND', false),
        'buffer_minutes' => env('FLAG_BUFFER_MINUTES', 20),
        'geofence_radius_m' => env('FLAG_GEOFENCE_RADIUS_M', 150),
        'slot_minutes' => env('FLAG_SLOT_MINUTES', 10),
        'enable_wallet_passes' => env('FLAG_ENABLE_WALLET_PASSES', true),
        'late_fee_base' => env('FLAG_LATE_FEE_BASE', 1500),
        'late_fee_per_min' => env('FLAG_LATE_FEE_PER_MIN', 200),
        'platform_pct' => env('FLAG_PLATFORM_PCT', 15),
        'min_capture_pct_if_no_show' => env('FLAG_MIN_CAPTURE_PCT_IF_NO_SHOW', 50),
    ],

];
