<?php

namespace Modules\Assessment\App\Services;

use Illuminate\Support\Facades\DB;
use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Models\AssessmentPeriod;

class AssessmentBulkService
{
    public function __construct(private AssessmentAssignmentService $assignments)
    {
    }

    public function generateForPeriod(AssessmentPeriod $period): array
    {
        $plan = $this->assignments->plan(periodId: $period->id);

        return DB::transaction(function () use ($period, $plan) {
            $result = ['created' => 0, 'skipped' => 0];
            foreach ($plan['rows'] as $row) {
                if ($row['status'] !== 'ready') {
                    $result['skipped']++;
                    continue;
                }
                $assessment = Assessment::firstOrCreate(
                    ['period_id' => $period->id, 'employee_user_id' => $row['user_id']],
                    [
                        'post_id' => $row['post_id'],
                        'evaluator_user_id' => $row['evaluator_id'],
                        'status' => Assessment::STATUS_DRAFT,
                    ]
                );
                $result[$assessment->wasRecentlyCreated ? 'created' : 'skipped']++;
            }

            return $result;
        });
    }
}
