<?php


namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\App\Models\User;

class AssessmentQuestionTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'assessment_question_templates';

    protected $fillable = [
        'title',
        'grade',
        'version',
        'description',
        'is_active',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_active' => 'boolean',
        'approved_at' => 'datetime',
        'created_by' => 'integer',
        'approved_by' => 'integer',
    ];

    /* ──────────────────────────────── Relations ─────────────────────────────── */

    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentTemplateQuestion::class, 'template_id');
    }

    public function activeQuestions(): HasMany
    {
        return $this->questions()->where('is_active', true)->orderBy('sort_order');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(AssessmentPost::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /* ──────────────────────────────── Scopes ─────────────────────────────── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_at');
    }

    public function scopeByGrade($query, string $grade)
    {
        return $query->where('grade', $grade);
    }

    public function scopeLatestVersion($query, string $grade)
    {
        return $query->where('grade', $grade)
            ->orderByDesc('version')
            ->limit(1);
    }

    /* ──────────────────────────────── Accessors ─────────────────────────────── */

    public function getIsApprovedAttribute(): bool
    {
        return !is_null($this->approved_at);
    }

    public function getStatusLabelAttribute(): string
    {
        if (!$this->is_active) {
            return 'غیرفعال';
        }

        if (!$this->is_approved) {
            return 'در انتظار تایید';
        }

        return "فعال (نسخه {$this->version})";
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->title} (v{$this->version})";
    }

    /* ──────────────────────────────── Query Helpers ─────────────────────────────── */

    /**
     * دریافت آخرین نسخه تایید شده یک تمپلیت بر اساس grade
     */
    public static function latestApprovedForGrade(string $grade): ?self
    {
        return self::active()
            ->approved()
            ->byGrade($grade)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * دریافت آخرین نسخه (چه تایید شده چه نشده) برای یک عنوان خاص
     */
    public static function latestVersionForTitle(string $title): ?self
    {
        return self::where('title', $title)
            ->orderByDesc('version')
            ->first();
    }
}
