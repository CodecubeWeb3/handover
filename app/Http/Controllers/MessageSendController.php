<?php

namespace App\Http\Controllers;

use App\Domain\Messaging\Services\MessageService;
use App\Http\Requests\SendMessageRequest;
use App\Models\MessageThread;
use Illuminate\Http\JsonResponse;

class MessageSendController extends Controller
{
    public function __invoke(SendMessageRequest $request, MessageThread $thread, MessageService $service): JsonResponse
    {
        $booking = $thread->booking()->with(['slot.request', 'operative'])->firstOrFail();

        $message = $service->send(
            $service->ensureThread($booking),
            $request->user(),
            (string) $request->validated()['message'],
            $request->validated()['attachments'] ?? []
        );

        return response()->json([
            'data' => [
                'id' => $message->id,
                'thread_id' => $message->thread_id,
                'body' => $message->body,
                'sender' => [
                    'id' => $message->sender_id,
                    'name' => $message->sender?->name,
                    'role' => $message->sender?->role->value ?? null,
                ],
                'attachments' => $message->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'disk' => $attachment->storage_disk,
                    'path' => $attachment->storage_path,
                    'mime' => $attachment->mime,
                    'bytes' => $attachment->bytes,
                ])->values(),
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}