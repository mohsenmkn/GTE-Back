<?php

namespace Modules\HR\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\App\Models\User;

class EmployeeRelative extends Model
{
    protected $fillable = [
        'user_id',
        'gt_employee_id',
        'gt_relative_id',
        'first_name',
        'last_name',
        'father_name',
        'relation_code',
        'national_id',
        'id_number',
        'birth_date',
        'degree_code',
        'education_state_code',
        'physical_state_code',
        'marital_status_code',
        'relative_type',
        'job',
        'description',
        'effective_date',
        'synced_at',
    ];

    protected $casts = [
        'birth_date'     => 'date',
        'effective_date' => 'date',
        'synced_at'      => 'datetime',
    ];

    // ─────────────────────────────────────────────
    //  روابط
    // ─────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─────────────────────────────────────────────
    //  Scopes
    // ─────────────────────────────────────────────

    /**
     * فقط همسر
     */
    public function scopeSpouse($query)
    {
        return $query->where('relation_code', 3);
    }

    /**
     * فقط فرزندان
     */
    public function scopeChildren($query)
    {
        return $query->whereIn('relation_code', [7, 8]);
    }

    /**
     * مرتب‌سازی بر اساس اولویت نسبت
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('relation_code')->orderBy('birth_date');
    }

    // ─────────────────────────────────────────────
    //  Accessors
    // ─────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date
            ? $this->birth_date->diffInYears(now())
            : null;
    }

    public function getRelationTitleAttribute(): string
    {
        return match ($this->relation_code) {
            3 => 'همسر',
            7 => 'فرزند پسر',
            8 => 'فرزند دختر',
            4 => 'پدر / مادر',
            default => 'سایر',
        };
    }

    public function getDegreeTitleAttribute(): string
    {
        return match ($this->degree_code) {
            1 => 'بی‌سواد',
            2 => 'سیکل',
            3 => 'دیپلم',
            4 => 'فوق دیپلم',
            5 => 'لیسانس',
            6 => 'فوق لیسانس',
            7 => 'دکتری',
            default => '—',
        };
    }

    public function getMaritalStatusTitleAttribute(): string
    {
        return match ($this->marital_status_code) {
            1 => 'مجرد',
            2 => 'متأهل',
            3 => 'طلاق گرفته',
            4 => 'فوت شده',
            default => '—',
        };
    }

    public function getEducationStateTitleAttribute(): string
    {
        return match ($this->education_state_code) {
            1 => 'در حال تحصیل',
            2 => 'فارغ‌التحصیل',
            3 => 'ترک تحصیل',
            default => '—',
        };
    }

    public function getPhysicalStateTitleAttribute(): string
    {
        return match ($this->physical_state_code) {
            1 => 'سالم',
            2 => 'دارای معلولیت',
            default => '—',
        };
    }
}
