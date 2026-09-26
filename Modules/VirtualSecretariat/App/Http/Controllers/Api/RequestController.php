<?php

namespace Modules\VirtualSecretariat\App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\VirtualSecretariat\App\Http\Resources\LetterRequestResource;
use Modules\VirtualSecretariat\App\Models\VsRequest;
use Modules\VirtualSecretariat\App\Services\LetterService;
use Modules\VirtualSecretariat\App\Repositories\SqlServerAutomationRepository;

class RequestController extends Controller
{
    protected LetterService $letterService;
    protected SqlServerAutomationRepository $automationRepo;

    /**
     * ✅ Constructor با Dependency Injection
     */
    public function __construct(
        LetterService $letterService,
        SqlServerAutomationRepository $automationRepo
    ) {
        $this->letterService = $letterService;
        $this->automationRepo = $automationRepo;
    }

    /**
     * لیست درخواست‌های کاربر
     */
    public function index(Request $request): JsonResponse
    {
        $query = VsRequest::with(['template', 'workflowLogs'])
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc');

        // فیلتر بر اساس وضعیت
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // فیلتر بر اساس template
        if ($request->filled('template_id')) {
            $query->where('template_id', $request->template_id);
        }

        $requests = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => LetterRequestResource::collection($requests)->resource,
            'pagination' => [
                'total' => $requests->total(),
                'per_page' => $requests->perPage(),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    /**
     * نمایش جزئیات یک درخواست
     */
    public function show(VsRequest $request): JsonResponse
    {
        // بررسی دسترسی
        if ($request->user_id !== Auth::id() && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'دسترسی غیرمجاز',
            ], 403);
        }

        $request->load(['template', 'user', 'workflowLogs']);

        return response()->json([
            'success' => true,
            'data' => new LetterRequestResource($request),
        ]);
    }

    /**
     * ثبت درخواست جدید
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'template_id' => 'required|exists:vs_templates,id',
            'subject' => 'required|string|max:500',
            'body' => 'required|string',
            'receiver_org' => 'required|string|max:255',
            'receiver_name' => 'required|string|max:255',
        ], [
            'template_id.required' => 'انتخاب نوع نامه الزامی است',
            'subject.required' => 'موضوع نامه الزامی است',
            'body.required' => 'متن نامه الزامی است',
            'receiver_org.required' => 'نام سازمان گیرنده الزامی است',
            'receiver_name.required' => 'نام گیرنده نامه الزامی است',
        ]);

        try {
            $newRequest = $this->letterService->createRequest(
                $request->all(),
                Auth::id()
            );

            return response()->json([
                'success' => true,
                'message' => 'درخواست با موفقیت ثبت و ارسال شد',
                'data' => new LetterRequestResource($newRequest),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در ثبت درخواست: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ دریافت گردش کار نامه به صورت زنده از اتوماسیون
     */
    public function workflowStatus(VsRequest $request): JsonResponse
    {
        // بررسی دسترسی
        if ($request->user_id !== Auth::id() && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'دسترسی غیرمجاز',
            ], 403);
        }

        // بررسی وجود کد نامه در اتوماسیون
        if (!$request->automation_entity_code) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'این درخواست هنوز به اتوماسیون ارسال نشده است',
            ]);
        }

        try {
            // ✅ فراخوانی متد getLetterWorkflow از Repository
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
}
