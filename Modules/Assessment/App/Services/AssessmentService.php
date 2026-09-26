<?php
namespace Modules\Assessment\App\Services;

use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Models\AssessmentAnswer;
use Modules\Assessment\App\Models\AssessmentGap;
use Modules\Assessment\App\Models\AssessmentQuestion;

class AssessmentService
{
    /**
     * ثبت پاسخ‌های ارزیابی
     */
    public function submitAnswers(Assessment $assessment, array $answers): void
    {
        foreach ($answers as $questionId => $data) {
            AssessmentAnswer::updateOrCreate(
                [
                    'assessment_id' => $assessment->id,
                    'question_id' => $questionId,
                ],
                [
                    'score' => $data['score'] ?? null,
                    'comment' => $data['comment'] ?? null,
                ]
            );
        }

        // محاسبه گپ‌ها
        $this->calculateGaps($assessment);

        // تغییر وضعیت به submitted
        $assessment->update(['status' => Assessment::STATUS_SUBMITTED]);
    }

    /**
     * محاسبه گپ‌ها بر اساس پاسخ‌ها
     */
    private function calculateGaps(Assessment $assessment): void
    {
        // حذف گپ‌های قبلی
        $assessment->gaps()->delete();

        $answers = $assessment->answers()->with('question')->get();

        foreach ($answers as $answer) {
            $required = $answer->question->effective_required_score ?? 5;
            $actual = $answer->score ?? 0;
            $gap = max(0, $required - $actual);

            if ($gap > 0) {
                AssessmentGap::create([
                    'assessment_id' => $assessment->id,
                    'question_id' => $answer->question_id,
                    'required_score' => $required,
                    'actual_score' => $actual,
                    'gap' => $gap,
                    'weighted_gap' => $gap * ($answer->question->risk_level ?? 1),
                    'fix_deadline' => $answer->question->fix_deadline,
                    'status' => AssessmentGap::STATUS_OPEN,
                ]);
            }
        }
    }

    /**
     * تایید ارزیابی
     */
    public function approve(Assessment $assessment, int $approvedBy): void
    {
        $assessment->update([
            'status' => Assessment::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $approvedBy,
        ]);
    }

    /**
     * رد ارزیابی
     */
    public function reject(Assessment $assessment, string $notes = ''): void
    {
        $assessment->update([
            'status' => Assessment::STATUS_REJECTED,
            'notes' => $notes,
        ]);
    }

    /**
     * دریافت فرم ارزیابی با تمام داده‌های مورد نیاز
     *
     * @param int $assessmentId
     * @return array
     */
    /**
     * دریافت فرم ارزیابی با تمام داده‌های مورد نیاز
     *
     * @param Assessment $assessment (تغییر از int به Assessment)
     * @return array
     */
    /**
     * دریافت فرم ارزیابی با سوالات مربوط به شناسنامه شغل
     *
     * @param Assessment $assessment
     * @return array
     */
    public function getForm(Assessment $assessment): array
    {
        // لود کردن روابط مورد نیاز
        $assessment->load([
            'employee',
            'evaluator',
            'post',
            'cycle',
            'answers',
        ]);

        $post = $assessment->post;

        if (!$post) {
            throw new \Exception('شناسنامه شایستگی برای این ارزیابی یافت نشد');
        }

        // ✅ دریافت سوالات فقط برای این شناسنامه (post_id)
        $questions = \Modules\Assessment\App\Models\AssessmentQuestion::where('post_id', $post->id)
            ->with('category')
            ->orderBy('category_id')
            ->orderBy('id')
            ->get();

        // ✅ گروه‌بندی سوالات بر اساس دسته‌بندی (منظر)
        $categories = $questions->groupBy('category.title')->map(function ($group, $categoryTitle) use ($assessment) {
            return [
                'id' => $group->first()->category?->id,
                'title' => $categoryTitle ?? 'سایر',
                'questions' => $group->map(function ($question) use ($assessment) {
                    // بررسی نمره قبلی (اگر ارزیابی قبلاً شروع شده)
                    $existingAnswer = $assessment->answers->firstWhere('question_id', $question->id);

                    return [
                        'id' => $question->id,
                        'title' => $question->title,
                        'required_score' => $question->required_score,
                        'risk_level' => $question->risk_level,
                        'fix_deadline' => $question->fix_deadline,
                        'score' => $existingAnswer?->score,
                        'comment' => $existingAnswer?->comment,
                    ];
                })->values(),
            ];
        })->values();

        // محاسبه آمار
        $totalQuestions = $questions->count();
        $answeredQuestions = $questions->filter(function ($q) use ($assessment) {
            return $assessment->answers->contains('question_id', $q->id);
        })->count();

        return [
            'assessment' => $assessment,
            'categories' => $categories,
            'stats' => [
                'total_questions' => $totalQuestions,
                'answered_questions' => $answeredQuestions,
                'completion' => $totalQuestions > 0 ? round(($answeredQuestions / $totalQuestions) * 100) : 0,
            ],
        ];
    }

}
