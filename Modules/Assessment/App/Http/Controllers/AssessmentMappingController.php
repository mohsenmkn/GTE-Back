<?php


namespace Modules\Assessment\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\Assessment\App\Models\AssessmentPostMapping;
use Modules\Assessment\App\Services\JobFamilyClassifier;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Models\OrganizationalUnit;

class AssessmentMappingController extends Controller
{
    public function __construct(
        private JobFamilyClassifier $classifier,
        private \Modules\Assessment\App\Services\AssessmentAssignmentService $assignments
    )
    {
    }

    /**
     * GET /assessment/mappings
     */
    /**
     * GET /assessment/mappings
     */
    public function index(Request $request): JsonResponse
    {
        // دریافت تمام نگاشت‌های فعال
        $mappings = AssessmentPostMapping::with(['assessmentPost', 'unit', 'creator'])
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();

        // دریافت تمام شناسنامه‌های فعال
        $activePosts = AssessmentPost::where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'grade', 'unit', 'domain']);

        // دریافت تمام پست‌های سازمانی
        $allPositions = EmployeePosition::with('unit')
            ->whereNotNull('post_title')
            ->whereNotNull('organizational_unit_id')
            ->whereHas('user')
            ->get();

        // ✅ فیلتر کردن پست‌های بدون شناسنامه
        $unmappedPositions = $allPositions->filter(
            fn($position) => !$this->assignments->profileFor($position, $activePosts, $mappings)
        );

        // گروه‌بندی بر اساس عنوان پست یکتا
        $unmappedPositions = $unmappedPositions
            ->groupBy('post_title')
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'post_title' => $first->post_title,
                    'unit' => $first->unit?->title,
                    'unit_id' => $first->organizational_unit_id,
                    'count' => $group->count(),
                ];
            })
            ->values();

        return response()->json([
            'mappings' => $mappings,
            'unmapped_positions' => $unmappedPositions,
            'available_posts' => $activePosts,
        ]);
    }

    /**
     * POST /assessment/mappings
     */
    /**
     * POST /assessment/mappings
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mapping_type' => 'required|in:title_pattern,unit',
            'post_title_pattern' => 'required_if:mapping_type,title_pattern|nullable|string|max:255',
            'organizational_unit_id' => 'required_if:mapping_type,unit|nullable|exists:organizational_units,id',
            'assessment_post_id' => 'required|exists:assessment_posts,id',
            'description' => 'nullable|string|max:500',
        ]);

        // ✅ بررسی تکراری بودن با منطق دقیق‌تر
        $existingMapping = AssessmentPostMapping::where('mapping_type', $validated['mapping_type'])
            ->where(function ($q) use ($validated) {
                if ($validated['mapping_type'] === 'title_pattern') {
                    $q->where('post_title_pattern', $validated['post_title_pattern']);
                } else {
                    $q->where('organizational_unit_id', $validated['organizational_unit_id']);
                }
            })
            ->first();

        // ✅ اگر نگاشت قبلی وجود دارد، آن را به‌روزرسانی کن
        if ($existingMapping) {
            $existingMapping->update([
                'assessment_post_id' => $validated['assessment_post_id'],
                'description' => $validated['description'] ?? $existingMapping->description,
                'is_active' => true,
            ]);

            return response()->json([
                'message' => 'نگاشت با موفقیت به‌روزرسانی شد',
                'mapping' => $existingMapping->fresh()->load(['assessmentPost', 'unit']),
                'updated' => true,
            ]);
        }

        // ✅ ایجاد نگاشت جدید
        $mapping = AssessmentPostMapping::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'نگاشت با موفقیت ایجاد شد',
            'mapping' => $mapping->load(['assessmentPost', 'unit']),
            'updated' => false,
        ], 201);
    }

    /**
     * DELETE /assessment/mappings/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $mapping = AssessmentPostMapping::findOrFail($id);
        $mapping->delete();

        return response()->json(['message' => 'نگاشت حذف شد']);
    }

    /**
     * PUT /assessment/mappings/{id}/toggle
     */
    public function toggle(int $id): JsonResponse
    {
        $mapping = AssessmentPostMapping::findOrFail($id);
        $mapping->update(['is_active' => !$mapping->is_active]);

        return response()->json([
            'message' => $mapping->is_active ? 'نگاشت فعال شد' : 'نگاشت غیرفعال شد',
            'is_active' => $mapping->is_active,
        ]);
    }

    /**
     * GET /assessment/mappings/no-evaluator
     */
    public function noEvaluator(Request $request): JsonResponse
    {
        $validated = $request->validate(['cycle_id' => 'required|integer|exists:assessment_cycles,id']);
        $startTime = microtime(true);
        $plan = $this->assignments->plan((int) $validated['cycle_id']);
        $positions = collect($plan['rows'])->where('status', 'no_evaluator')->values();

        return response()->json([
            'positions' => $positions,
            'summary' => [
                'total' => $positions->count(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ],
        ]);
    }

    /**
     * POST /assessment/mappings/assign-evaluator
     */
    public function assignEvaluator(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cycle_id' => 'required|exists:assessment_cycles,id',
            'user_id' => 'required|exists:users,id',
            'evaluator_id' => 'required|exists:users,id',
        ]);

        if ((int) $validated['user_id'] === (int) $validated['evaluator_id']) {
            return response()->json([
                'message' => 'ارزیاب نمی‌تواند خود کارمند باشد',
            ], 422);
        }

        $assessment = Assessment::where('cycle_id', $validated['cycle_id'])
            ->where('employee_user_id', $validated['user_id'])->first();
        if ($assessment && !in_array($assessment->status, [Assessment::STATUS_DRAFT, Assessment::STATUS_REJECTED], true)) {
            return response()->json(['message' => 'ارزیابی ثبت‌شده قابل تغییر ارزیاب نیست.'], 422);
        }
        $employee = EmployeePosition::with('unit')->where('user_id', $validated['user_id'])->firstOrFail();
        $post = $this->assignments->profileFor($employee);
        if (!$assessment && !$post) {
            return response()->json(['message' => 'شناسنامه شایستگی برای کارمند یافت نشد.'], 422);
        }
        $assessment = Assessment::updateOrCreate(
            ['cycle_id' => $validated['cycle_id'], 'employee_user_id' => $validated['user_id']],
            [
                'evaluator_user_id' => $validated['evaluator_id'],
                'post_id' => $assessment?->post_id ?? $post->id,
            ]
        );

        return response()->json([
            'message' => 'ارزیاب با موفقیت تعیین شد',
            'assessment' => $assessment->load('employee', 'evaluator'),
        ]);
    }


}





























