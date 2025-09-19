<?php

namespace App\Http\Controllers;

use App\Domain\Messaging\Services\MessageService;
use App\Http\Requests\FlagMessageRequest;
use App\Models\Message;
use Illuminate\Http\JsonResponse;

class MessageFlagController extends Controller
{
    public function __invoke(FlagMessageRequest $request, Message $message, MessageService $service): JsonResponse
    {
        $flag = $service->flag($message, $request->user(), (string) $request->validated()['reason']);

        return response()->json([
            'data' => [
                'message_id' => $flag->message_id,
                'reporter_id' => $flag->reporter_id,
                'reason' => $flag->reason,
                'created_at' => $flag->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}