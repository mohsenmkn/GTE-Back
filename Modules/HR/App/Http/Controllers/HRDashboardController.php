<?php

namespace Modules\HR\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\HR\App\Services\HRDashboardService;

class HRDashboardController extends Controller
{
    public function __construct(
        private HRDashboardService $dashboardService
    ) {}

    public function overview(): JsonResponse
    {
        try {
            $data = $this->dashboardService->getOverview();

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Throwable $e) {
            \Log::error('HR Dashboard overview failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت اطلاعات داشبورد.',
            ], 500);
        }
    }
}