/*namespace Modules\Assessment\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\Assessment\App\Models\AssessmentPostMapping;
use Modules\Assessment\App\Services\JobFamilyClassifier;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Models\OrganizationalUnit;

class AssessmentMappingController extends Controller
{
    private const EVALUATOR_CHAIN = [
        'معاون' => 'مدیرعامل',
        'مدیر' => 'معاون',
        'رئیس' => 'مدیر',
        'سرپرست/کارشناس ارشد' => 'رئیس',
        'کارشناس' => 'سرپرست/کارشناس ارشد',
        'کاردان/تکنسین/مسئول' => 'سرپرست/کارشناس ارشد',
        'متصدی' => 'سرپرست/کارشناس ارشد',
        'راننده/اپراتور' => 'سرپرست/کارشناس ارشد',
        'کارگر' => 'سرپرست/کارشناس ارشد',
    ];

    public function __construct(
        private JobFamilyClassifier $classifier
    ) {
    }

    /**
     * GET /assessment/mappings
     */
    /**
     * GET /assessment/mappings
     */
//    public function index(Request $request): JsonResponse
//    {
//        // دریافت تمام نگاشت‌های فعال
//        $mappings = AssessmentPostMapping::with(['assessmentPost', 'unit', 'creator'])
//            ->where('is_active', true)
//            ->orderBy('created_at', 'desc')
//            ->get();
//
//        // دریافت تمام شناسنامه‌های فعال
//        $activePosts = AssessmentPost::where('is_active', true)->get();
//
//        // دریافت تمام پست‌های سازمانی
//        $allPositions = EmployeePosition::with('unit')
//            ->whereNotNull('post_title')
//            ->whereNotNull('organizational_unit_id')
//            ->whereHas('user')
//            ->get();
//
//        // ✅ فیلتر کردن پست‌های بدون شناسنامه
//        $unmappedPositions = $allPositions->filter(function ($pos) use ($mappings, $activePosts) {
//            // ۱. بررسی نگاشت دستی
//            $hasManualMapping = $mappings->first(function ($mapping) use ($pos) {
//                if ($mapping->mapping_type === 'title_pattern') {
//                    $pattern = str_replace('%', '.*', $mapping->post_title_pattern);
//                    return preg_match('/^' . $pattern . '$/u', $pos->post_title) === 1;
//                }
//                return $mapping->organizational_unit_id === $pos->organizational_unit_id;
//            });
//
//            if ($hasManualMapping) {
//                return false; // نگاشت دستی دارد
//            }
//
//            // ۲. بررسی شناسنامه طبیعی (grade + unit)
//            $family = $this->classifier->classify($pos->post_title);
//            $unitTitle = $pos->unit?->title;
//
//            $hasNaturalProfile = $activePosts->first(function ($post) use ($family, $unitTitle) {
//                return $post->grade === $family && $post->unit === $unitTitle;
//            });
//
//            if ($hasNaturalProfile) {
//                return false; // شناسنامه طبیعی دارد
//            }
//
//            return true; // بدون شناسنامه
//        });
//
//        // گروه‌بندی بر اساس عنوان پست یکتا
//        $unmappedPositions = $unmappedPositions
//            ->groupBy('post_title')
//            ->map(function ($group) {
//                $first = $group->first();
//                return [
//                    'post_title' => $first->post_title,
//                    'unit' => $first->unit?->title,
//                    'unit_id' => $first->organizational_unit_id,
//                    'count' => $group->count(),
//                ];
//            })
//            ->values();
//
//        // شناسنامه‌های موجود برای dropdown
//        $availablePosts = $activePosts
//            ->orderBy('title')
//            ->get(['id', 'title', 'grade', 'unit']);
//
//        return response()->json([
//            'mappings' => $mappings,
//            'unmapped_positions' => $unmappedPositions,
//            'available_posts' => $availablePosts,
//        ]);
//    }

    /**
     * POST /assessment/mappings
     */
