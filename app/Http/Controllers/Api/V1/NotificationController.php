<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reads back the `database`-channel notifications already written by the
 * `platform:check-subscriptions-and-devices` scheduled command (see
 * docs/LARAVEL_API_DISCOVERY.md §13/§21) — no notification is created here,
 * only listed/marked-read, via the standard Notifiable trait already on
 * User.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->when($request->boolean('unread_only'), fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 20))
            ->withQueryString();

        // Passed as ->items() (not the paginator itself) because
        // ApiResponse::collection() overwrites $meta entirely whenever it's
        // given a LengthAwarePaginator directly — building meta by hand here
        // is the only way to add `unread_count` alongside pagination.
        return ApiResponse::collection(
            $notifications->items(),
            'Notifications retrieved successfully.',
            $this->paginationMeta($notifications) + ['unread_count' => $user->unreadNotifications()->count()],
        );
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return ApiResponse::success(null, 'Notification marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return ApiResponse::success(null, 'All notifications marked as read.');
    }

    /**
     * @return array<string, mixed>
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
