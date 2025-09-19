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

        $filters = [
            'reason' => $request->string('reason')->trim()->value(),
            'reporter' => $request->string('reporter')->trim()->value(),
            'booking_id' => $request->query('booking_id'),
            'thread_id' => $request->query('thread_id') ?? $request->query('thread'),
            'message_id' => $request->query('message_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        foreach (['booking_id', 'thread_id', 'message_id'] as $numeric) {
            if (isset($filters[$numeric]) && $filters[$numeric] !== '') {
                $filters[$numeric] = (int) $filters[$numeric];
            } else {
                $filters[$numeric] = null;
            }
        }

        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(5, min($perPage, 100));
        $page = max(1, (int) $request->query('page', 1));

        return response()->json($this->service->listFlags($filters, $perPage, $page));
    }
}