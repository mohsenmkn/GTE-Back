<?php
namespace Modules\Assessment\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Services\AssessmentAssignmentService;

class AssessmentAssignmentController extends Controller
{
    public function __construct(private AssessmentAssignmentService $assignments)
    {
    }

    /**
     * GET /assessment/auto-assign/preview
     * پیش‌نمایش تخصیص خودکار
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate(['cycle_id' => 'required|integer|exists:assessment_cycles,id']);
        $cycleId = (int) $validated['cycle_id'];

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

    private function buildPlan(int $cycleId): array
    {
        return $this->assignments->plan($cycleId);
    }
}
