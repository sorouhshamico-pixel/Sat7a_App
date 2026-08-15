<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Support\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The shared notification inbox — every authenticated user type (customer,
 * provider staff, driver, platform staff) reaches only their own via
 * `$request->user()->id`, never a route parameter, the same ownership
 * shape as every other `/me` endpoint in this project (see
 * docs/NOTIFICATIONS.md).
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->notifications()->latest('created_at');

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            data: ['notifications' => NotificationResource::collection($notifications->items())],
            meta: [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $request->user()->notifications()->whereNull('read_at')->count(),
            ],
        );
    }

    public function markRead(Request $request, string $notificationPublicId): JsonResponse
    {
        $notification = $request->user()->notifications()->where('public_id', $notificationPublicId)->first();

        if ($notification === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Notification not found.', 404);
        }

        $notification->markAsRead();

        return ApiResponse::success(['notification' => new NotificationResource($notification)]);
    }
}
