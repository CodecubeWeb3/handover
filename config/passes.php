<?php

return [
    'issuer' => env('PASS_TOKEN_ISSUER', 'Safe Handover'),
    'rotation_seconds' => (int) env('PASS_TOKEN_ROTATION_SECONDS', 900),
    'totp_window' => (int) env('PASS_TOKEN_TOTP_WINDOW', 1),
    'deeplink_scheme' => env('PASS_TOKEN_DEEPLINK_SCHEME', 'handover://booking'),
];