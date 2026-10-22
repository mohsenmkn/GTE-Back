<?php


namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\App\Models\User;

class AssessmentQuestionProposal extends Model
{
    protected $table = 'assessment_question_proposals';

    protected $fillable = [
        'target_post_id',
        'proposed_by_user_id',
        'category_id',
        'title',
        'risk_level',
        'required_score',
        'fix_deadline',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'risk_level' => 'integer',
        'required_score' => 'integer',
        'target_post_id' => 'integer',
        'proposed_by_user_id' => 'integer',
        'category_id' => 'integer',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    /* ──────────────────────────────── Status Constants ─────────────────────────────── */

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /* ──────────────────────────────── Relations ─────────────────────────────── */

    public function targetPost(): BelongsTo
    {
        return $this->belongsTo(AssessmentPost::class, 'target_post_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssessmentCategory::class, 'category_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /* ──────────────────────────────── Scopes ─────────────────────────────── */

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeForPost($query, int $postId)
    {
        return $query->where('target_post_id', $postId);
    }

    public function scopeByProposer($query, int $userId)
    {
        return $query->where('proposed_by_user_id', $userId);
    }

    /* ──────────────────────────────── Accessors ─────────────────────────────── */

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'در انتظار بررسی',
            self::STATUS_APPROVED => 'تایید شده',
            self::STATUS_REJECTED => 'رد شده',
            default => $this->status,
        };
    }

    public function getRiskLabelAttribute(): string
    {
        return match ($this->risk_level) {
            5 => 'فاجعه‌بار',
            4 => 'بحرانی',
            3 => 'متوسط',
            2 => 'ضعیف',
            1 => 'قابل چشم‌پوشی',
            default => '—',
        };
    }

    public function getFixDeadlineLabelAttribute(): string
    {
        return match ($this->fix_deadline) {
            'immediate' => 'فوری (تا ۶ ماه)',
            'short_term' => 'کوتاه‌مدت (۶ تا ۱۲ ماه)',
            'mid_term' => 'میان‌مدت (۱ تا ۲ سال)',
            'long_term' => 'بلندمدت (۲ تا ۳ سال)',
            default => '—',
        };
    }
}
