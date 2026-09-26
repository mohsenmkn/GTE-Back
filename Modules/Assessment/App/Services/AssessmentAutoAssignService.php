<?php
namespace Modules\Assessment\App\Services;

use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Models\AssessmentPeriod;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Models\OrganizationalUnit;

class AssessmentAutoAssignService
{
    /**
     * زنجیره ارزیابی: هر خانواده توسط چه خانواده‌ای ارزیابی می‌شود
     */
    private const EVALUATOR_CHAIN = [
        'معاون' => 'مدیر',
        'مدیر' => 'معاون',
        'رئیس' => 'مدیر',
        'سرپرست/کارشناس ارشد' => 'رئیس',
        'کارشناس' => 'سرپرست/کارشناس ارشد',
        'کاردان/تکنسین/مسئول' => 'سرپرست/کارشناس ارشد',
        'راننده/اپراتور' => 'سرپرست/کارشناس ارشد',
        'متصدی' => 'سرپرست/کارشناس ارشد',
        'کارگر' => 'سرپرست/کارشناس ارشد',
    ];

    public function __construct(
        private JobFamilyClassifier $classifier
    ) {
    }

    /**
     * تخصیص خودکار ارزیاب برای یک دوره ارزیابی
     */
    public function autoAssign(AssessmentPeriod $period): array
    {
        $result = [
            'assigned' => 0,
            'unassigned' => 0,
            'skipped' => 0,
        ];

        // ارزیابی‌های بدون ارزیاب در این دوره
        $assessments = Assessment::where('period_id', $period->id)
            ->whereNull('evaluator_user_id')
            ->get();

        foreach ($assessments as $assessment) {
            $position = EmployeePosition::where('user_id', $assessment->employee_user_id)->first();

            if (!$position) {
                $result['skipped']++;
                continue;
            }

            $family = $this->classifier->classify($position->post_title);
            $evaluatorFamily = self::EVALUATOR_CHAIN[$family] ?? null;

            if (!$evaluatorFamily) {
                $result['unassigned']++;
                continue;
            }

            // ✅ یافتن ارزیاب در واحد سازمانی کارمند (با پیمایش به سمت بالا)
            $evaluator = $this->findEvaluatorInUnit($position, $evaluatorFamily);

            if ($evaluator) {
                $assessment->update(['evaluator_user_id' => $evaluator->user_id]);
                $result['assigned']++;
            } else {
                $result['unassigned']++;
            }
        }

        return $result;
    }

    /**
     * ✅ یافتن ارزیاب در واحد سازمانی (یا واحدهای والد)
     *
     * منطق:
     * 1. شروع از واحد سازمانی کارمند
     * 2. جستجو برای ارزیاب با خانواده شغلی مورد نظر در همان واحد
     * 3. اگر پیدا نشد → رفتن به واحد والد (parent_id)
     * 4. تکرار تا رسیدن به ریشه یا یافتن ارزیاب
     */
    private function findEvaluatorInUnit(EmployeePosition $position, string $evaluatorFamily): ?EmployeePosition
    {
        $unitId = $position->organizational_unit_id;

        // پیمایش در سلسله‌مراتب واحدها
        while ($unitId) {
            // یافتن تمام پست‌ها در این واحد
            $candidates = EmployeePosition::where('organizational_unit_id', $unitId)
                ->where('user_id', '!=', $position->user_id)
                ->get();

            // فیلتر بر اساس خانواده شغلی مورد نظر
            $evaluator = $candidates->first(function ($p) use ($evaluatorFamily) {
                return $this->classifier->classify($p->post_title) === $evaluatorFamily;
            });

            if ($evaluator) {
                return $evaluator;
            }

            // رفتن به واحد والد
            $unit = OrganizationalUnit::find($unitId);
            $unitId = $unit?->parent_id;
        }

        return null;
    }

    /**
     * پیش‌نمایش تخصیص (برای نمایش در UI)
     */
    public function getPreview(int $cycleId): array
    {
        $positions = EmployeePosition::with('user')->get();
        $rows = [];

        foreach ($positions as $position) {
            $family = $this->classifier->classify($position->post_title);
            $evaluatorFamily = self::EVALUATOR_CHAIN[$family] ?? null;

            $evaluator = $evaluatorFamily
                ? $this->findEvaluatorInUnit($position, $evaluatorFamily)
                : null;

            $post = $family
                ? AssessmentPost::where('grade', $family)->where('is_active', true)->first()
                : null;

            // بررسی اینکه آیا قبلاً ارزیابی شده
            $exists = Assessment::where('cycle_id', $cycleId)
                ->where('employee_user_id', $position->user_id)
                ->exists();

            $rows[] = [
                'personnel_code' => $position->user->personnel_code ?? null,
                'name' => $position->user->name ?? null,
                'unit' => $position->organizationalUnit?->title ?? null,
                'post_title' => $position->post_title,
                'family' => $family,
                'profile_title' => $post?->title,
                'post_id' => $post?->id,
                'evaluator_name' => $evaluator?->user->name ?? null,
                'evaluator_id' => $evaluator?->user_id,
                'status' => $exists ? 'exists' : ($post && $evaluator ? 'ready' : 'no_profile'),
            ];
        }

        // محاسبه خلاصه
        $summary = [
            'total' => count($rows),
            'ready' => collect($rows)->where('status', 'ready')->count(),
            'exists' => collect($rows)->where('status', 'exists')->count(),
            'no_profile' => collect($rows)->where('status', 'no_profile')->count(),
            'no_evaluator' => collect($rows)->where('evaluator_id', null)->where('status', '!=', 'exists')->count(),
            'out_of_scope' => collect($rows)->where('family', null)->count(),
        ];

        return [
            'rows' => $rows,
            'summary' => $summary,
        ];
    }
}
