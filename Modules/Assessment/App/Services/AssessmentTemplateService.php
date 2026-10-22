<?php


namespace Modules\Assessment\App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Assessment\App\Models\AssessmentCategory;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\Assessment\App\Models\AssessmentQuestion;
use Modules\Assessment\App\Models\AssessmentQuestionTemplate;
use Modules\Assessment\App\Models\AssessmentTemplateQuestion;

class AssessmentTemplateService
{
    /* ──────────────────────────────── Template Management ─────────────────────────────── */

    /**
     * ایجاد تمپلیت جدید
     */
    public function createTemplate(array $data, int $createdBy): AssessmentQuestionTemplate
    {
        $validated = validator($data, [
            'title' => 'required|string|max:255',
            'grade' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
        ])->validate();

        // بررسی تکراری نبودن title + version
        $existingVersion = AssessmentQuestionTemplate::where('title', $validated['title'])
            ->max('version') ?? 0;

        return AssessmentQuestionTemplate::create([
            'title' => $validated['title'],
            'grade' => $validated['grade'],
            'version' => $existingVersion + 1,
            'description' => $validated['description'] ?? null,
            'is_active' => true,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * به‌روزرسانی تمپلیت
     */
    public function updateTemplate(int $templateId, array $data): AssessmentQuestionTemplate
    {
        $template = AssessmentQuestionTemplate::findOrFail($templateId);

        $validated = validator($data, [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ])->validate();

        $template->update($validated);

        return $template->fresh();
    }

    /**
     * تایید تمپلیت
     */
    public function approveTemplate(int $templateId, int $approvedBy): AssessmentQuestionTemplate
    {
        $template = AssessmentQuestionTemplate::findOrFail($templateId);

        $template->update([
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        return $template->fresh();
    }

    /**
     * افزایش نسخه تمپلیت (ایجاد نسخه جدید)
     */
    public function incrementVersion(int $templateId, int $createdBy): AssessmentQuestionTemplate
    {
        $oldTemplate = AssessmentQuestionTemplate::findOrFail($templateId);

        // ایجاد نسخه جدید با همان اطلاعات
        $newTemplate = $this->createTemplate([
            'title' => $oldTemplate->title,
            'grade' => $oldTemplate->grade,
            'description' => $oldTemplate->description,
        ], $createdBy);

        // کپی سوالات از نسخه قبلی
        foreach ($oldTemplate->questions as $question) {
            $this->addQuestion($newTemplate->id, [
                'category_id' => $question->category_id,
                'title' => $question->title,
                'risk_level' => $question->risk_level,
                'required_score' => $question->required_score,
                'fix_deadline' => $question->fix_deadline,
                'sort_order' => $question->sort_order,
            ]);
        }

        // غیرفعال کردن نسخه قبلی
        $oldTemplate->update(['is_active' => false]);

        return $newTemplate;
    }

    /* ──────────────────────────────── Template Questions ─────────────────────────────── */

    /**
     * افزودن سوال به تمپلیت
     */
    public function addQuestion(int $templateId, array $data): AssessmentTemplateQuestion
    {
        $validated = validator($data, [
            'category_id' => 'required|exists:assessment_categories,id',
            'title' => 'required|string|max:1000',
            'risk_level' => 'nullable|integer|min:1|max:5',
            'required_score' => 'nullable|integer|min:1|max:5',
            'fix_deadline' => 'nullable|in:immediate,short_term,mid_term,long_term',
            'sort_order' => 'nullable|integer',
        ])->validate();

        // محاسبه sort_order اگر ارائه نشده
        $maxSortOrder = AssessmentTemplateQuestion::where('template_id', $templateId)
            ->max('sort_order') ?? 0;

        return AssessmentTemplateQuestion::create([
            'template_id' => $templateId,
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'risk_level' => $validated['risk_level'] ?? 3,
            'required_score' => $validated['required_score'] ?? 3,
            'fix_deadline' => $validated['fix_deadline'] ?? 'mid_term',
            'sort_order' => $validated['sort_order'] ?? ($maxSortOrder + 1),
            'is_active' => true,
        ]);
    }

    /**
     * به‌روزرسانی سوال تمپلیت
     */
    public function updateQuestion(int $questionId, array $data): AssessmentTemplateQuestion
    {
        $question = AssessmentTemplateQuestion::findOrFail($questionId);

        $validated = validator($data, [
            'category_id' => 'sometimes|required|exists:assessment_categories,id',
            'title' => 'sometimes|required|string|max:1000',
            'risk_level' => 'nullable|integer|min:1|max:5',
            'required_score' => 'nullable|integer|min:1|max:5',
            'fix_deadline' => 'nullable|in:immediate,short_term,mid_term,long_term',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ])->validate();

        $question->update($validated);

        return $question->fresh();
    }

    /**
     * حذف سوال از تمپلیت
     */
    public function removeQuestion(int $questionId): bool
    {
        $question = AssessmentTemplateQuestion::findOrFail($questionId);
        return $question->delete();
    }

    /**
     * تغییر ترتیب سوالات (bulk update)
     */
    public function reorderQuestions(int $templateId, array $orders): int
    {
        $updated = 0;
        foreach ($orders as $questionId => $sortOrder) {
            $affected = AssessmentTemplateQuestion::where('id', $questionId)
                ->where('template_id', $templateId)
                ->update(['sort_order' => $sortOrder]);
            $updated += $affected;
        }
        return $updated;
    }

    /* ──────────────────────────────── Import to Post ─────────────────────────────── */

    /**
     * import سوالات تمپلیت به یک شناسنامه
     */
    public function importToPost(int $templateId, int $postId): array
    {
        $template = AssessmentQuestionTemplate::findOrFail($templateId);
        $post = AssessmentPost::findOrFail($postId);

        return DB::transaction(function () use ($template, $post) {
            $imported = 0;
            $skipped = 0;

            // دریافت سوالات فعال تمپلیت
            $templateQuestions = $template->activeQuestions()->with('category')->get();

            foreach ($templateQuestions as $templateQuestion) {
                // بررسی تکراری نبودن (بر اساس title + category)
                $exists = AssessmentQuestion::where('post_id', $post->id)
                    ->where('category_id', $templateQuestion->category_id)
                    ->where('title', $templateQuestion->title)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // ایجاد سوال جدید
                AssessmentQuestion::create([
                    'post_id' => $post->id,
                    'category_id' => $templateQuestion->category_id,
                    'title' => $templateQuestion->title,
                    'risk_level' => $templateQuestion->risk_level,
                    'required_score' => $templateQuestion->required_score,
                    'fix_deadline' => $templateQuestion->fix_deadline,
                    'sort_order' => $templateQuestion->sort_order,
                    'is_active' => true,
                ]);

                $imported++;
            }

            // به‌روزرسانی اطلاعات تمپلیت در شناسنامه
            $post->update([
                'template_id' => $template->id,
                'template_version_used' => $template->version,
                'last_template_sync_at' => now(),
            ]);

            return compact('imported', 'skipped');
        });
    }

    /**
     * sync شناسنامه با آخرین نسخه تمپلیت
     */
    public function syncPostWithTemplate(int $postId): array
    {
        $post = AssessmentPost::findOrFail($postId);

        if (!$post->template_id) {
            throw new \Exception('این شناسنامه از تمپلیت ساخته نشده است');
        }

        $template = $post->template;

        // بررسی outdated بودن
        if (!$post->template_outdated) {
            return [
                'synced' => 0,
                'message' => 'شناسنامه با آخرین نسخه تمپلیت sync است',
            ];
        }

        return DB::transaction(function () use ($post, $template) {
            $added = 0;
            $updated = 0;

            // دریافت سوالات فعلی شناسنامه
            $existingQuestions = $post->questions()
                ->get()
                ->keyBy(fn($q) => $q->category_id . '|' . $q->title);

            // دریافت سوالات فعال تمپلیت
            $templateQuestions = $template->activeQuestions()->get();

            foreach ($templateQuestions as $templateQuestion) {
                $key = $templateQuestion->category_id . '|' . $templateQuestion->title;

                if ($existingQuestions->has($key)) {
                    // به‌روزرسانی سوال موجود
                    $existingQuestion = $existingQuestions->get($key);
                    $existingQuestion->update([
                        'risk_level' => $templateQuestion->risk_level,
                        'required_score' => $templateQuestion->required_score,
                        'fix_deadline' => $templateQuestion->fix_deadline,
                        'sort_order' => $templateQuestion->sort_order,
                    ]);
                    $updated++;
                } else {
                    // ایجاد سوال جدید
                    AssessmentQuestion::create([
                        'post_id' => $post->id,
                        'category_id' => $templateQuestion->category_id,
                        'title' => $templateQuestion->title,
                        'risk_level' => $templateQuestion->risk_level,
                        'required_score' => $templateQuestion->required_score,
                        'fix_deadline' => $templateQuestion->fix_deadline,
                        'sort_order' => $templateQuestion->sort_order,
                        'is_active' => true,
                    ]);
                    $added++;
                }
            }

            // به‌روزرسانی اطلاعات sync
            $post->update([
                'template_version_used' => $template->version,
                'last_template_sync_at' => now(),
            ]);

            return compact('added', 'updated');
        });
    }

    /* ──────────────────────────────── Query Helpers ─────────────────────────────── */

    /**
     * دریافت لیست تمپلیت‌ها با فیلتر
     */
    public function getTemplates(array $filters = []): Collection
    {
        $query = AssessmentQuestionTemplate::with(['questions.category', 'creator', 'approver']);

        if (isset($filters['grade'])) {
            $query->byGrade($filters['grade']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['approved'])) {
            if ($filters['approved']) {
                $query->approved();
            } else {
                $query->whereNull('approved_at');
            }
        }

        return $query->orderByDesc('version')->orderBy('title')->get();
    }

    /**
     * دریافت آخرین نسخه تایید شده برای یک grade
     */
    public function getLatestApprovedTemplate(string $grade): ?AssessmentQuestionTemplate
    {
        return AssessmentQuestionTemplate::latestApprovedForGrade($grade);
    }
}
