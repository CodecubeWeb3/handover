<?php

namespace App\Http\Controllers;

use App\Domain\Messaging\Services\MessageService;
use App\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageFlagsIndexController extends Controller
{
    public function __construct(private readonly MessageService $service)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user && in_array($user->role, [UserRole::Admin, UserRole::Moderator], true), 403);

        return response()->json($this->service->listFlags($request));
    }
}