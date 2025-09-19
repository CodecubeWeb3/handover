<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    public function update(Authenticatable $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();
    }
}