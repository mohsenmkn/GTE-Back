<?php


namespace Modules\HR\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationalPosition extends Model
{
    protected $fillable = [
        'organizational_unit_id',
        'parent_id',

        'gt_post_ref',
        'post_code',
        'post_title',

        'gt_job_ref',
        'job_code',
        'job_title',

        'is_custom',
        'is_active',
        'sort_order',
        'description',
        'synced_at',
    ];

    protected $casts = [
        'is_custom' => 'boolean',
        'is_active' => 'boolean',
        'synced_at' => 'datetime',
    ];

    // ─────────────────────────────
    // واحد سازمانی
    // ─────────────────────────────

    public function unit(): BelongsTo
    {
        return $this->belongsTo(
            OrganizationalUnit::class,
            'organizational_unit_id'
        );
    }

    // ─────────────────────────────
    // سمت والد
    // ─────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'parent_id'
        );
    }

    // ─────────────────────────────
    // سمت‌های زیرمجموعه
    // ─────────────────────────────

    public function children(): HasMany
    {
        return $this->hasMany(
            self::class,
            'parent_id'
        )->orderBy('sort_order');
    }

    // ─────────────────────────────
    // پرسنل این سمت
    // ─────────────────────────────

    public function employees(): HasMany
    {
        return $this->hasMany(
            EmployeePosition::class,
            'organizational_position_id'
        );
    }

    // ─────────────────────────────
    // سمت‌های ریشه
    // ─────────────────────────────

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    // ─────────────────────────────
    // سمت‌های فعال
    // ─────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
