<?php


namespace Modules\Assessment\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\Assessment\App\Models\AssessmentQuestionProposal;
use Modules\Assessment\App\Services\AssessmentProposalService;

class AssessmentProposalController extends Controller
{
    public function __construct(
        private AssessmentProposalService $service
    )
    {
    }

    /* ──────────────────────────────── Designable Subordinates ─────────────────────────────── */

    /**
     * GET /assessment/proposals/designable
     * دریافت زیرمجموعه‌هایی که کاربر می‌تونه براشون سوال طراحی کنه
     */
    public function designable(Request $request): JsonResponse
    {
        if (!$request->user()->can('assessment.proposal_create')) {
            return response()->json(['message' => 'دسترسی مجاز نیست.'], 403);
        }

        $subordinates = $this->service->getDesignableSubordinates($request->user()->id);

        return response()->json([
            'subordinates' => $subordinates,
            'total' => $subordinates->count(),
        ]);
    }

    /* ──────────────────────────────── Proposal CRUD ─────────────────────────────── */

    /**
     * GET /assessment/proposals
     * لیست پیشنهادات (برای HR Admin: همه، برای بالادست: فقط پیشنهادات خودش)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->can('assessment.proposal_review') || $user->can('assessment.manage')) {
            // HR Admin: همه پیشنهادات
            $proposals = $this->service->getPendingProposals();
        } elseif ($user->can('assessment.proposal_create')) {
            // بالادست: فقط پیشنهادات خودش
            $proposals = $this->service->getMyProposals($user->id);
        } else {
            return response()->json(['message' => 'دسترسی مجاز نیست.'], 403);
        }

        return response()->json([
            'proposals' => $proposals,
        ]);
    }

    /**
     * GET /assessment/proposals/stats
     * آمار پیشنهادات (فقط HR Admin)
     */
    public function stats(Request $request): JsonResponse
    {
        if (!$request->user()->can('assessment.proposal_review') && !$request->user()->can('assessment.manage')) {
            return response()->json(['message' => 'دسترسی مجاز نیست.'], 403);
        }

        return response()->json([
            'stats' => $this->service->getProposalStats(),
        ]);
    }

    /**
     * GET /assessment/proposals/post/{postId}
     * پیشنهادات یک شناسنامه خاص
     */
    public function forPost(Request $request, int $postId): JsonResponse
    {
        $user = $request->user();
        if (!$user->can('assessment.proposal_review') && !$user->can('assessment.manage')) {
            return response()->json(['message' => 'دسترسی مجاز نیست.'], 403);
        }

        $proposals = $this->service->getProposalsForPost($postId);

        return response()->json([
            'proposals' => $proposals,
        ]);
    }

