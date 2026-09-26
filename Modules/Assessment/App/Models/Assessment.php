<?php
namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Auth\App\Models\User;

class Assessment extends Model
{
    protected $table = 'assessments';

    protected $fillable = [
        'cycle_id',
        'period_id',
        'employee_user_id',
        'evaluator_user_id',
        'post_id',
        'status',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'rejection_notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /* ──────────────────────────────── Relations ─────────────────────────────── */

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AssessmentCycle::class, 'cycle_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'period_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(AssessmentPost::class, 'post_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\App\Models\User::class, 'employee_user_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\App\Models\User::class, 'evaluator_user_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class, 'assessment_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AssessmentAction::class, 'assessment_id');
    }

    /* ──────────────────────────────── Status Constants ──────────────────────────────── */

    const STATUS_DRAFT     = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED  = 'approved';
    const STATUS_REJECTED  = 'rejected';

    /* ──────────────────────────────── Relations ──────────────────────────────── */


    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function gaps(): HasMany
    {
        return $this->hasMany(AssessmentGap::class);
    }

    /* ──────────────────────────────── Scopes ──────────────────────────────── */

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeForEvaluator($query, int $userId)
    {
        return $query->where('evaluator_user_id', $userId);
    }

    public function scopeForEmployee($query, int $userId)
    {
        return $query->where('employee_user_id', $userId);
    }

    /* ─────────────────────────────── Accessors ──────────────────────────────── */

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT     => 'پیش‌نویس',
            self::STATUS_SUBMITTED => 'تکمیل شده',
            self::STATUS_APPROVED  => 'تایید شده',
            self::STATUS_REJECTED  => 'برگشت خورده',
            default => $this->status,
        };
    }

    public function getAverageScoreAttribute(): float
    {
        return round($this->answers()->avg('score') ?? 0, 2);
    }

    public function getTotalWeightedGapAttribute(): int
    {
        return (int) $this->gaps()->sum('weighted_gap');
    }

    public function getGapsCountAttribute(): int
    {
        return $this->gaps()->count();
    }

    public function getCriticalGapsCountAttribute(): int
    {
        return $this->gaps()
            ->whereHas('question', fn($q) => $q->where('risk_level', '>=', 4))
            ->count();
    }
}
