<?php

namespace App\Http\Requests;

use App\Models\MessageThread;
use Illuminate\Foundation\Http\FormRequest;

class ThreadReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $thread = $this->route('thread');

        if (! $user || ! $thread instanceof MessageThread) {
            return false;
        }

        $booking = $thread->booking;

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
            'message_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}