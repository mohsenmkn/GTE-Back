<?php


namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentTemplateQuestion extends Model
{
    protected $table = 'assessment_template_questions';

    protected $fillable = [
        'template_id',
        'category_id',
        'title',
        'risk_level',
        'required_score',
        'fix_deadline',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'risk_level' => 'integer',
        'required_score' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'template_id' => 'integer',
        'category_id' => 'integer',
    ];

    /* ──────────────────────────────── Relations ─────────────────────────────── */

    public function template(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestionTemplate::class, 'template_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssessmentCategory::class, 'category_id');
    }

    /* ──────────────────────────────── Scopes ─────────────────────────────── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /* ──────────────────────────────── Accessors ─────────────────────────────── */

    /**
     * اگر required_score خالی باشد، از risk_level استفاده کن (مشابه AssessmentQuestion)
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
            'immediate' => 'فوری (تا ۶ ماه)',
            'short_term' => 'کوتاه‌مدت (۶ تا ۱۲ ماه)',
            'mid_term' => 'میان‌مدت (۱ تا ۲ سال)',
            'long_term' => 'بلندمدت (۲ تا ۳ سال)',
            default => '—',
        };
    }
}
