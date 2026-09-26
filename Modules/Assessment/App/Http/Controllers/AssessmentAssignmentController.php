<?php
namespace Modules\Assessment\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\Assessment\App\Services\JobFamilyClassifier;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Models\OrganizationalUnit;

class AssessmentAssignmentController extends Controller
{
    /**
     * زنجیره ارزیابی: هر خانواده توسط چه خانواده‌ای ارزیابی می‌شود
     */
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
     * GET /assessment/auto-assign/preview
     * پیش‌نمایش تخصیص خودکار
     */
    public function preview(Request $request): JsonResponse
    {
        $cycleId = $request->query('cycle_id') ? (int) $request->query('cycle_id') : null;

        if (!$cycleId) {
            return response()->json([
                'message' => 'cycle_id الزامی است',
            ], 422);
        }

        $plan = $this->buildPlan($cycleId);

        return response()->json([
            'rows' => $plan['rows'],
            'summary' => $plan['summary'],
        ]);
    }

    /**
     * POST /assessment/auto-assign/execute
     * اجرای تخصیص خودکار
     */
    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            'cycle_id' => 'required|exists:assessment_cycles,id',
        ]);

        $cycleId = (int) $request->input('cycle_id');
        $plan = $this->buildPlan($cycleId);

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($plan, $cycleId, &$created, &$skipped) {
            foreach ($plan['rows'] as $row) {
                if ($row['status'] !== 'ready') {
                    $skipped++;
                    continue;
                }

                $assessment = Assessment::firstOrCreate(
                    [
                        'cycle_id' => $cycleId,
                        'employee_user_id' => $row['user_id'],
                    ],
                    [
                        'post_id' => $row['post_id'],
                        'evaluator_user_id' => $row['evaluator_id'],
                        'status' => 'draft',
                    ]
                );

                $assessment->wasRecentlyCreated ? $created++ : $skipped++;
            }
        });

        return response()->json([
            'message' => "تخصیص کامل شد: {$created} ارزیابی ساخته شد، {$skipped} رد شد.",
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    /**
     * ساخت برنامه تخصیص (پیش‌نمایش و اجرا مشترک)
     */
// در AssessmentAssignmentController.php
//    private function buildPlan(int $cycleId): array
//    {
//        $positions = EmployeePosition::with(['user', 'unit'])
//            ->whereNotNull('post_title')
//            ->whereNotNull('organizational_unit_id')
//            ->whereHas('user')
//            ->get();
//
//        $profiles = AssessmentPost::where('is_active', true)->get();
//        $byUnit = $positions->groupBy('organizational_unit_id');
//        $units = OrganizationalUnit::all()->keyBy('id');
//
//        $existing = Assessment::where('cycle_id', $cycleId)
//            ->pluck('employee_user_id')
//            ->flip();
//
//        $rows = [];
//        $summary = [
//            'total' => 0, 'ready' => 0, 'exists' => 0,
//            'no_profile' => 0, 'no_evaluator' => 0, 'out_of_scope' => 0,
//        ];
//
//        foreach ($positions as $pos) {
//            $summary['total']++;
//
//            $family = $this->classifier->classify($pos->post_title);
//
//            if (!$family) {
//                $summary['out_of_scope']++;
//                $rows[] = $this->row($pos, null, null, null, 'out_of_scope');
//                continue;
//            }
//
//            // ✅ بررسی نگاشت دستی (با اولویت بالاتر)
//            $manualPost = \Modules\Assessment\App\Models\AssessmentPostMapping::findMappingForPosition(
//                $pos->post_title,
//                $pos->organizational_unit_id
//            );
//
//            if ($manualPost) {
//                $profile = $manualPost;
//            } else {
//                $unitTitle = $pos->unit?->title;
//                $profile = $profiles->first(fn($p) => $p->grade === $family && $p->unit === $unitTitle)
//                    ?? $profiles->first(fn($p) => $p->grade === $family);
//            }
//
//            if (!$profile) {
//                $summary['no_profile']++;
//                $rows[] = $this->row($pos, $family, null, null, 'no_profile');
//                continue;
//            }
//
//            // ✅ یافتن ارزیاب با بررسی hierarchy واقعی
//            $evaluator = $this->findEvaluator($pos, $family, $byUnit, $units);
//
//            if (!$evaluator) {
//                $summary['no_evaluator']++;
//                $rows[] = $this->row($pos, $family, $profile, null, 'no_evaluator');
//                continue;
//            }
//
//            if ($existing->has($pos->user_id)) {
//                $summary['exists']++;
//                $rows[] = $this->row($pos, $family, $profile, $evaluator, 'exists');
//                continue;
//            }
//
//            $summary['ready']++;
//            $rows[] = $this->row($pos, $family, $profile, $evaluator, 'ready');
//        }
//
//        return ['rows' => $rows, 'summary' => $summary];
//    }

    private function buildPlan(int $cycleId): array
    {
        $positions = EmployeePosition::with(['user', 'unit'])
            ->whereNotNull('post_title')
            ->whereNotNull('organizational_unit_id')
            ->whereHas('user')
            ->get();

        $profiles = AssessmentPost::where('is_active', true)->get();
        $byUnit = $positions->groupBy('organizational_unit_id');
        $units = OrganizationalUnit::all()->keyBy('id');

        $existing = Assessment::where('cycle_id', $cycleId)
            ->pluck('employee_user_id')
            ->flip();

        $rows = [];
        $summary = [
            'total' => 0, 'ready' => 0, 'exists' => 0,
            'no_profile' => 0, 'no_evaluator' => 0, 'out_of_scope' => 0,
        ];

        foreach ($positions as $pos) {
            $summary['total']++;

            $family = $this->classifier->classify($pos->post_title);

            if (!$family) {
                $summary['out_of_scope']++;
                $rows[] = $this->row($pos, null, null, null, 'out_of_scope');
                continue;
            }

            // ✅ یافتن شناسنامه با اولویت‌بندی دقیق
            $profile = $this->findProfile($pos, $family, $profiles);

            if (!$profile) {
                $summary['no_profile']++;
                $rows[] = $this->row($pos, $family, null, null, 'no_profile');
                continue;
            }

            $evaluator = $this->findEvaluator($pos, $family, $byUnit, $units);

            if (!$evaluator) {
                $summary['no_evaluator']++;
                $rows[] = $this->row($pos, $family, $profile, null, 'no_evaluator');
                continue;
            }

            if ($existing->has($pos->user_id)) {
                $summary['exists']++;
                $rows[] = $this->row($pos, $family, $profile, $evaluator, 'exists');
                continue;
            }

            $summary['ready']++;
            $rows[] = $this->row($pos, $family, $profile, $evaluator, 'ready');
        }

        return ['rows' => $rows, 'summary' => $summary];
    }

    /**
     * ✅ یافتن شناسنامه با اولویت‌بندی دقیق
     * ۱. نگاشت دستی بر اساس عنوان پست
     * ۲. نگاشت دستی بر اساس واحد سازمانی
     * ۳. شناسنامه با grade + unit دقیق
     * ۴. شناسنامه فقط با grade (fallback)
     */
    private function findProfile($pos, string $family, $profiles): ?AssessmentPost
    {
        // ۱. بررسی نگاشت دستی بر اساس عنوان پست
        $titleMapping = \Modules\Assessment\App\Models\AssessmentPostMapping::active()
            ->where('mapping_type', 'title_pattern')
            ->whereNotNull('post_title_pattern')
            ->get()
            ->first(function ($mapping) use ($pos) {
                $pattern = str_replace('%', '.*', $mapping->post_title_pattern);
                return preg_match('/^' . $pattern . '$/u', $pos->post_title) === 1;
            });

        if ($titleMapping) {
            return $titleMapping->assessmentPost;
        }

        // ۲. بررسی نگاشت دستی بر اساس واحد سازمانی
        $unitMapping = \Modules\Assessment\App\Models\AssessmentPostMapping::active()
            ->where('mapping_type', 'unit')
            ->where('organizational_unit_id', $pos->organizational_unit_id)
            ->first();

        if ($unitMapping) {
            return $unitMapping->assessmentPost;
        }

        // . جستجوی شناسنامه با grade + unit دقیق
        $unitTitle = $pos->unit?->title;

        $exactMatch = $profiles->first(function ($p) use ($family, $unitTitle) {
            return $p->grade === $family && $p->unit === $unitTitle;
        });

        if ($exactMatch) {
            return $exactMatch;
        }

        // ۴. جستجو با domain (اگر unit دقیق پیدا نشد)
        $domainMatch = $profiles->first(function ($p) use ($family, $pos) {
            return $p->grade === $family && $p->domain === $pos->unit?->title;
        });

        if ($domainMatch) {
            return $domainMatch;
        }

        // ۵. Fallback: فقط بر اساس grade (بدون فیلتر unit)
        // ⚠️ این فقط برای مواردی است که واقعاً شناسنامه واحد ندارند
        return null; // به جای برگرداندن اولین رکورد، null برمی‌گردانیم
    }


    /**
     * ✅ یافتن ارزیاب در واحد سازمانی (با پیمایش به سمت بالا)
     */
    private function findEvaluator($pos, string $family, $byUnit, $units): ?array
    {
        $evaluatorFamily = self::EVALUATOR_CHAIN[$family] ?? null;
        if (!$evaluatorFamily) return null;

        $unitId = $pos->organizational_unit_id;

        // ✅ فقط در همان واحد سازمانی جستجو کن (نه در والد)
        $found = ($byUnit[$unitId] ?? collect())
            ->where('user_id', '!=', $pos->user_id)
            ->first(function ($p) use ($evaluatorFamily) {
                return $this->classifier->classify($p->post_title) === $evaluatorFamily;
            });

        if ($found) {
            return ['id' => $found->user_id, 'name' => $found->user?->name];
        }

        return null;
    }

    /**
     * ساخت ردیف خروجی
     */
    private function row($pos, ?string $family, ?AssessmentPost $profile, ?array $evaluator, string $status): array
    {
        return [
            'user_id' => $pos->user_id,
            'name' => $pos->user?->name,
            'personnel_code' => $pos->personnel_code,
            'unit' => $pos->unit?->title,
            'post_title' => $pos->post_title,
            'family' => $family,
            'post_id' => $profile?->id,
            'profile_title' => $profile?->title,
            'evaluator_id' => $evaluator['id'] ?? null,
            'evaluator_name' => $evaluator['name'] ?? null,
            'status' => $status,
        ];
    }
}