//    public function store(Request $request): JsonResponse
//    {
//        $validated = $request->validate([
//            'mapping_type' => 'required|in:title_pattern,unit',
//            'post_title_pattern' => 'nullable|string|max:255',
//            'organizational_unit_id' => 'nullable|exists:organizational_units,id',
//            'assessment_post_id' => 'required|exists:assessment_posts,id',
//            'description' => 'nullable|string|max:500',
//        ]);
//
//        $exists = AssessmentPostMapping::where('mapping_type', $validated['mapping_type'])
//            ->where(function ($q) use ($validated) {
//                if ($validated['mapping_type'] === 'title_pattern') {
//                    $q->where('post_title_pattern', $validated['post_title_pattern']);
//                } else {
//                    $q->where('organizational_unit_id', $validated['organizational_unit_id']);
//                }
//            })
//            ->exists();
//
//        if ($exists) {
//            return response()->json(['message' => 'این نگاشت قبلاً ثبت شده است'], 422);
//        }
//
//        $mapping = AssessmentPostMapping::create([
//            ...$validated,
//            'created_by' => $request->user()->id,
//        ]);
//
//        return response()->json([
//            'message' => 'نگاشت با موفقیت ایجاد شد',
//            'mapping' => $mapping->load(['assessmentPost', 'unit']),
//        ], 201);
//    }

    /**
     * DELETE /assessment/mappings/{id}
     */
//    public function destroy(int $id): JsonResponse
//    {
//        $mapping = AssessmentPostMapping::findOrFail($id);
//        $mapping->delete();
//
//        return response()->json(['message' => 'نگاشت حذف شد']);
//    }

    /**
     * PUT /assessment/mappings/{id}/toggle
     */
