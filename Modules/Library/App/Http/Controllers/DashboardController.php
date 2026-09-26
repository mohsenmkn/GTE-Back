<?php

namespace Modules\Library\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Library\App\Services\LibraryService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private LibraryService $libraryService
    ) {}

    /**
     * GET /api/library/admin/statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->libraryService->getStatistics();
        return response()->json(['data' => $stats]);
    }
}
