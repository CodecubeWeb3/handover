<?php

namespace App\Http\Controllers;

use App\Domain\Messaging\Services\MessageService;
use App\Http\Requests\ThreadTypingRequest;
use App\Models\MessageThread;
use Illuminate\Http\JsonResponse;

class ThreadTypingController extends Controller
{
    public function __invoke(ThreadTypingRequest $request, MessageThread $thread, MessageService $service): JsonResponse
    {
        $service->emitTyping($thread, $request->user(), (string) $request->validated()['state']);

        return response()->json(['status' => 'ok']);
    }
}