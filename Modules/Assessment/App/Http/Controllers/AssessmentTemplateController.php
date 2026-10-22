<?php


namespace Modules\Assessment\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Assessment\App\Models\AssessmentQuestionTemplate;
use Modules\Assessment\App\Models\AssessmentTemplateQuestion;
use Modules\Assessment\App\Services\AssessmentTemplateService;
use Modules\Assessment\App\Services\JobFamilyClassifier;

class AssessmentTemplateController extends Controller
{
    public function __construct(
        private AssessmentTemplateService $service,
        private JobFamilyClassifier       $classifier
    )
    {
    }

    /* ──────────────────────────────── Template CRUD ─────────────────────────────── */

    /**
     * GET /assessment/templates
     * لیست تمپلیت‌ها با فیلتر
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('assessment.template_view') && !$request->user()->can('assessment.manage')) {
            return response()->json(['message' => 'دسترسی مجاز نیست.'], 403);
        }

        $filters = [
            'grade' => $request->query('grade'),
            'is_active' => $request->has('is_active') ? (bool)$request->query('is_active') : null,
            'approved' => $request->has('approved') ? (bool)$request->query('approved') : null,
        ];

        $templates = $this->service->getTemplates(array_filter($filters, fn($v) => !is_null($v)));

        return response()->json([
            'templates' => $templates,
            'families' => JobFamilyClassifier::FAMILIES,
        ]);
    }

    /**
     * POST /assessment/templates
     * ایجاد تمپلیت جدید
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->can('assessment.template_manage')) {
            return response()->json(['message' => 'فقط مدیر HR مجاز به ایجاد تمپلیت است.'], 403);
        }

        try {
            $template = $this->service->createTemplate(
                $request->all(),
                $request->user()->id
            );

            return response()->json([
                'message' => 'تمپلیت با موفقیت ایجاد شد',
                'template' => $template->load('creator'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'خطای اعتبارسنجی',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در ایجاد تمپلیت: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /assessment/templates/{template}
     * نمایش جزئیات تمپلیت + سوالات
     */
    public function show(AssessmentQuestionTemplate $template): JsonResponse
    {
        $user = request()->user();
        if (!$user->can('assessment.template_view') && !$user->can('assessment.manage')) {
            return response()->json(['message' => 'دسترسی مجاز نیست.'], 403);
        }

        $template->load([
            'questions.category',
            'creator',
            'approver',
            'posts' => fn($q) => $q->select('id', 'title', 'grade', 'unit', 'template_version_used'),
        ]);

        // آمار سوالات بر اساس دسته‌بندی
        $stats = [
            'total_questions' => $template->questions->count(),
            'active_questions' => $template->questions->where('is_active', true)->count(),
            'categories_count' => $template->questions->pluck('category_id')->unique()->count(),
            'posts_using' => $template->posts->count(),
        ];

        // گروه‌بندی سوالات بر اساس دسته‌بندی
        $categories = $template->questions
            ->groupBy('category_id')
            ->map(function ($group) {
                return [
                    'id' => $group->first()->category?->id,
                    'title' => $group->first()->category?->title ?? 'سایر',
                    'questions' => $group->sortBy('sort_order')->values(),
                ];
            })
            ->values();

        return response()->json([
            'template' => $template,
            'stats' => $stats,
            'categories' => $categories,
        ]);
    }

