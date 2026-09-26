<?php

namespace Modules\Complaint\App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Complaint\App\Services\ComplaintStatisticsService;

class ComplaintStatisticsController extends Controller
{
    public function __construct(
        protected ComplaintStatisticsService $statisticsService
    ) {
    }

    public function index(): JsonResponse
    {
        $statistics = $this->statisticsService->getDashboardStatistics();

        return response()->json([
            'data' => $statistics,
        ]);
    }
}
