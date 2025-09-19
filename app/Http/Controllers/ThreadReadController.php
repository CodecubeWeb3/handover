<?php

namespace App\Http\Controllers;

use App\Domain\Messaging\Services\MessageService;
use App\Http\Requests\ThreadReadRequest;
use App\Models\MessageThread;
use Illuminate\Http\JsonResponse;

class ThreadReadController extends Controller
{
    public function __invoke(ThreadReadRequest $request, MessageThread $thread, MessageService $service): JsonResponse
    {
        $service->emitRead($thread, $request->user(), $request->validated()['message_id'] ?? null);

        return response()->json(['status' => 'ok']);
    }
}