    /**
     * PUT /assessment/templates/{template}
     * به‌روزرسانی تمپلیت
     */
    public function update(Request $request, AssessmentQuestionTemplate $template): JsonResponse
    {
        if (!$request->user()->can('assessment.template_manage')) {
            return response()->json(['message' => 'فقط مدیر HR مجاز به ویرایش تمپلیت است.'], 403);
        }

        try {
            $updated = $this->service->updateTemplate($template->id, $request->all());

            return response()->json([
                'message' => 'تمپلیت با موفقیت به‌روزرسانی شد',
                'template' => $updated,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در به‌روزرسانی: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /assessment/templates/{template}
     * حذف (soft delete) تمپلیت
     */
    public function destroy(Request $request, AssessmentQuestionTemplate $template): JsonResponse
    {
        if (!$request->user()->can('assessment.template_manage')) {
            return response()->json(['message' => 'فقط مدیر HR مجاز به حذف تمپلیت است.'], 403);
        }

        // بررسی استفاده در شناسنامه‌ها
        $postsCount = $template->posts()->count();
        if ($postsCount > 0) {
            return response()->json([
                'message' => "این تمپلیت در {$postsCount} شناسنامه استفاده شده و قابل حذف نیست.",
            ], 422);
        }

        $template->delete();

        return response()->json(['message' => 'تمپلیت حذف شد']);
    }

    /* ──────────────────────────────── Template Actions ─────────────────────────────── */

    /**
     * POST /assessment/templates/{template}/approve
     * تایید تمپلیت
     */
    public function approve(Request $request, AssessmentQuestionTemplate $template): JsonResponse
    {
        if (!$request->user()->can('assessment.template_manage')) {
            return response()->json(['message' => 'فقط مدیر HR مجاز به تایید تمپلیت است.'], 403);
        }

        if ($template->questions()->where('is_active', true)->count() === 0) {
            return response()->json([
                'message' => 'تمپلیت باید حداقل یک سوال فعال داشته باشد',
            ], 422);
        }

        $approved = $this->service->approveTemplate($template->id, $request->user()->id);

        return response()->json([
            'message' => 'تمپلیت تایید شد',
            'template' => $approved->load('approver'),
        ]);
    }

    /**
     * POST /assessment/templates/{template}/increment-version
     * ایجاد نسخه جدید از تمپلیت
     */
    public function incrementVersion(Request $request, AssessmentQuestionTemplate $template): JsonResponse
    {
        if (!$request->user()->can('assessment.template_manage')) {
            return response()->json(['message' => 'فقط مدیر HR مجاز به این عمل است.'], 403);
        }

        try {
            $newTemplate = $this->service->incrementVersion($template->id, $request->user()->id);

            return response()->json([
                'message' => "نسخه جدید (v{$newTemplate->version}) ایجاد شد",
                'template' => $newTemplate->load('creator'),
                'old_version' => $template->version,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در ایجاد نسخه جدید: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ──────────────────────────────── Template Questions ─────────────────────────────── */

    /**
     * POST /assessment/templates/{template}/questions
     * افزودن سوال به تمپلیت
     */
    public function storeQuestion(Request $request, AssessmentQuestionTemplate $template): JsonResponse
    {
        if (!$request->user()->can('assessment.template_manage')) {
            return response()->json(['message' => 'فقط مدیر HR مجاز به افزودن سوال است.'], 403);
        }

        try {
            $question = $this->service->addQuestion($template->id, $request->all());

            return response()->json([
                'message' => 'سوال با موفقیت اضافه شد',
                'question' => $question->load('category'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'خطای اعتبارسنجی',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در افزودن سوال: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /assessment/templates/questions/{question}
     * ویرایش سوال تمپلیت
     */
    public function updateQuestion(Request $request, AssessmentTemplateQuestion $question): JsonResponse
    {
        if (!$request->user()->can('assessment.template_manage')) {
            return response()->json(['message' => 'فقط مدیر HR مجاز به ویرایش سوال است.'], 403);
        }

        try {
            $updated = $this->service->updateQuestion($question->id, $request->all());

            return response()->json([
                'message' => 'سوال با موفقیت ویرایش شد',
                'question' => $updated->load('category'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در ویرایش سوال: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /assessment/templates/questions/{question}
     * حذف سوال از تمپلیت
     */
    public function destroyQuestion(Request $request, AssessmentTemplateQuestion $question): JsonResponse
    {
        if (!$request->user()->can('assessment.template_manage')) {
            return response()->json(['message' => 'فقط مدیر HR مجاز به حذف سوال است.'], 403);
        }

        $this->service->removeQuestion($question->id);

        return response()->json(['message' => 'سوال حذف شد']);
    }

    /**
     * PUT /assessment/templates/{template}/questions/reorder
     * تغییر ترتیب سوالات (bulk)
     */
    public function reorderQuestions(Request $request, AssessmentQuestionTemplate $template): JsonResponse
    {
        if (!$request->user()->can('assessment.template_manage')) {
            return response()->json(['message' => 'دسترسی مجاز نیست.'], 403);
        }

        $validated = $request->validate([
            'orders' => 'required|array',
            'orders.*' => 'integer|min:0',
        ]);

        $updated = $this->service->reorderQuestions($template->id, $validated['orders']);

        return response()->json([
            'message' => 'ترتیب سوالات به‌روزرسانی شد',
            'updated' => $updated,
        ]);
    }

    /* ──────────────────────────────── Import/Sync ─────────────────────────────── */

    /**
     * POST /assessment/templates/{template}/import-to-post
     * import سوالات تمپلیت به یک شناسنامه
     */
    public function importToPost(Request $request, AssessmentQuestionTemplate $template): JsonResponse
    {
        if (!$request->user()->can('assessment.manage')) {
            return response()->json(['message' => 'دسترسی مجاز نیست.'], 403);
        }

        $validated = $request->validate([
            'post_id' => 'required|exists:assessment_posts,id',
        ]);

        try {
            $result = $this->service->importToPost($template->id, (int)$validated['post_id']);

            return response()->json([
                'message' => "import انجام شد: {$result['imported']} سوال اضافه، {$result['skipped']} سوال تکراری رد شد",
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در import: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /assessment/posts/{post}/sync-template
     * sync شناسنامه با آخرین نسخه تمپلیت
     */
    public function syncPostWithTemplate(Request $request, \Modules\Assessment\App\Models\AssessmentPost $post): JsonResponse
    {
        if (!$request->user()->can('assessment.manage')) {
            return response()->json(['message' => 'دسترسی مجاز نیست.'], 403);
        }

        try {
            $result = $this->service->syncPostWithTemplate($post->id);

            return response()->json([
                'message' => $result['message'] ?? "sync انجام شد: {$result['added']} سوال جدید، {$result['updated']} سوال به‌روزرسانی شد",
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در sync: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ──────────────────────────────── Helpers ─────────────────────────────── */

    /**
     * GET /assessment/templates/latest-approved
     * دریافت آخرین نسخه تایید شده برای یک grade
     */
    public function latestApproved(Request $request): JsonResponse
    {
        $grade = $request->query('grade');
        if (!$grade) {
            return response()->json(['message' => 'پارامتر grade الزامی است'], 422);
        }

        $template = $this->service->getLatestApprovedTemplate($grade);

        return response()->json([
            'template' => $template?->load('questions.category'),
        ]);
    }
}
