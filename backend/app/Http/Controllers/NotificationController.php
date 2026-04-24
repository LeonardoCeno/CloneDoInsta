<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $notifications = $this->notifications->list($request->user(), $this->perPage($request));

        return response()->json(NotificationResource::collection($notifications)->response()->getData(true));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $this->notifications->markAllRead($request->user());

        return response()->json(['message' => 'Notificações marcadas como lidas.']);
    }
}
