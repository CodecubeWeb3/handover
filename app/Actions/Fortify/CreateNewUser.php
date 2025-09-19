<?php

namespace App\Actions\Fortify;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        $normalized = $this->normalizedInput($input);

        $validator = Validator::make($normalized, [
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:191', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+[0-9]{8,15}$/', 'unique:users,phone'],
            'country' => ['required', 'string', 'size:2'],
            'dob' => ['required', 'date', 'before_or_equal:' . now()->subYears(18)->format('Y-m-d')],
            'role' => ['required', Rule::in(UserRole::assignableValues())],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ]);

        $validator->validate();

        $data = $validator->validated();

        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'country' => $data['country'],
            'dob' => $data['dob'],
            'role' => $data['role'],
            'password' => $data['password'],
        ]);
    }

    private function normalizedInput(array $input): array
    {
        $normalized = Arr::map($input, function ($value) {
            if (is_string($value)) {
                return trim($value);
            }

            return $value;
        });

        if (isset($normalized['phone']) && is_string($normalized['phone'])) {
            $phone = preg_replace('/[^0-9+]/', '', $normalized['phone']) ?? '';
            $phone = preg_replace('/(?!^)\+/', '', $phone) ?? '';
            if ($phone !== '' && ! str_starts_with($phone, '+')) {
                $phone = '+' . ltrim($phone, '+');
            }
            $normalized['phone'] = $phone;
        }

        if (isset($normalized['country']) && is_string($normalized['country'])) {
            $normalized['country'] = Str::upper($normalized['country']);
        }

        if (isset($normalized['email']) && is_string($normalized['email'])) {
            $normalized['email'] = Str::lower($normalized['email']);
        }

        return $normalized;
    }
}