<?php

return [
    'currency' => env('PAYMENT_CURRENCY', 'GBP'),
    'slot_price_minor' => (int) env('PAYMENT_SLOT_PRICE_MINOR', 4000),
    'gateway' => env('PAYMENT_GATEWAY', 'simulation'),
];