<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

class ScanBookingRequest extends FormRequest
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

        return $user->role === UserRole::Operative && (int) $booking->operative_id === (int) $user->id;
    }

    public function rules(): array
    {
        return [
            'event_uuid' => ['required', 'uuid'],
            'token.code' => ['required', 'string', 'regex:/^[0-9]{4,8}$/'],
            'location.lat' => ['required', 'numeric', 'between:-90,90'],
            'location.lng' => ['required', 'numeric', 'between:-180,180'],
            'device.attested' => ['required', 'boolean'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}