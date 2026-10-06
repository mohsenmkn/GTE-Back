<?php

namespace Modules\Assessment\App\Services;

use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Models\AssessmentPeriod;

class AssessmentAutoAssignService
{
    public function __construct(private AssessmentAssignmentService $assignments)
    {
    }

    public function autoAssign(AssessmentPeriod $period): array
    {
        $plan = $this->assignments->plan(periodId: $period->id);
        $rows = collect($plan['rows'])->keyBy('user_id');
        $result = ['assigned' => 0, 'unassigned' => 0, 'skipped' => 0];

        foreach (Assessment::where('period_id', $period->id)->whereNull('evaluator_user_id')->get() as $assessment) {
            if (!in_array($assessment->status, [Assessment::STATUS_DRAFT, Assessment::STATUS_REJECTED], true)) {
                $result['skipped']++;
                continue;
            }
            $row = $rows->get($assessment->employee_user_id);
            if (!$row) {
                $result['skipped']++;
            } elseif (!$row['evaluator_id']) {
                $result['unassigned']++;
            } else {
                $updated = Assessment::whereKey($assessment->id)->whereNull('evaluator_user_id')
                    ->whereIn('status', [Assessment::STATUS_DRAFT, Assessment::STATUS_REJECTED])
                    ->update(['evaluator_user_id' => $row['evaluator_id']]);
                $result[$updated ? 'assigned' : 'skipped']++;
            }
        }

        return $result;
    }

    public function getPreview(int $cycleId): array
    {
        return $this->assignments->plan($cycleId);
    }
}
