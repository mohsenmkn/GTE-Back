<?php

namespace Modules\HR\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\HR\App\Services\OrgChartService;

class OrgChartController extends Controller
{
    public function __construct(
        private OrgChartService $orgChartService
    ) {}

    /**
     * GET /api/v1/hr/org-chart
     * دریافت درخت چارت سازمانی با پرسنل
     */
    public function index(): JsonResponse
    {
        $tree  = $this->orgChartService->buildTree();
        $stats = $this->orgChartService->getStats();

        return response()->json([
            'tree'  => $tree,
            'stats' => $stats,
        ]);
    }

    /**
     * GET /api/v1/hr/org-chart/units
     * لیست تخت واحدها (برای select و فیلتر)
     */
    public function units(): JsonResponse
    {
        $units = \Modules\HR\App\Models\OrganizationalUnit::select(
            'id', 'title', 'code', 'level', 'parent_id'
        )
            ->where('is_active', true)
            ->orderBy('level')
            ->orderBy('title')
            ->get();

        return response()->json(['units' => $units]);
    }
}
