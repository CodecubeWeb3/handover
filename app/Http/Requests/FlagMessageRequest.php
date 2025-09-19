<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FlagMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $message = $this->route('message');

        if (! $user || ! $message instanceof \App\Models\Message) {
            return false;
        }

        $booking = $message->thread?->booking;

        if (! $booking) {
            return false;
        }

        return in_array($user->id, [
            $booking->slot?->request?->parent_id,
            $booking->operative_id,
        ], true);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:191'],
        ];
    }
}