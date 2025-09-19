<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

class ShowPassPayloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $booking = $this->route('booking');

        if (! $user instanceof \App\Models\User || ! $booking instanceof Booking) {
            return false;
        }

        if (in_array($user->role, [UserRole::Admin, UserRole::Moderator], true)) {
            return true;
        }

        if ($user->role === UserRole::Operative && (int) $booking->operative_id === (int) $user->id) {
            return true;
        }

        if ($user->role === UserRole::Parent) {
            $requestingParentId = $booking->slot?->request?->parent_id;

            if ($requestingParentId && (int) $requestingParentId === (int) $user->id) {
                return true;
            }
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'refresh' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'refresh' => filter_var($this->input('refresh', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
        ]);
    }
}