<?php

namespace Modules\HR\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\App\Models\User;

class EmployeePosition extends Model
{
    protected $fillable = [
        'user_id',
        'personnel_code',
        'gt_employee_id',
        'gt_statute_id',

        'post_code',
        'post_title',
        'gt_post_ref',

        'job_code',
        'job_title',
        'gt_job_ref',

        'organizational_unit_id',
        'organizational_position_id',

        'gt_organizational_structure_ref',
        'gt_department_ref',

        'synced_at',
        'sync_failed',
    ];

    protected $casts = [
        'synced_at'   => 'datetime',
        'sync_failed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    public function isStale(int $minutes = 15): bool
    {
        return !$this->synced_at
            || $this->synced_at->lt(now()->subMinutes($minutes));
    }

    /** آیا سمت تغییر کرده؟ */
    public function hasChanged(?object $statute): bool
    {
        if (!$statute) return false;

        return $this->gt_statute_id    !== $statute->EmployeeStatuteID
            || $this->post_title        !== $statute->PostTitle
            || $this->job_title         !== $statute->JobTitle
            || $this->gt_department_ref !== $statute->DepartmentRef;
    }

    public function organizationalPosition(): BelongsTo
    {
        return $this->belongsTo(
            OrganizationalPosition::class,
            'organizational_position_id'
        );
    }
}
