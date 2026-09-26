<?php

namespace Modules\HR\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\App\Models\User;

class EmployeeStatuteHistory extends Model
{
    protected $table = 'employee_statute_history';

    protected $fillable = [
        'user_id',
        'gt_employee_id',
        'gt_statute_id',
        'post_ref',
        'post_code',
        'post_title',
        'job_ref',
        'job_code',
        'job_title',
        'department_ref',
        'organizational_structure_ref',
        'issue_date',
        'apply_date',
        'expiry_date',
        'statute_number',
        'is_current',
        'synced_at',
    ];

    protected $casts = [
        'issue_date'  => 'datetime',
        'apply_date'  => 'datetime',
        'expiry_date' => 'datetime',
        'is_current'  => 'boolean',
        'synced_at'   => 'datetime',
    ];

    // ── روابط ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ──

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('issue_date', 'desc');
    }

    // ── Accessors ──

    /**
     * عنوان کامل حکم: سمت + شغل
     */
    public function getFullTitleAttribute(): string
    {
        $parts = array_filter([
            $this->post_title,
            $this->job_title ? "({$this->job_title})" : null,
        ]);

        return implode(' ', $parts) ?: 'بدون عنوان';
    }

    /**
     * آیا سمت نسبت به حکم قبلی تغییر کرده؟
     */
    public function hasPostChangedComparedTo(?self $previous): bool
    {
        if (!$previous) return true;
        return $this->post_ref !== $previous->post_ref;
    }
}
