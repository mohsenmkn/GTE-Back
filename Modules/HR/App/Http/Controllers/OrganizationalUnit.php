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

    // ── سلسله مراتب ──────────────────────────

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
