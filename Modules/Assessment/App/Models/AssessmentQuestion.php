<?php
namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentQuestion extends Model
{
    protected $table = 'assessment_questions';

    protected $fillable = [
        'post_id',
        'category_id',
        'title',
        'risk_level',
        'fix_deadline',
        'sort_order',
        'is_active',
        'required_score',
    ];

    protected $casts = [
        'risk_level' => 'integer',
        'required_score' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /* ──────────────────────────────── Relations ──────────────────────────────── */

    public function post(): BelongsTo
    {
        return $this->belongsTo(AssessmentPost::class, 'post_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssessmentCategory::class, 'category_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class, 'question_id');
    }

    public function gaps(): HasMany
    {
        return $this->hasMany(AssessmentGap::class, 'question_id');
    }

    /* ──────────────────────────────── Scopes ──────────────────────────────── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPost($query, int $postId)
    {
        return $query->where('post_id', $postId);
    }

    /* ─────────────────────────────── Accessors ──────────────────────────────── */

    /**
     * اگر required_score خالی باشد، از risk_level استفاده کن
     */
    public function getEffectiveRequiredScoreAttribute(): ?int
    {
        return $this->required_score ?? $this->risk_level;
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
            'immediate'  => 'فوری (تا ۶ ماه)',
            'short_term' => 'کوتاه‌مدت (۶ تا ۱۲ ماه)',
            'mid_term'   => 'میان‌مدت (۱ تا ۲ سال)',
            'long_term'  => 'بلندمدت (۲ تا ۳ سال)',
            default => '—',
        };
    }

    public function getFixDeadlineMonthsAttribute(): int
    {
        return match ($this->fix_deadline) {
            'immediate'  => 6,
            'short_term' => 12,
            'mid_term'   => 24,
            'long_term'  => 36,
            default => 6,
        };
    }
}
