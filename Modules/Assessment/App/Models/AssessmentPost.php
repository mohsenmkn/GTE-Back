<?php
namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class AssessmentPost extends Model
{

    protected $fillable = [
        'title',
        'code',
        'grade',              // ✅ رده شغلی (مثل: رئیس، کارشناس)
        'unit',               // ✅ واحد سازمانی (مثل: سرمایه انسانی و پشتیبانی)
        'domain',             // ✅ حوزه تخصصی (اختیاری)
        'min_education',
        'min_experience_years',
        'description',
        'is_active',
        'source',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'min_experience_years' => 'integer',
    ];

    /* ──────────────────────────────── Relations ─────────────────────────────── */

    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class, 'post_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'post_id');
    }

    /* ──────────────────────────────── Scopes ──────────────────────────────── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByGrade($query, string $grade)
    {
        return $query->where('grade', $grade);
    }

    public function scopeByUnit($query, string $unit)
    {
        return $query->where('unit', $unit);
    }

    /* ──────────────────────────────── Query Helpers ──────────────────────────────── */

    /**
     * یافتن شناسنامه بر اساس grade + unit
     * اولویت: تطابق دقیق grade + unit → سپس فقط grade
     */
    public static function findByGradeAndUnit(string $grade, string $unit): ?self
    {
        return self::where('is_active', true)
            ->where('grade', $grade)
            ->where('unit', $unit)
            ->first()
            ?? self::where('is_active', true)
                ->where('grade', $grade)
                ->first();
    }

    /**
     * عنوان نمایشی: "رئیس - سرمایه انسانی و پشتیبانی"
     */
    public function getDisplayNameAttribute(): string
    {
        $parts = array_filter([$this->grade, $this->unit]);
        return implode(' - ', $parts) ?: $this->title;
    }

    /**
     * آمار سوالات
     */
    public function getQuestionsCountAttribute(): int
    {
        return $this->questions()->count();
    }

    public function getActiveQuestionsCountAttribute(): int
    {
        return $this->questions()->where('is_active', true)->count();
    }

    /**
     * ✅ اصلاح شده: استفاده از HasManyThrough به جای belongsToMany
     *
     * ساختار: Post → Questions → Categories
     *
     * پارامترهای hasManyThrough:
     * 1. مدل نهایی (Category)
     * 2. مدل واسط (Question)
     * 3. FK روی جدول واسط که به Post اشاره می‌کند (post_id)
     * 4. PK روی جدول نهایی (id)
     * 5. PK روی جدول فعلی (id)
     * 6. FK روی جدول واسط که به Category اشاره می‌کند (category_id)
     */
    public function categories(): HasManyThrough
    {
        return $this->hasManyThrough(
            AssessmentCategory::class,    // مدل نهایی
            AssessmentQuestion::class,    // مدل واسط
            'post_id',                    // FK در جدول واسط به Post
            'id',                         // PK در جدول Category
            'id',                         // PK در جدول Post
            'category_id'                 // FK در جدول واسط به Category
        )->distinct()->orderBy('sort_order');
    }
}
