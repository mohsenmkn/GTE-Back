<?php

namespace Modules\Library\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Library\App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * GET /api/library/my-notifications
     * دریافت اعلان‌های کاربر فعلی
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $unreadOnly = $request->query('unread', false);

        $query = Notification::where('user_id', $user->id)
            ->with('reservation.bookCopy.book');

        if ($unreadOnly) {
            $query->where('is_read', false);
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'total' => $notifications->total(),
                'unread_count' => Notification::where('user_id', $user->id)
                    ->where('is_read', false)
                    ->count(),
            ],
        ]);
    }

    /**
     * PUT /api/library/my-notifications/{id}/read
     * علامت‌گذاری یک اعلان به عنوان خوانده شده
     */
    public function markAsRead(int $id, Request $request): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => 'اعلان به عنوان خوانده شده علامت‌گذاری شد.',
            'data' => $notification,
        ]);
    }

    /**
     * PUT /api/library/my-notifications/read-all
     * علامت‌گذاری همه اعلان‌ها به عنوان خوانده شده
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'message' => "تعداد {$count} اعلان به عنوان خوانده شده علامت‌گذاری شد.",
        ]);
    }
}