    /**
     * POST /assessment/proposals
     * ایجاد پیشنهاد سوال جدید (توسط بالادست)
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->can('assessment.proposal_create')) {
            return response()->json(['message' => 'دسترسی مجاز نیست.'], 403);
        }

        $validated = $request->validate([
            'target_post_id' => 'required|exists:assessment_posts,id',
            'category_id' => 'required|exists:assessment_categories,id',
            'title' => 'required|string|max:1000',
            'risk_level' => 'nullable|integer|min:1|max:5',
            'required_score' => 'nullable|integer|min:1|max:5',
            'fix_deadline' => 'nullable|in:immediate,short_term,mid_term,long_term',
        ]);

        try {
            $proposal = $this->service->createProposal(
                (int)$validated['target_post_id'],
                $request->user()->id,
                $validated
            );

            return response()->json([
                'message' => 'پیشنهاد با موفقیت ثبت شد و در انتظار بررسی مدیر HR است',
                'proposal' => $proposal->load(['targetPost', 'proposer', 'category']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /assessment/proposals/{proposal}/approve
     * تایید پیشنهاد و تبدیل به سوال واقعی (HR Admin)
     */
    public function approve(Request $request, AssessmentQuestionProposal $proposal): JsonResponse
    {
        if (!$request->user()->can('assessment.proposal_review') && !$request->user()->can('assessment.manage')) {
            return response()->json(['message' => 'فقط مدیر HR مجاز به تایید پیشنهاد است.'], 403);
        }

        try {
            $question = $this->service->approveProposal($proposal->id, $request->user()->id);

            return response()->json([
                'message' => 'پیشنهاد تایید و به سوال تبدیل شد',
                'question' => $question->load('category'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /assessment/proposals/{proposal}/reject
     * رد پیشنهاد (HR Admin)
     */
    public function reject(Request $request, AssessmentQuestionProposal $proposal): JsonResponse
    {
        if (!$request->user()->can('assessment.proposal_review') && !$request->user()->can('assessment.manage')) {
            return response()->json(['message' => 'فقط مدیر HR مجاز به رد پیشنهاد است.'], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $rejected = $this->service->rejectProposal(
                $proposal->id,
                $request->user()->id,
                $validated['reason']
            );

            return response()->json([
                'message' => 'پیشنهاد رد شد',
                'proposal' => $rejected->load('reviewer'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * DELETE /assessment/proposals/{proposal}
     * حذف پیشنهاد (فقط توسط خود proposer یا HR Admin)
     */
    public function destroy(Request $request, AssessmentQuestionProposal $proposal): JsonResponse
    {
        $user = $request->user();
        $isOwner = (int)$proposal->proposed_by_user_id === (int)$user->id;
        $isAdmin = $user->can('assessment.manage');

        if (!$isOwner && !$isAdmin) {
            return response()->json(['message' => 'فقط ایجادکننده یا مدیر HR مجاز به حذف است.'], 403);
        }

        if ($proposal->status !== AssessmentQuestionProposal::STATUS_PENDING) {
            return response()->json(['message' => 'فقط پیشنهادات در انتظار بررسی قابل حذف هستند.'], 422);
        }

        $proposal->delete();

        return response()->json(['message' => 'پیشنهاد حذف شد']);
    }

    /* ──────────────────────────────── Bulk Actions ─────────────────────────────── */

    /**
     * POST /assessment/proposals/bulk-approve
     * تایید گروهی پیشنهادات (HR Admin)
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        if (!$request->user()->can('assessment.proposal_review') && !$request->user()->can('assessment.manage')) {
            return response()->json(['message' => 'دسترسی مجاز نیست.'], 403);
        }

        $validated = $request->validate([
            'proposal_ids' => 'required|array|min:1',
            'proposal_ids.*' => 'exists:assessment_question_proposals,id',
        ]);

        $approved = 0;
        $failed = 0;
        $errors = [];

        foreach ($validated['proposal_ids'] as $proposalId) {
            try {
                $this->service->approveProposal($proposalId, $request->user()->id);
                $approved++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "پیشنهاد #{$proposalId}: " . $e->getMessage();
            }
        }

        return response()->json([
            'message' => "{$approved} پیشنهاد تایید شد" . ($failed > 0 ? "، {$failed} مورد با خطا مواجه شد" : ''),
            'approved' => $approved,
            'failed' => $failed,
            'errors' => $errors,
        ]);
    }

    /**
     * POST /assessment/proposals/bulk-reject
     * رد گروهی پیشنهادات (HR Admin)
     */
    public function bulkReject(Request $request): JsonResponse
    {
        if (!$request->user()->can('assessment.proposal_review') && !$request->user()->can('assessment.manage')) {
            return response()->json(['message' => 'دسترسی مجاز نیست.'], 403);
        }

        $validated = $request->validate([
            'proposal_ids' => 'required|array|min:1',
            'proposal_ids.*' => 'exists:assessment_question_proposals,id',
            'reason' => 'required|string|max:1000',
        ]);

        $rejected = 0;
        $failed = 0;

        foreach ($validated['proposal_ids'] as $proposalId) {
            try {
                $this->service->rejectProposal($proposalId, $request->user()->id, $validated['reason']);
                $rejected++;
            } catch (\Exception $e) {
                $failed++;
            }
        }

        return response()->json([
            'message' => "{$rejected} پیشنهاد رد شد",
            'rejected' => $rejected,
            'failed' => $failed,
        ]);
    }
}
