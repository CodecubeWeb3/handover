<?php

namespace App\Http\Controllers;

use App\Domain\Messaging\Services\MessageService;
use App\Enums\UserRole;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageFlagResolveController extends Controller
{
    public function __construct(private readonly MessageService $service)
    {
    }

    public function __invoke(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        abort_unless($user && in_array($user->role, [UserRole::Admin, UserRole::Moderator], true), 403);

        $this->service->resolveFlags($message);

        return response()->json(['status' => 'resolved']);
    }
}