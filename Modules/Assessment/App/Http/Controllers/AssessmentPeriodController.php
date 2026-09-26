<?php
namespace Modules\Assessment\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Assessment\App\Models\AssessmentPeriod;
use Modules\Assessment\App\Services\AssessmentAutoAssignService;
use Modules\Assessment\App\Services\AssessmentBulkService;

class AssessmentPeriodController extends Controller
{
    public function __construct(
        private AssessmentBulkService $bulkService,
        private AssessmentAutoAssignService $autoAssignService
    ) {}

    /**
     * GET /assessment/periods
     */
    public function index(Request $request): JsonResponse
    {
        $query = AssessmentPeriod::with(['creator', 'targets', 'assessments'])
            ->withCount('assessments');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'periods' => $query->latest()->get(),
        ]);
    }

    /**
     * POST /assessment/periods
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'      => 'required|string|max:255',
            'year'       => 'nullable|integer|min:1390|max:1500',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $period = AssessmentPeriod::create($validated + [
                'status'     => 'draft',
                'created_by' => $request->user()->id,
            ]);

        return response()->json(['period' => $period->load('creator')], 201);
    }

    /**
     * GET /assessment/periods/{period}
     */
    public function show(AssessmentPeriod $period): JsonResponse
    {
        $period->load(['creator', 'targets.group', 'targets.evaluator', 'assessments.employee', 'assessments.evaluator']);

        return response()->json(['period' => $period]);
    }

    /**
     * PUT /assessment/periods/{period}
     */
    public function update(Request $request, AssessmentPeriod $period): JsonResponse
    {
        $validated = $request->validate([
            'title'      => 'sometimes|required|string|max:255',
            'year'       => 'nullable|integer|min:1390|max:1500',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'status'     => 'nullable|in:draft,active,closed',
        ]);

        $period->update($validated);

        return response()->json(['period' => $period->fresh()]);
    }

    /**
     * POST /assessment/periods/{period}/generate
     * تولید انبوه ارزیابی بر اساس اهداف دوره
     */
    public function generate(AssessmentPeriod $period): JsonResponse
    {
        if ($period->status !== 'draft') {
            return response()->json([
                'message' => 'فقط دوره‌های پیش‌نویس قابل تولید ارزیابی هستند.',
            ], 422);
        }

        $result = $this->bulkService->generate($period);

        return response()->json([
            'message' => "ارزیابی‌ها با موفقیت ساخته شدند.",
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'errors'  => $result['errors'] ?? [],
        ]);
    }

    /**
     * POST /assessment/periods/{period}/auto-assign
     * تخصیص خودکار ارزیاب
     */
    public function autoAssign(AssessmentPeriod $period): JsonResponse
    {
        if ($period->status !== 'draft') {
            return response()->json([
                'message' => 'فقط دوره‌های پیش‌نویس قابل تخصیص خودکار هستند.',
            ], 422);
        }

        $result = $this->autoAssignService->autoAssign($period);

        return response()->json([
            'message'    => 'تخصیص خودکار انجام شد.',
            'assigned'   => $result['assigned'],
            'unassigned' => $result['unassigned'],
            'skipped'    => $result['skipped'],
        ]);
    }
}
