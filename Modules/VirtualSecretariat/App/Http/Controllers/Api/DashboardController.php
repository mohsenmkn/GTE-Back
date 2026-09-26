<?php


namespace Modules\VirtualSecretariat\App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\VirtualSecretariat\App\Models\VsRequest;
use Modules\VirtualSecretariat\App\Models\VsTemplate;

class DashboardController extends Controller
{
    /**
     * آمار کلی دبیرخانه مجازی
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_requests' => VsRequest::count(),
            'pending_requests' => VsRequest::where('status', 'pending')->count(),
            'sent_requests' => VsRequest::where('status', 'sent')->count(),
            'completed_requests' => VsRequest::where('status', 'completed')->count(),
            'failed_requests' => VsRequest::where('status', 'failed')->count(),
            'active_templates' => VsTemplate::where('is_active', true)->count(),

            // آمار 30 روز اخیر
            'requests_last_30_days' => VsRequest::where('created_at', '>=', now()->subDays(30))->count(),

            // میانگین زمان پردازش (روز)
            'avg_processing_days' => VsRequest::whereNotNull('completed_at')
                    ->whereNotNull('sent_at')
                    ->selectRaw('AVG(DATEDIFF(day, sent_at, completed_at)) as avg_days')
                    ->value('avg_days') ?? 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * نمودار وضعیت درخواست‌ها
     */
    public function statusChart(): JsonResponse
    {
        $data = VsRequest::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * آخرین درخواست‌ها
     */
    public function recentRequests(): JsonResponse
    {
        $requests = VsRequest::with(['user', 'template'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }
}
