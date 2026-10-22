<?php


namespace Modules\Assessment\App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\Assessment\App\Models\AssessmentQuestion;
use Modules\Assessment\App\Models\AssessmentQuestionProposal;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Services\OrganizationHierarchyService;

class AssessmentProposalService
{
    public function __construct(
        private OrganizationHierarchyService $hierarchy,
        private JobFamilyClassifier          $classifier
    )
    {
    }

    /* ──────────────────────────────── Designable Subordinates ─────────────────────────────── */

    /**
     * دریافت زیرمجموعه‌هایی که کاربر می‌تونه براشون سوال طراحی کنه
     */
// در AssessmentProposalService.php
    public function getDesignableSubordinates(int $userId): Collection
    {
        $snapshot = $this->hierarchy->snapshot();

        // پیدا کردن EmployeePosition کاربر
        $userPosition = EmployeePosition::where('user_id', $userId)->first();

        if (!$userPosition) {
            return collect();
        }

        // ✅ استفاده از متد جدید subordinates()
        $subordinates = $this->hierarchy->subordinates($userPosition, $snapshot);

        // گروه‌بندی بر اساس post_title یکتا
        return $subordinates
            ->groupBy('post_title')
            ->map(function ($group) {
                $first = $group->first();
                $family = $this->classifier->classify($first->post_title);

                // بررسی وجود شناسنامه برای این post_title
                $existingPost = AssessmentPost::where('title', $first->post_title)
                    ->orWhere(function ($q) use ($family, $first) {
                        $q->where('grade', $family)
                            ->where('unit', $first->unit?->title);
                    })
                    ->first();

                return [
                    'post_title'       => $first->post_title,
                    'unit'             => $first->unit?->title,
                    'unit_id'          => $first->organizational_unit_id,
                    'family'           => $family,
                    'employee_count'   => $group->count(),
                    'existing_post_id' => $existingPost?->id,
                    'has_post'         => !is_null($existingPost),
                ];
            })
            ->values();
    }

    /* ──────────────────────────────── Proposal Management ─────────────────────────────── */

    /**
     * ایجاد پیشنهاد سوال جدید
     */
    public function createProposal(int $targetPostId, int $proposerId, array $data): AssessmentQuestionProposal
    {
        $validated = validator($data, [
            'category_id' => 'required|exists:assessment_categories,id',
            'title' => 'required|string|max:1000',
            'risk_level' => 'nullable|integer|min:1|max:5',
            'required_score' => 'nullable|integer|min:1|max:5',
            'fix_deadline' => 'nullable|in:immediate,short_term,mid_term,long_term',
        ])->validate();

        // بررسی تکراری نبودن پیشنهاد (برای همان post + title)
        $exists = AssessmentQuestionProposal::where('target_post_id', $targetPostId)
            ->where('title', $validated['title'])
            ->whereIn('status', [
                AssessmentQuestionProposal::STATUS_PENDING,
                AssessmentQuestionProposal::STATUS_APPROVED,
            ])
            ->exists();

        if ($exists) {
            throw new \Exception('این سوال قبلاً پیشنهاد شده است');
        }

        return AssessmentQuestionProposal::create([
            'target_post_id' => $targetPostId,
            'proposed_by_user_id' => $proposerId,
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'risk_level' => $validated['risk_level'] ?? 3,
            'required_score' => $validated['required_score'] ?? 3,
            'fix_deadline' => $validated['fix_deadline'] ?? 'mid_term',
            'status' => AssessmentQuestionProposal::STATUS_PENDING,
        ]);
    }

    /**
     * تایید پیشنهاد و تبدیل به سوال واقعی
     */
    public function approveProposal(int $proposalId, int $reviewerId): AssessmentQuestion
    {
        $proposal = AssessmentQuestionProposal::findOrFail($proposalId);

        if ($proposal->status !== AssessmentQuestionProposal::STATUS_PENDING) {
            throw new \Exception('این پیشنهاد قباً بررسی شده است');
        }

        return DB::transaction(function () use ($proposal, $reviewerId) {
            // ایجاد سوال واقعی
            $question = AssessmentQuestion::create([
                'post_id' => $proposal->target_post_id,
                'category_id' => $proposal->category_id,
                'title' => $proposal->title,
                'risk_level' => $proposal->risk_level,
                'required_score' => $proposal->required_score,
                'fix_deadline' => $proposal->fix_deadline,
                'sort_order' => AssessmentQuestion::where('post_id', $proposal->target_post_id)
                        ->max('sort_order') + 1,
                'is_active' => true,
            ]);

            // به‌روزرسانی وضعیت پیشنهاد
            $proposal->update([
                'status' => AssessmentQuestionProposal::STATUS_APPROVED,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            return $question;
        });
    }

    /**
     * رد پیشنهاد
     */
    public function rejectProposal(int $proposalId, int $reviewerId, string $reason): AssessmentQuestionProposal
    {
        $proposal = AssessmentQuestionProposal::findOrFail($proposalId);

        if ($proposal->status !== AssessmentQuestionProposal::STATUS_PENDING) {
            throw new \Exception('این پیشنهاد قبلاً بررسی شده است');
        }

        $proposal->update([
            'status' => AssessmentQuestionProposal::STATUS_REJECTED,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $proposal->fresh();
    }

    /* ──────────────────────────────── Query Helpers ─────────────────────────────── */

    /**
     * دریافت پیشنهادات در انتظار بررسی
     */
    public function getPendingProposals(): Collection
    {
        return AssessmentQuestionProposal::pending()
            ->with(['targetPost', 'proposer', 'category'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * دریافت پیشنهادات یک کاربر خاص
     */
    public function getMyProposals(int $userId): Collection
    {
        return AssessmentQuestionProposal::byProposer($userId)
            ->with(['targetPost', 'category', 'reviewer'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * دریافت پیشنهادات برای یک شناسنامه خاص
     */
    public function getProposalsForPost(int $postId): Collection
    {
        return AssessmentQuestionProposal::forPost($postId)
            ->with(['proposer', 'category', 'reviewer'])
            ->orderBy('status')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * دریافت آمار پیشنهادات
     */
    public function getProposalStats(): array
    {
        return [
            'pending' => AssessmentQuestionProposal::pending()->count(),
            'approved' => AssessmentQuestionProposal::approved()->count(),
            'rejected' => AssessmentQuestionProposal::rejected()->count(),
            'total' => AssessmentQuestionProposal::count(),
        ];
    }


}
