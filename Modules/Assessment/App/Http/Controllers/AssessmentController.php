<?php
namespace Modules\Assessment\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Models\AssessmentAnswer;
use Modules\Assessment\App\Models\AssessmentMethod;
use Modules\Assessment\App\Models\AssessmentPeriod;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\Assessment\App\Models\AssessmentQuestion;
use Modules\Assessment\App\Models\AssessmentCategory;
use Modules\Assessment\App\Services\AssessmentAutoAssignService;
use Modules\Assessment\App\Services\AssessmentService;

class AssessmentController extends Controller
{
    public function __construct(private AssessmentService $service) {}

    /**
     * GET /assessment/assessments
     * لیست ارزیابی‌های محول شده به کاربر فعلی
     */
    /**
     * GET /assessment/assessments
     * لیست ارزیابی‌های محول شده به کاربر فعلی
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // ✅ دریافت واحد سازمانی کاربر فعلی از جدول employee_positions
        $userPosition = \Modules\HR\App\Models\EmployeePosition::where('user_id', $user->id)->first();
        $userUnitId = $userPosition?->organizational_unit_id;

        $query = Assessment::with(['employee', 'evaluator', 'post', 'cycle'])
            ->where('evaluator_user_id', $user->id);

        // ✅ فیلتر بر اساس واحد سازمانی (اگر کاربر واحد دارد)
        if ($userUnitId) {
            // پیدا کردن user_id های پرسنل در همان واحد سازمانی
            $employeeUserIds = \Modules\HR\App\Models\EmployeePosition::where('organizational_unit_id', $userUnitId)
                ->pluck('user_id');

            // فیلتر ارزیابی‌ها بر اساس employee_user_id
            $query->whereIn('employee_user_id', $employeeUserIds);
        }

        // فیلترهای اختیاری
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('cycle_id')) {
            $query->where('cycle_id', $request->cycle_id);
        }

        $assessments = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'assessments' => $assessments,
        ]);
    }

    /**
     * ایجاد ارزیابی تکی
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_user_id'  => 'required|exists:users,id',
            'post_id'           => 'required|exists:assessment_posts,id',
            'evaluator_user_id' => 'required|exists:users,id',
            'cycle_id'          => 'nullable|exists:assessment_cycles,id',
        ]);

        $assessment = $this->service->createAssessment(
            $validated['employee_user_id'],
            $validated['post_id'],
            $validated['evaluator_user_id'],
            $validated['cycle_id'] ?? null
        );

        return response()->json(['assessment' => $assessment], 201);
    }

    /**
     * ایجاد گروهی
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'post_id'           => 'required|exists:assessment_posts,id',
            'evaluator_user_id' => 'required|exists:users,id',
            'cycle_id'          => 'nullable|exists:assessment_cycles,id',
            'employee_user_ids' => 'required|array|min:1',
            'employee_user_ids.*' => 'exists:users,id',
        ]);

        $result = $this->service->bulkCreate(
            $validated['post_id'],
            $validated['evaluator_user_id'],
            $validated['cycle_id'] ?? null,
            $validated['employee_user_ids']
        );

        return response()->json($result);
    }

    /**
     * نمایش ارزیابی + فرم سوالات
     */
    public function show(Assessment $assessment): JsonResponse
    {
        $user = request()->user();
        $isManager   = $user->can('assessment.manage');
        $isEvaluator = $assessment->evaluator_user_id === $user->id;
        $isEmployee  = $assessment->employee_user_id === $user->id
            && $assessment->status === Assessment::STATUS_APPROVED;

        if (!$isManager && !$isEvaluator && !$isEmployee) {
            return response()->json(['message' => 'دسترسی به این ارزیابی ندارید.'], 403);
        }

        $assessment->load(['employee', 'evaluator', 'post', 'cycle']);

        $data = $this->service->getForm($assessment);

        return response()->json($data);
    }

