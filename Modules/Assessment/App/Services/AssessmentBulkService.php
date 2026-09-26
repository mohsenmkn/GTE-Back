<?php
namespace Modules\Assessment\App\Services;

use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Models\AssessmentPeriod;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\HR\App\Models\EmployeePosition;

class AssessmentBulkService
{
    public function __construct(
        private JobFamilyClassifier $classifier
    ) {
    }

    /**
     * تولید انبوه ارزیابی برای یک دوره
     */
    public function generateForPeriod(AssessmentPeriod $period): array
    {
        $result = ['created' => 0, 'skipped' => 0];

        // دریافت تمام پوزیشن‌های فعال
        $positions = EmployeePosition::with(['user', 'unit'])
            ->whereNotNull('post_title')
            ->whereNotNull('organizational_unit_id')
            ->whereHas('user')
            ->get();

        foreach ($positions as $position) {
            $family = $this->classifier->classify($position->post_title);
            if (!$family) {
                $result['skipped']++;
                continue;
            }

            // یافتن شناسنامه شایستگی
            $post = AssessmentPost::findByGradeAndUnit($family, $position->unit?->title);
            if (!$post) {
                $result['skipped']++;
                continue;
            }

            // بررسی اینکه آیا قبلاً ایجاد شده
            $exists = Assessment::where('period_id', $period->id)
                ->where('employee_user_id', $position->user_id)
                ->exists();

            if ($exists) {
                $result['skipped']++;
                continue;
            }

            Assessment::create([
                'period_id' => $period->id,
                'employee_user_id' => $position->user_id,
                'post_id' => $post->id,
                'status' => Assessment::STATUS_DRAFT,
            ]);

            $result['created']++;
        }

        return $result;
    }
}