//    public function toggle(int $id): JsonResponse
//    {
//        $mapping = AssessmentPostMapping::findOrFail($id);
//        $mapping->update(['is_active' => !$mapping->is_active]);
//
//        return response()->json([
//            'message' => $mapping->is_active ? 'نگاشت فعال شد' : 'نگاشت غیرفعال شد',
//            'is_active' => $mapping->is_active,
//        ]);
//    }

    /**
     * ✅ GET /assessment/mappings/no-evaluator
     * نسخه بهینه‌شده - بدون کوئری در حلقه
     */
//    public function noEvaluator(Request $request): JsonResponse
//    {
//        // افزایش زمان اجرا
//        set_time_limit(300);
//
//        $cycleId = $request->query('cycle_id');
//
//        if (!$cycleId) {
//            return response()->json(['message' => 'cycle_id الزامی است'], 422);
//        }
//
//        $startTime = microtime(true);
//
//        // ✅ . لود تمام داده‌ها در یک کوئری (Eager Loading)
//        $allPositions = EmployeePosition::with(['user', 'unit'])
//            ->whereNotNull('post_title')
//            ->whereNotNull('organizational_unit_id')
//            ->whereHas('user')
//            ->get();
//
//        // ✅ ۲. Cache کردن شناسنامه‌های فعال
//        $activePosts = AssessmentPost::where('is_active', true)->get();
//
//        // ✅ ۳. Cache کردن ارزیابی‌های موجود در این چرخه
//        $existingAssessments = Assessment::where('cycle_id', $cycleId)
//            ->pluck('employee_user_id')
//            ->flip();
//
//        // ✅ ۴. Cache کردن تمام واحدها
//        $allUnits = OrganizationalUnit::all()->keyBy('id');
//
//        // ✅ ۵. گروه‌بندی پوزیشن‌ها بر اساس واحد (برای جستجوی سریع ارزیاب)
//        $positionsByUnit = $allPositions->groupBy('organizational_unit_id');
//
//        $noEvaluatorList = [];
//        $debugStats = [
//            'total_positions' => $allPositions->count(),
//            'no_family' => 0,
//            'no_profile' => 0,
//            'has_assessment' => 0,
//            'no_evaluator_family' => 0,
//            'has_evaluator' => 0,
//            'no_evaluator' => 0,
//        ];
//
//        foreach ($allPositions as $pos) {
//            $family = $this->classifier->classify($pos->post_title);
//
//            // بررسی خانواده شغلی
//            if (!$family) {
//                $debugStats['no_family']++;
//                continue;
//            }
//
//            // بررسی وجود شناسنامه (از cache)
//            $unitTitle = $pos->unit?->title;
//            $hasProfile = $activePosts->first(fn($p) => $p->grade === $family && $p->unit === $unitTitle);
//
//            if (!$hasProfile) {
//                $debugStats['no_profile']++;
//                continue;
//            }
//
//            // بررسی وجود ارزیابی (از cache)
//            if ($existingAssessments->has($pos->user_id)) {
//                $debugStats['has_assessment']++;
//                continue;
//            }
//
//            // بررسی زنجیره ارزیابی
//            $evaluatorFamily = self::EVALUATOR_CHAIN[$family] ?? null;
//            if (!$evaluatorFamily) {
//                $debugStats['no_evaluator_family']++;
//                continue;
//            }
//
//            // ✅ یافتن ارزیاب (از cache - بدون کوئری)
//            $evaluator = $this->findEvaluatorInUnitFast(
//                $pos,
//                $evaluatorFamily,
//                $positionsByUnit,
//                $allUnits
//            );
//
//            if ($evaluator) {
//                $debugStats['has_evaluator']++;
//                continue;
//            }
//
//            // ✅ این پرسنل ارزیاب ندارد
//            $debugStats['no_evaluator']++;
//
//            // ✅ دریافت ارزیاب‌های پیشنهادی (از cache)
//            $suggestedEvaluators = $this->getSuggestedEvaluatorsFast(
//                $pos,
//                $evaluatorFamily,
//                $positionsByUnit,
//                $allUnits
//            );
//
//            $noEvaluatorList[] = [
//                'user_id' => $pos->user_id,
//                'name' => $pos->user?->name,
//                'personnel_code' => $pos->personnel_code,
//                'post_title' => $pos->post_title,
//                'unit' => $unitTitle,
//                'family' => $family,
//                'evaluator_family_needed' => $evaluatorFamily,
//                'suggested_evaluators' => $suggestedEvaluators,
//            ];
//        }
//
//        $endTime = microtime(true);
//        $executionTime = round(($endTime - $startTime) * 1000, 2);
//
//        Log::info('No Evaluator Debug', [
//            'stats' => $debugStats,
//            'execution_time_ms' => $executionTime,
//        ]);
//
//        return response()->json([
//            'positions' => $noEvaluatorList,
//            'summary' => [
//                'total' => count($noEvaluatorList),
//                'debug' => $debugStats,
//                'execution_time_ms' => $executionTime,
//            ],
//        ]);
//    }

    /**
     * ✅ POST /assessment/mappings/assign-evaluator
     */
