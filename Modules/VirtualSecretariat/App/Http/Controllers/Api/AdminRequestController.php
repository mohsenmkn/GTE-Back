<?php


namespace Modules\VirtualSecretariat\App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\VirtualSecretariat\App\Http\Resources\AdminLetterRequestResource;
use Modules\VirtualSecretariat\App\Models\VsRequest;
use Modules\VirtualSecretariat\App\Repositories\SqlServerAutomationRepository;
use Modules\VirtualSecretariat\App\Services\AdminLetterService;

class AdminRequestController extends Controller
{
    protected AdminLetterService $adminService;
    protected SqlServerAutomationRepository $automationRepo;

    public function __construct(
        AdminLetterService            $adminService,
        SqlServerAutomationRepository $automationRepo
    )
    {
        $this->adminService = $adminService;
        $this->automationRepo = $automationRepo;
    }

    /**
     * لیست تمام درخواست‌ها با فیلتر و pagination
     *
     * GET /api/v1/virtual-secretariat/admin/requests
     */
    public function index(Request $request): JsonResponse
    {
        $query = VsRequest::with(['template', 'user'])
            ->orderBy('created_at', 'desc');

        // فیلتر بر اساس وضعیت
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // فیلتر بر اساس کاربر
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // فیلتر بر اساس قالب
        if ($request->filled('template_id')) {
            $query->where('template_id', $request->template_id);
        }

        // جستجو در عنوان
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('automation_letter_number', 'LIKE', "%{$search}%");
            });
        }

        // فیلتر تاریخ
        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->to_date . ' 23:59:59');
        }

        $perPage = $request->get('per_page', 20);
        $requests = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => AdminLetterRequestResource::collection($requests)->resource,
            'pagination' => [
                'total' => $requests->total(),
                'per_page' => $requests->perPage(),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'from' => $requests->firstItem(),
                'to' => $requests->lastItem(),
            ],
            'filters' => $request->only(['status', 'user_id', 'template_id', 'search', 'from_date', 'to_date']),
        ]);
    }

    /**
     * نمایش جزئیات یک درخواست
     *
     * GET /api/v1/virtual-secretariat/admin/requests/{request}
     */
    public function show(VsRequest $request): JsonResponse
    {
        $request->load(['template', 'user', 'workflowLogs']);

        return response()->json([
            'success' => true,
            'data' => new AdminLetterRequestResource($request),
        ]);
    }

    /**
     * دریافت گردش کار نامه
     *
     * GET /api/v1/virtual-secretariat/admin/requests/{request}/workflow
     */
    public function workflowStatus(VsRequest $request): JsonResponse
    {
        if (!$request->automation_entity_code) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'این درخواست هنوز به اتوماسیون ارسال نشده است',
            ]);
        }

        try {
            $workflow = $this->automationRepo->getLetterWorkflow($request->automation_entity_code);

            return response()->json([
                'success' => true,
                'data' => $workflow,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت گردش کار: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تغییر وضعیت درخواست (تأیید/رد/لغو)
     *
     * PUT /api/v1/virtual-secretariat/admin/requests/{request}/status
     */
    public function updateStatus(Request $request, VsRequest $vsRequest): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:completed,rejected,cancelled',
            'admin_note' => 'nullable|string|max:1000',
        ], [
            'status.required' => 'تعیین وضعیت الزامی است',
            'status.in' => 'وضعیت انتخابی معتبر نیست',
        ]);

        try {
            $updatedRequest = $this->adminService->updateRequestStatus(
                $vsRequest,
                $request->status,
                $request->admin_note ?? null,
                Auth::id()
            );

            return response()->json([
                'success' => true,
                'message' => 'وضعیت درخواست با موفقیت به‌روزرسانی شد',
                'data' => new AdminLetterRequestResource($updatedRequest),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در تغییر وضعیت: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تلاش مجدد برای ارسال درخواست ناموفق
     *
     * POST /api/v1/virtual-secretariat/admin/requests/{request}/retry
     */
    public function retry(VsRequest $request): JsonResponse
    {
        if ($request->status !== 'failed') {
            return response()->json([
                'success' => false,
                'message' => 'فقط درخواست‌های ناموفق قابل تلاش مجدد هستند',
            ], 400);
        }

        try {
            $updatedRequest = $this->adminService->retryFailedRequest($request);

            return response()->json([
                'success' => true,
                'message' => 'درخواست با موفقیت دوباره ارسال شد',
                'data' => new AdminLetterRequestResource($updatedRequest),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در تلاش مجدد: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * حذف درخواست
     *
     * DELETE /api/v1/virtual-secretariat/admin/requests/{request}
     */
    public function destroy(VsRequest $request): JsonResponse
    {
        try {
            // اگر درخواست به اتوماسیون ارسال شده، ابتدا از SQL Server پاک شود
            if ($request->automation_entity_code) {
                $this->automationRepo->cleanupLetter($request->automation_entity_code);
            }

            $request->delete();

            return response()->json([
                'success' => true,
                'message' => 'درخواست با موفقیت حذف شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در حذف درخواست: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * آمار و گزارش درخواست‌ها
     *
     * GET /api/v1/virtual-secretariat/admin/requests/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $fromDate = $request->get('from_date', now()->subDays(30)->format('Y-m-d'));
        $toDate = $request->get('to_date', now()->format('Y-m-d'));

        $stats = [
            'total' => VsRequest::count(),
            'by_status' => VsRequest::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status'),
            'by_template' => VsRequest::with('template')
                ->select('template_id', DB::raw('count(*) as count'))
                ->groupBy('template_id')
                ->get()
                ->map(fn($item) => [
                    'template_id' => $item->template_id,
                    'template_name' => $item->template?->name ?? 'نامشخص',
                    'count' => $item->count,
                ]),
            'recent_count' => VsRequest::where('created_at', '>=', now()->subDays(7))->count(),
            'today_count' => VsRequest::whereDate('created_at', today())->count(),
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
                'total' => VsRequest::whereBetween('created_at', [$fromDate, $toDate . ' 23:59:59'])->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * خروجی Excel/CSV از درخواست‌ها
     *
     * GET /api/v1/virtual-secretariat/admin/requests/export
     */
    public function export(Request $request): JsonResponse
    {
        $query = VsRequest::with(['template', 'user'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->limit(1000)->get();

        $data = $requests->map(fn($r) => [
            'id' => $r->id,
            'title' => $r->title,
            'user' => $r->user?->name ?? '-',
            'template' => $r->template?->name ?? '-',
            'status' => $r->status,
            'letter_number' => $r->automation_letter_number ?? '-',
            'entity_code' => $r->automation_entity_code ?? '-',
            'created_at' => $r->created_at?->format('Y-m-d H:i'),
        ]);

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => $data->count(),
        ]);
    }



//    /**
//     * نمایش جزئیات یک درخواست
//     */
//    public function show(VsRequest $vsRequest): JsonResponse
//    {
//        $vsRequest->load(['template', 'user', 'workflowLogs']);
//
//        return response()->json([
//            'success' => true,
//            'data' => new AdminLetterRequestResource($vsRequest),
//        ]);
//    }
//
//    /**
//     * دریافت گردش کار نامه
//     */
//    public function workflowStatus(VsRequest $vsRequest): JsonResponse
//    {
//        if (!$vsRequest->automation_entity_code) {
//            return response()->json([
//                'success' => true,
//                'data' => [],
//                'message' => 'این درخواست هنوز به اتوماسیون ارسال نشده است',
//            ]);
//        }
//
//        try {
//            $workflow = $this->automationRepo->getLetterWorkflow($vsRequest->automation_entity_code);
//
//            return response()->json([
//                'success' => true,
//                'data' => $workflow,
//            ]);
//        } catch (\Exception $e) {
//            return response()->json([
//                'success' => false,
//                'message' => 'خطا در دریافت گردش کار: ' . $e->getMessage(),
//            ], 500);
//        }
//    }
//
//    /**
//     * تغییر وضعیت درخواست (تأیید/رد/لغو)
//     */
//    public function updateStatus(Request $httpRequest, VsRequest $vsRequest): JsonResponse
//    {
//        $httpRequest->validate([
//            'status' => 'required|in:completed,rejected,cancelled',
//            'admin_note' => 'nullable|string|max:1000',
//        ], [
//            'status.required' => 'تعیین وضعیت الزامی است',
//            'status.in' => 'وضعیت انتخابی معتبر نیست',
//        ]);
//
//        try {
//            $updatedRequest = $this->adminService->updateRequestStatus(
//                $vsRequest,
//                $httpRequest->status,
//                $httpRequest->admin_note ?? null,
//                Auth::id()
//            );
//
//            return response()->json([
//                'success' => true,
//                'message' => 'وضعیت درخواست با موفقیت به‌روزرسانی شد',
//                'data' => new AdminLetterRequestResource($updatedRequest),
//            ]);
//        } catch (\Exception $e) {
//            return response()->json([
//                'success' => false,
//                'message' => 'خطا در تغییر وضعیت: ' . $e->getMessage(),
//            ], 500);
//        }
//    }
//
//    /**
//     * تلاش مجدد برای ارسال درخواست ناموفق
//     */
//    public function retry(VsRequest $vsRequest): JsonResponse
//    {
//        if ($vsRequest->status !== 'failed') {
//            return response()->json([
//                'success' => false,
//                'message' => 'فقط درخواست‌های ناموفق قابل تلاش مجدد هستند',
//            ], 400);
//        }
//
//        try {
//            $updatedRequest = $this->adminService->retryFailedRequest($vsRequest);
//
//            return response()->json([
//                'success' => true,
//                'message' => 'درخواست با موفقیت دوباره ارسال شد',
//                'data' => new AdminLetterRequestResource($updatedRequest),
//            ]);
//        } catch (\Exception $e) {
//            return response()->json([
//                'success' => false,
//                'message' => 'خطا در تلاش مجدد: ' . $e->getMessage(),
//            ], 500);
//        }
//    }
//
//    /**
//     * حذف درخواست
//     */
//    public function destroy(VsRequest $vsRequest): JsonResponse
//    {
//        try {
//            if ($vsRequest->automation_entity_code) {
//                $this->automationRepo->cleanupLetter($vsRequest->automation_entity_code);
//            }
//
//            $vsRequest->delete();
//
//            return response()->json([
//                'success' => true,
//                'message' => 'درخواست با موفقیت حذف شد',
//            ]);
//        } catch (\Exception $e) {
//            return response()->json([
//                'success' => false,
//                'message' => 'خطا در حذف درخواست: ' . $e->getMessage(),
//            ], 500);
//        }
//    }
}