    /**
     * ثبت نمرات + محاسبه گپ
     */
//    public function submit(Request $request, Assessment $assessment): JsonResponse
//    {
//        $user = $request->user();
//
//        // 🔐 فقط ارزیابِ خودِ رکورد یا مدیر کل
//        if (!$user->can('assessment.manage') && $assessment->evaluator_user_id !== $user->id) {
//            return response()->json(['message' => 'فقط ارزیابِ تعیین‌شده می‌تواند این ارزیابی را ثبت کند.'], 403);
//        }
//
//        // 🔒 فقط در حالت پیش‌نویس یا برگشت‌خورده قابل ویرایش
//        if (!in_array($assessment->status, [Assessment::STATUS_DRAFT, Assessment::STATUS_REJECTED])) {
//            return response()->json(['message' => 'این ارزیابی قابل ویرایش نیست.'], 422);
//        }
//
//        $request->validate([
//            'scores'   => 'required|array',
//            'scores.*' => 'integer|between:1,5',
//        ]);
//
//        $assessment = $this->service->submitAnswers($assessment, $request->scores);
//
//        return response()->json([
//            'message'    => 'ارزیابی ثبت شد و وضعیت به «تکمیل شده» تغییر کرد.',
//            'assessment' => $assessment,
//        ]);
//    }


    /**
     * POST /assessment/assessments/{id}/submit
     * ثبت نمرات ارزیابی
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'scores' => 'required|array',
            'scores.*' => 'nullable|integer|min:1|max:5',
        ]);

        $assessment = Assessment::findOrFail($id);

        // بررسی دسترسی
        if ($assessment->evaluator_user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'شما مجاز به ثبت نمرات این ارزیابی نیستید',
            ], 403);
        }

        // ذخیره نمرات
        foreach ($request->scores as $questionId => $score) {
            AssessmentAnswer::updateOrCreate(
                [
                    'assessment_id' => $assessment->id,
                    'question_id' => $questionId,
                ],
                [
                    'score' => $score,
                ]
            );
        }

        // به‌روزرسانی وضعیت
        $assessment->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return response()->json([
            'message' => 'نمرات با موفقیت ثبت شد',
            'assessment' => $assessment->fresh(),
        ]);
    }

    public function approve(Assessment $assessment): JsonResponse
    {
        return response()->json(['assessment' => $this->service->approve($assessment, request()->user())]);
    }

    public function reject(Request $request, Assessment $assessment): JsonResponse
    {
        return response()->json(['assessment' => $this->service->reject($assessment, request()->user(), $request->notes)]);
    }

    /**
     * گپ‌های یک ارزیابی + اقدامات
     */
    public function gaps(Assessment $assessment): JsonResponse
    {
        $gaps = $assessment->gaps()
            ->with(['question.category', 'actions.method'])
            ->orderByDesc('weighted_gap')
            ->get()
            ->map(fn($g) => [
                'id'             => $g->id,
                'question'       => $g->question?->title,
                'category'       => $g->question?->category?->title,
                'required_score' => $g->required_score,
                'actual_score'   => $g->actual_score,
                'gap'            => $g->gap,
                'weighted_gap'   => $g->weighted_gap,
                'severity'       => $g->severity_label,
                'requires_action'=> $g->requires_action,
                'fix_deadline'   => $g->fix_deadline,
                'status'         => $g->status,
                'actions'        => $g->actions,
            ]);

        return response()->json(['gaps' => $gaps]);
    }

    /**
     * ایجاد اقدام اصلاحی برای یک گپ
     */
    public function storeAction(Request $request, Assessment $assessment): JsonResponse
    {
        $validated = $request->validate([
            'gap_id'     => 'required|exists:assessment_gaps,id',
            'method_id'  => 'nullable|exists:assessment_methods,id',
            'title'      => 'required|string|max:500',
            'due_date'   => 'nullable|date',
            'description'=> 'nullable|string',
        ]);

        $gap = $assessment->gaps()->where('id', $validated['gap_id'])->firstOrFail();

        $action = $gap->actions()->create([
            'method_id'   => $validated['method_id'] ?? null,
            'title'       => $validated['title'],
            'due_date'    => $validated['due_date'] ?? null,
            'description' => $validated['description'] ?? null,
            'status'      => 'planned',
        ]);

        $gap->update(['status' => 'actioned']);

        return response()->json(['action' => $action], 201);
    }

    // ────────────── کاتالوگ‌ها برای UI ──────────────

