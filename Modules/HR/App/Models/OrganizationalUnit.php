<?php

namespace Modules\HR\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationalUnit extends Model
{
    protected $fillable = [
        'gt_department_id',
        'code',
        'title',
        'parent_id',
        'parent_position_id', // ✅ اضافه شده در مرحله ۱
        'path',
        'level',
        'is_active',
        'synced_at',
        'is_custom',
        'sort_order',
        'description',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'is_active' => 'boolean',
        'is_custom' => 'boolean',
    ];

    // ── سلسله مراتب واحدها ──────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** همه فرزندان مستقیم فعال */
    public function directChildren(): HasMany
    {
        return $this->children()->where('is_active', true);
    }

    // ✅ رابطه جدید: والد از نوع سمت
    public function parentPosition(): BelongsTo
    {
        return $this->belongsTo(OrganizationalPosition::class, 'parent_position_id');
    }

    /**
     * ✅ متد کمکی: دریافت والد (چه واحد چه سمت)
     */
    public function parentEntity(): Model|null
    {
        if ($this->parent_id) {
            return $this->parent;
        }

        if ($this->parent_position_id) {
            return $this->parentPosition;
        }

        return null;
    }

    // ✅ رابطه جدید: سمت‌های این واحد
    public function positions(): HasMany
    {
        return $this->hasMany(OrganizationalPosition::class, 'organizational_unit_id');
    }

    /** همه زیرمجموعه‌ها (همه سطوح) با استفاده از path */
    public function allDescendants()
    {
        return self::where('path', 'LIKE', $this->path . '%')
            ->where('id', '!=', $this->id)
            ->orderBy('path');
    }

    /** آیا این واحد زیرمجموعه واحد دیگر است؟ */
    public function isDescendantOf(OrganizationalUnit $ancestor): bool
    {
        return str_starts_with($this->path, $ancestor->path);
    }

    /** همه اجداد از ریشه تا این واحد */
    public function ancestors()
    {
        $ids = array_filter(explode('/', $this->path ?? ''));
        return self::whereIn('id', $ids)
            ->where('id', '!=', $this->id)
            ->orderBy('path');
    }

    /** breadcrumb: ["شرکت", "معاونت", "اداره"] */
    public function getBreadcrumbAttribute(): array
    {
        if (empty($this->path)) {
            return [$this->title];
        }

        return $this->ancestors()
            ->pluck('title')
            ->push($this->title)
            ->toArray();
    }

    // ── کارکنان ──────────────────────────

    public function employees(): HasMany
    {
        return $this->hasMany(EmployeePosition::class);
    }
}