//    public function assignEvaluator(Request $request): JsonResponse
//    {
//        $validated = $request->validate([
//            'cycle_id' => 'required|exists:assessment_cycles,id',
//            'user_id' => 'required|exists:users,id',
//            'evaluator_id' => 'required|exists:users,id',
//        ]);
//
//        if ($validated['user_id'] === $validated['evaluator_id']) {
//            return response()->json([
//                'message' => 'ارزیاب نمی‌تواند خود کارمند باشد',
//            ], 422);
//        }
//
//        $assessment = Assessment::updateOrCreate(
//            [
//                'cycle_id' => $validated['cycle_id'],
//                'employee_user_id' => $validated['user_id'],
//            ],
//            [
//                'evaluator_user_id' => $validated['evaluator_id'],
//                'status' => 'draft',
//            ]
//        );
//
//        return response()->json([
//            'message' => 'ارزیاب با موفقیت تعیین شد',
//            'assessment' => $assessment->load('employee', 'evaluator'),
//        ]);
//    }

    /**
     * ✅ یافتن ارزیاب - نسخه بهینه‌شده (بدون کوئری دیتابیس)
     */
//    private function findEvaluatorInUnitFast(
//        $pos,
//        string $evaluatorFamily,
//        $positionsByUnit,
//        $allUnits
//    ): ?EmployeePosition {
//        $unitId = $pos->organizational_unit_id;
//
//        while ($unitId && isset($allUnits[$unitId])) {
//            $candidates = $positionsByUnit->get($unitId, collect());
//
//            $found = $candidates->first(function ($p) use ($evaluatorFamily, $pos) {
//                return $p->user_id !== $pos->user_id
//                    && $this->classifier->classify($p->post_title) === $evaluatorFamily;
//            });
//
//            if ($found) return $found;
//            $unitId = $allUnits[$unitId]->parent_id;
//        }
//
//        return null;
//    }

    /**
     * ✅ دریافت ارزیاب‌های پیشنهادی - نسخه بهینه‌شده
     */
//    private function getSuggestedEvaluatorsFast(
//        $pos,
//        string $evaluatorFamily,
//        $positionsByUnit,
//        $allUnits
//    ): array {
//        $unitId = $pos->organizational_unit_id;
//        $suggested = [];
//
//        while ($unitId && isset($allUnits[$unitId])) {
//            $candidates = $positionsByUnit->get($unitId, collect());
//
//            $evaluators = $candidates
//                ->filter(function ($p) use ($evaluatorFamily, $pos) {
//                    return $p->user_id !== $pos->user_id
//                        && $this->classifier->classify($p->post_title) === $evaluatorFamily;
//                })
//                ->map(fn($p) => [
//                    'id' => $p->user_id,
//                    'name' => $p->user?->name,
//                    'post_title' => $p->post_title,
//                ])
//                ->values()
//                ->toArray();
//
//            if (!empty($evaluators)) {
//                $suggested = array_merge($suggested, $evaluators);
//            }
//
//            $unitId = $allUnits[$unitId]->parent_id;
//        }
//
//        return $suggested;
//    }
//}
//*/
