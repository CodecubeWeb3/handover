<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'home' => '/verify-email',

    'prefix' => '',

    'domain' => null,

    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],

    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication(['confirmPassword' => true]),
    ],
];