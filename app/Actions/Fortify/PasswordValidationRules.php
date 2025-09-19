<?php

namespace App\Actions\Fortify;

use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    protected function passwordRules(): array
    {
        return [
            'required',
            'string',
            'confirmed',
            Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised(),
        ];
    }
}