<?php
// Modules/WBS/Entities/WbsItem.php

namespace Modules\WBS\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Contract\App\Models\Contract;
use Modules\Document\App\Models\Document;
use Modules\Project\App\Models\Project;
use Modules\WBS\App\Models\Task;

class WbsItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'parent_id',
        'code',
        'name',
        'category',
        'unit',
        'quantity',
        'unit_price',
        'total_price',
        'weight',
        'progress_percent',
        'status',
        'description',
    ];

    public function setParentIdAttribute($value)
    {
        $this->attributes['parent_id'] = $value ?: null;
    }
    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'progress_percent' => 'decimal:2',
    ];

    public function calculateProgress(): float
    {
        // ✅ اگر تسک دارد، از میانگین تسک‌ها محاسبه کن
        if ($this->tasks()->exists()) {
            return $this->tasks()->avg('progress_percent') ?? 0;
        }

        // ✅ اگر فرزند دارد، از میانگین فرزندان محاسبه کن
        if ($this->children()->exists()) {
            return $this->children()->avg('progress_percent') ?? 0;
        }

        // ✅ اگر هیچکدام ندارد، همان مقدار خودش را برگردان
        return $this->progress_percent ?? 0;
    }

// ✅ متد برای به‌روزرسانی خودکار
    public function updateProgress(): void
    {
        $calculated = $this->calculateProgress();
        $this->update(['progress_percent' => $calculated]);
    }

// ✅ به‌روزرسانی recursive (بالا به پایین)
    public function updateProgressRecursive(): void
    {
        // اول فرزندان را به‌روز کن
        foreach ($this->children as $child) {
            $child->updateProgressRecursive();
        }

        // سپس خودش را به‌روز کن
        $this->updateProgress();
    }

    // Relations
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function parent()
    {
        return $this->belongsTo(WbsItem::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(WbsItem::class, 'parent_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function contracts()
    {
        return $this->belongsToMany(Contract::class, 'contract_wbs')
            ->withPivot('quantity', 'unit_price', 'total_price')
            ->withTimestamps();
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    // Helper methods
    public function isRoot()
    {
        return is_null($this->parent_id);
    }

    public function hasChildren()
    {
        return $this->children()->exists();
    }

    public function getFullPathAttribute()
    {
        $path = [$this->code];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->code);
            $parent = $parent->parent;
        }

        return implode('.', $path);
    }

    // ✅ محاسبه درصد پیشرفت
    public function getEffectiveProgressAttribute(): float
    {
        // اگر تسک دارد
        if ($this->tasks()->exists()) {
            return (float) $this->tasks()->avg('progress_percent') ?? 0;
        }

        // اگر فرزند دارد
        if ($this->children()->exists()) {
            return (float) $this->children()->avg('progress_percent') ?? 0;
        }

        // مقدار دستی
        return (float) ($this->progress_percent ?? 0);
    }

        // ✅ آیا درصد به صورت خودکار محاسبه میشه؟
    public function getIsAutoProgressAttribute(): bool
    {
        return $this->tasks()->exists() || $this->children()->exists();
    }
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
