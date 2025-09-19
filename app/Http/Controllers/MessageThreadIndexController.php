<?php

namespace App\Http\Controllers;

use App\Domain\Messaging\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageThreadIndexController extends Controller
{
    public function __construct(private readonly MessageService $service)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user, 401);

        return response()->json([
            'data' => $this->service->listThreadsForUser($user),
        ]);
    }
}