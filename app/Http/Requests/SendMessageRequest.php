<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\MessageThread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendMessageRequest extends FormRequest
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

        if (in_array($user->role, [UserRole::Admin, UserRole::Moderator], true)) {
            return true;
        }

        if ($user->role === UserRole::Parent && (int) $booking->slot?->request?->parent_id === (int) $user->id) {
            return true;
        }

        if ($user->role === UserRole::Operative && (int) $booking->operative_id === (int) $user->id) {
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:1000'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*.storage_disk' => ['sometimes', 'string', 'max:100'],
            'attachments.*.storage_path' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.mime' => ['required_with:attachments', 'string', 'max:100'],
            'attachments.*.bytes' => ['required_with:attachments', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('message')) {
            $this->merge([
                'message' => trim((string) $this->input('message')),
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $message = $this->input('message');
            $attachments = $this->input('attachments', []);

            if (($message === null || $message === '') && empty($attachments)) {
                $validator->errors()->add('message', 'Message body or attachments required.');
            }
        });
    }
}