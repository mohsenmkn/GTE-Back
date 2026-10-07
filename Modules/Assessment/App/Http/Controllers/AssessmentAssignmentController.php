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
        $validated = $request->validate([
            'cycle_id' => 'required|integer|exists:assessment_cycles,id',
        ]);

        $cycleId = (int) $validated['cycle_id'];
        $plan = $this->buildPlan($cycleId);

        return response()->json([
            'rows'    => $plan['rows'],
            'summary' => $plan['summary'],
        ]);
    }

    /**
     * POST /assessment/auto-assign/execute
     * اجرای تخصیص خودکار (بهینه‌شده با Bulk Insert)
     */
    public function execute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cycle_id' => 'required|integer|exists:assessment_cycles,id',
        ]);

        $cycleId = (int) $validated['cycle_id'];
        $plan    = $this->buildPlan($cycleId);

        // ── ۱. فقط ردیف‌های آماده ──
        $readyRows = collect($plan['rows'])
            ->filter(fn($row) => $row['status'] === 'ready');

        if ($readyRows->isEmpty()) {
            return response()->json([
                'message' => 'هیچ ردیف آماده‌ای برای تخصیص وجود ندارد.',
                'created' => 0,
                'skipped' => count($plan['rows']),
            ]);
        }

        // ── ۲. گرفتن user_id هایی که قبلاً در این چرخه ارزیابی دارند (۱ کوئری) ──
        $existingUserIds = Assessment::where('cycle_id', $cycleId)
            ->pluck('employee_user_id')
            ->toArray();

        // ── ۳. حذف تکراری‌ها و ساخت آرایه برای Bulk Insert ──
        $now = now();
        $toInsert = $readyRows
            ->filter(fn($row) => !in_array($row['user_id'], $existingUserIds))
            ->map(fn($row) => [
                'cycle_id'          => $cycleId,
                'employee_user_id'  => $row['user_id'],
                'post_id'           => $row['post_id'],
                'evaluator_user_id' => $row['evaluator_id'],
                'status'            => 'draft',
                'created_at'        => $now,
                'updated_at'        => $now,
            ])
            ->values()
            ->toArray();

        // ── ۴. درج یکجا درون ترنزکشن ──
        $created = 0;

        DB::transaction(function () use ($toInsert, &$created) {
            if (!empty($toInsert)) {
                // اگر تعداد زیاد است، chunk می‌کنیم (هر ۵۰۰ تا یک INSERT)
                foreach (array_chunk($toInsert, 500) as $chunk) {
                    Assessment::insert($chunk);
                }
                $created = count($toInsert);
            }
        });

        $skipped = count($plan['rows']) - $created;

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