    public function posts(Request $request): JsonResponse
    {
        $query = AssessmentPost::where('is_active', true)
            ->withCount([
                'questions as total_questions',
                'questions as risk_done' => fn($q) => $q->whereNotNull('risk_level'),
            ]);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('title', 'like', "%{$s}%")
                ->orWhere('unit', 'like', "%{$s}%")
                ->orWhere('grade', 'like', "%{$s}%"));
        }

        if ($request->filled('grade')) {
            $query->where('grade', $request->grade);
        }

        return response()->json([
            'posts' => $query->orderBy('grade')->orderBy('unit')->get(),
        ]);
    }

    /**
     * GET /assessment/methods?all=1
     */
    public function methods(Request $request): JsonResponse
    {
        $query = AssessmentMethod::query();

        // حالت مدیریتی: همه (فعال + غیرفعال)
        if (!$request->boolean('all')) {
            $query->where('is_active', true);
        }

        return response()->json(['methods' => $query->orderBy('title')->get()]);
    }

    public function users(Request $request): JsonResponse
    {
        $q = $request->search;
        $users = \Modules\Auth\App\Models\User::query()
            ->when($q, fn($qq) => $qq
                ->where('name', 'like', "%{$q}%")
                ->orWhere('personnel_code', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'personnel_code']);

        return response()->json(['users' => $users]);
    }

    /**
     * GET /assessment/posts/{post}/questions
     * سوالات شناسنامه به تفکیک منظر + آمار
     */
    /**
     * GET /assessment/posts/{post}/questions
     * سوالات شناسنامه به تفکیک منظر + آمار
     */
    public function postQuestions(AssessmentPost $post): JsonResponse
    {
        $questions = AssessmentQuestion::where('post_id', $post->id)
            ->with('category')
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $total = $questions->count();
        $done  = $questions->whereNotNull('risk_level')->count();

        // ✅ شمارش مناظر یکتا
        $uniqueCategoriesCount = $questions->pluck('category_id')->unique()->count();

        return response()->json([
            'post' => $post,
            'stats' => [
                'total'               => $total,
                'risk_done'           => $done,
                'categories_count'    => $uniqueCategoriesCount, // ✅ اضافه شد
                'completion'          => $total ? round(($done / $total) * 100) : 0,
            ],
            'categories' => $questions->groupBy('category.title')->map(fn($g) => [
                'title'     => $g->first()->category?->title ?? 'سایر',
                'questions' => $g->values(),
            ])->values(),
        ]);
    }

    /**
     * PUT /assessment/questions/{question}
     */
    public function updateQuestion(Request $request, AssessmentQuestion $question): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:500',
            'category_id' => 'nullable|exists:assessment_categories,id',
            'required_score' => 'nullable|integer|min:1|max:5',
            'risk_level' => 'nullable|integer|min:1|max:5',  // ✅ باید integer باشد
            'fix_deadline' => 'nullable|string',
        ]);

        $question->update($data);

        return response()->json([
            'message' => 'سوال با موفقیت ویرایش شد.',
            'question' => $question->fresh(),
        ]);
    }

    /**
     * PUT /assessment/questions/bulk
     * ذخیره گروهی تغییرات شناسنامه
     */
    public function bulkUpdateQuestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.id'             => 'required|exists:assessment_questions,id',
            'items.*.required_score' => 'nullable|integer|between:1,5',
            'items.*.risk_level'     => 'nullable|integer|between:1,5',
            'items.*.fix_deadline'   => 'nullable|in:immediate,short_term,mid_term,long_term',
        ]);

        foreach ($validated['items'] as $item) {
            AssessmentQuestion::where('id', $item['id'])
                ->update(collect($item)->except('id')->toArray());
        }

        return response()->json(['updated' => count($validated['items'])]);
    }

    /**
     * GET /assessment/categories
     */
    public function categories(): JsonResponse
    {
        return response()->json([
            'categories' => AssessmentCategory::orderBy('sort_order')->get(),
        ]);
    }

    /**
     * POST /assessment/categories
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200|unique:assessment_categories,title',
        ]);

        $category = AssessmentCategory::create([
            'title'      => $validated['title'],
            'sort_order' => (AssessmentCategory::max('sort_order') ?? 0) + 1,
            'is_active'  => true,
        ]);

        return response()->json(['category' => $category], 201);
    }

    /**
     * POST /assessment/questions
     */
    public function storeQuestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'post_id'        => 'required|exists:assessment_posts,id',
            'category_id'    => 'required|exists:assessment_categories,id',
            'title'          => 'required|string|max:1000',
            'required_score' => 'nullable|integer|between:1,5',
            'risk_level'     => 'nullable|integer|between:1,5',
            'fix_deadline'   => 'nullable|in:immediate,short_term,mid_term,long_term',
        ]);

        $question = AssessmentQuestion::create($validated + ['is_active' => true]);

        return response()->json(['question' => $question], 201);
    }

    /**
     * DELETE /assessment/questions/{question}
     * اگر سوال در ارزیابی‌ها استفاده شده باشد، به جای حذف، غیرفعال می‌شود
     */
    public function destroyQuestion(AssessmentQuestion $question): JsonResponse
    {
        if ($question->answers()->exists()) {
            $question->update(['is_active' => false]);
            return response()->json([
                'message'  => 'این سوال در ارزیابی‌ها استفاده شده، بنابراین به جای حذف، غیرفعال شد.',
                'disabled' => true,
            ]);
        }

        $question->delete();

        return response()->json([
            'message'  => 'سوال حذف شد.',
            'disabled' => false,
        ]);
    }

    /**
     * POST /assessment/posts
     * تعریف پست سازمانی جدید
     */
    public function storePost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'                => 'required|string|max:255|unique:assessment_posts,title',
            'grade'                => 'nullable|string|max:50',
            'unit'                 => 'nullable|string|max:150',
            'domain'               => 'nullable|string|max:150',
            'min_education'        => 'nullable|string|max:50',
            'min_experience_years' => 'nullable|integer|min:0|max:50',
            'description'          => 'nullable|string',
        ], [
            'title.required' => 'عنوان پست الزامی است.',
            'title.unique'   => 'پستی با این عنوان قبلاً ثبت شده است.',
        ]);

        $post = AssessmentPost::create($validated + ['is_active' => true]);

        return response()->json(['post' => $post], 201);
    }

    /**
     * PUT /assessment/posts/{post}
     * ویرایش پست
     */
    public function updatePost(Request $request, AssessmentPost $post): JsonResponse
    {
        $validated = $request->validate([
            'title'                => 'sometimes|required|string|max:255|unique:assessment_posts,title,' . $post->id,
            'grade'                => 'nullable|string|max:50',
            'unit'                 => 'nullable|string|max:150',
            'domain'               => 'nullable|string|max:150',
            'min_education'        => 'nullable|string|max:50',
            'min_experience_years' => 'nullable|integer|min:0|max:50',
            'description'          => 'nullable|string',
            'is_active'            => 'nullable|boolean',
        ]);

        $post->update($validated);

        return response()->json(['post' => $post->fresh()]);
    }

    /**
     * DELETE /assessment/posts/{post}
     * حذف پست — اگر سوال/ارزیابی داشته باشد، غیرفعال می‌شود
     */
    public function destroyPost(AssessmentPost $post): JsonResponse
    {
        if ($post->questions()->exists() || $post->assessments()->exists()) {
            $post->update(['is_active' => false]);
            return response()->json([
                'message'  => 'این پست دارای سوال یا ارزیابی است، بنابراین به جای حذف، غیرفعال شد.',
                'disabled' => true,
            ]);
        }

        $post->delete();

        return response()->json([
            'message'  => 'پست با موفقیت حذف شد.',
            'disabled' => false,
        ]);
    }

    /**
     * GET /assessment/suggest-post?user_id=
     * پیشنهاد پست ارزیابی بر اساس پست فعلی کارمند در HR
     * ✅ اصلاح شده: بر اساس grade + unit
     */
    public function suggestPost(Request $request): JsonResponse
    {
        $userId = (int) $request->query('user_id');

        $position = \Modules\HR\App\Models\EmployeePosition::where('user_id', $userId)->first();

        if (!$position || !$position->post_title) {
            return response()->json(['post_id' => null, 'post_title' => null]);
        }

        $classifier = app(\Modules\Assessment\App\Services\JobFamilyClassifier::class);
        $family = $classifier->classify($position->post_title);

        if (!$family) {
            return response()->json(['post_id' => null, 'post_title' => $position->post_title]);
        }

        // ✅ جستجو بر اساس grade + unit
        $post = AssessmentPost::findByGradeAndUnit($family, $position->unit?->title);

        return response()->json([
            'post_id'    => $post?->id,
            'post_title' => $post?->title,
            'family'     => $family,
            'unit'       => $position->unit?->title,
        ]);
    }

    /**
     * POST /assessment/methods
     */
    public function storeMethod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255|unique:assessment_methods,title',
        ], [
            'title.required' => 'عنوان روش الزامی است.',
            'title.unique'   => 'این روش قبلاً ثبت شده است.',
        ]);

        $method = AssessmentMethod::create($validated + ['is_active' => true]);

        return response()->json(['method' => $method], 201);
    }

    /**
     * PUT /assessment/methods/{method}
     */
    public function updateMethod(Request $request, AssessmentMethod $method): JsonResponse
    {
        $validated = $request->validate([
            'title'     => 'sometimes|required|string|max:255|unique:assessment_methods,title,' . $method->id,
            'is_active' => 'nullable|boolean',
        ]);

        $method->update($validated);

        return response()->json(['method' => $method->fresh()]);
    }

    /**
     * DELETE /assessment/methods/{method}
     * اگر در اقدامات اصلاحی استفاده شده → غیرفعال؛ وگرنه حذف
     */
    public function destroyMethod(AssessmentMethod $method): JsonResponse
    {
        $used = \Modules\Assessment\App\Models\AssessmentAction::where('method_id', $method->id)->exists();

        if ($used) {
            $method->update(['is_active' => false]);
            return response()->json([
                'message'  => 'این روش در اقدامات اصلاحی استفاده شده، بنابراین غیرفعال شد.',
                'disabled' => true,
            ]);
        }

        $method->delete();

        return response()->json([
            'message'  => 'روش با موفقیت حذف شد.',
            'disabled' => false,
        ]);
    }

    /**
     * GET /assessment/employees/{user}/report
     * کارنامه شایستگی یک کارمند (همه ارزیابی‌ها + گپ‌ها + اقدامات)
     */
    public function employeeReport(Request $request, int $user): JsonResponse
    {
        // 🔐 فقط HR یا دارای دسترسی ارزیابی
        if (!$request->user()->can('hr.view') && !$request->user()->can('assessment.view')) {
            return response()->json(['message' => 'دسترسی مجاز نیست.'], 403);
        }

        $assessments = Assessment::where('employee_user_id', $user)
            ->with([
                'post', 'cycle', 'evaluator',
                'gaps' => fn($q) => $q->with(['question.category', 'actions.method'])->orderByDesc('weighted_gap'),
            ])
            ->latest()
            ->get()
            ->map(function ($a) {
                return [
                    'id'                   => $a->id,
                    'status'               => $a->status,
                    'status_label'         => $this->statusLabel($a->status),
                    'post_title'           => $a->post?->title,
                    'cycle_title'          => $a->cycle?->title,
                    'evaluator_name'       => $a->evaluator?->name,
                    'submitted_at'         => $a->submitted_at?->format('Y-m-d'),
                    'average_score'        => $a->average_score,
                    'total_weighted_gap'   => $a->total_weighted_gap,
                    'gaps_count'           => $a->gaps->count(),
                    'critical_count'       => $a->gaps->filter(fn($g) => $g->severity_label === 'بحرانی')->count(),
                    'categories'           => $a->gaps->groupBy('question.category.title')->map(fn($group, $title) => [
                        'title' => $title,
                        'gaps'  => $group->map(fn($g) => [
                            'id'             => $g->id,
                            'question'       => $g->question?->title,
                            'required_score' => $g->required_score,
                            'actual_score'   => $g->actual_score,
                            'gap'            => $g->gap,
                            'weighted_gap'   => $g->weighted_gap,
                            'severity'       => $g->severity_label,
                            'actions'        => $g->actions->map(fn($act) => [
                                'title'  => $act->title,
                                'method' => $act->method?->title,
                                'status' => $act->status,
                            ])->values(),
                        ])->values(),
                    ])->values(),
                ];
            });

        return response()->json(['assessments' => $assessments]);
    }

    private function statusLabel(string $status): string
    {
        return [
            'draft'     => 'در حال ارزیابی',
            'submitted' => 'تکمیل شده',
            'approved'  => 'تایید شده',
            'rejected'  => 'برگشت خورده',
        ][$status] ?? $status;
    }

    public function autoAssign(AssessmentPeriod $period, AssessmentAutoAssignService $service): JsonResponse
    {
        if ($period->status !== 'draft') {
            return response()->json([
                'message' => 'فقط دوره‌های پیش‌نویس قابل تخصیص خودکار هستند.',
            ], 422);
        }

        $result = $service->autoAssign($period);

        return response()->json([
            'message'    => 'تخصیص خودکار انجام شد.',
            'assigned'   => $result['assigned'],
            'unassigned' => $result['unassigned'],
            'skipped'    => $result['skipped'],
        ]);
    }

    /**
     * ✅ متد کمکی: دریافت بازگشتی تمام ID واحدهای سازمانی زیرمجموعه یک واحد
     */
    private function getSubordinateUnitIds(?int $unitId): array
    {
        if (!$unitId) return [];

        $ids = [$unitId];
        $children = \Modules\HR\App\Models\OrganizationalUnit::where('parent_id', $unitId)->pluck('id');

        foreach ($children as $childId) {
            $ids = array_merge($ids, $this->getSubordinateUnitIds($childId));
        }

        return array_unique($ids);
    }


    /**
     * GET /assessment/posts/{post}
     */
    public function showPost(AssessmentPost $post): JsonResponse
    {
        $post->load(['questions.category']);

        $stats = [
            'total_questions'    => $post->questions()->count(),
            'active_questions'   => $post->questions()->where('is_active', true)->count(),
            'with_risk_level'    => $post->questions()->whereNotNull('risk_level')->count(),
            'categories_count'   => $post->questions()->distinct('category_id')->count('category_id'),
        ];

        return response()->json([
            'post'  => $post,
            'stats' => $stats,
        ]);
    }

    /**
     * POST /assessment/import/preview
     */
    public function importPreview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $tempPath = $file->getRealPath();

        try {
            $importer = app(\Modules\Assessment\App\Services\ExcelProfileImporter::class);
            $result = $importer->importFile($tempPath, dryRun: true, originalFilename: $originalName);

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'خطا در پردازش فایل: ' . $e->getMessage(),
                'stats' => ['files' => 0, 'sheets' => 0, 'questions' => 0, 'created' => 0, 'updated' => 0, 'posts' => 0],
                'anomalies' => [],
            ], 500);
        }
    }

    /**
     * POST /assessment/import
     */
    public function importExcel(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $tempPath = $file->getRealPath();

        try {
            $importer = app(\Modules\Assessment\App\Services\ExcelProfileImporter::class);
            $result = $importer->importFile($tempPath, dryRun: false, originalFilename: $originalName);

            return response()->json([
                'message' => 'فایل با موفقیت import شد.',
                'stats' => $result['stats'],
                'anomalies' => $result['anomalies'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'خطا در import: ' . $e->getMessage(),
                'stats' => ['files' => 0, 'sheets' => 0, 'questions' => 0, 'created' => 0, 'updated' => 0, 'posts' => 0],
                'anomalies' => [],
            ], 500);
        }
    }

    /**
     * POST /assessment/import/bulk
     * import چند فایل از یک پوشه
     */
    public function importBulk(Request $request): JsonResponse
    {
        $request->validate([
            'directory' => 'required|string',
        ]);

        $directory = storage_path('app/' . $request->directory);
        if (!is_dir($directory)) {
            return response()->json(['message' => 'پوشه یافت نشد.'], 404);
        }

        $files = glob($directory . '/*.xlsx');
        $importer = app(\Modules\Assessment\App\Services\ExcelProfileImporter::class);
        $totalResult = ['stats' => [], 'anomalies' => []];

        foreach ($files as $file) {
            $result = $importer->importFile($file, dryRun: false);
            $totalResult['stats'] = array_merge_recursive(
                $totalResult['stats'],
                $result['stats']
            );
            $totalResult['anomalies'] = array_merge(
                $totalResult['anomalies'],
                $result['anomalies']
            );
        }

        return response()->json([
            'message'   => count($files) . ' فایل import شد.',
            'stats'     => $totalResult['stats'],
            'anomalies' => $totalResult['anomalies'],
        ]);
    }

    /**
     * GET /assessment/dashboard/stats
     * آمار داشبورد
     */
    public function dashboardStats(Request $request): JsonResponse
    {
        $user = $request->user();

        $stats = [
            'total_posts'         => \Modules\Assessment\App\Models\AssessmentPost::where('is_active', true)->count(),
            'total_questions'     => \Modules\Assessment\App\Models\AssessmentQuestion::where('is_active', true)->count(),
            'total_categories'    => \Modules\Assessment\App\Models\AssessmentCategory::where('is_active', true)->count(),
            'total_methods'       => \Modules\Assessment\App\Models\AssessmentMethod::where('is_active', true)->count(),
            'total_cycles'        => \Modules\Assessment\App\Models\AssessmentCycle::count(),
            'active_cycles'       => \Modules\Assessment\App\Models\AssessmentCycle::where('status', 'active')->count(),
            'total_periods'       => \Modules\Assessment\App\Models\AssessmentPeriod::count(),
            'active_periods'      => \Modules\Assessment\App\Models\AssessmentPeriod::where('status', 'active')->count(),
        ];

        if ($user->can('assessment.evaluate') || $user->can('assessment.manage')) {
            $stats['my_evaluations'] = \Modules\Assessment\App\Models\Assessment::where('evaluator_user_id', $user->id)->count();
            $stats['pending_evaluations'] = \Modules\Assessment\App\Models\Assessment::where('evaluator_user_id', $user->id)
                ->where('status', 'draft')->count();
        }

        return response()->json(['stats' => $stats]);
    }












}
