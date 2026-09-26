<?php
// Modules/Inventory/App/Models/InventoryCategory.php

namespace Modules\Warehouse\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Company\App\Models\Company;

class InventoryCategory extends Model
{
    use SoftDeletes;

    protected $table = 'inventory_categories';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'parent_id',
        'description',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ============ Relations ============

    /**
     * رابطه با شرکت
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * رابطه با دسته‌بندی والد
     */
    public function parent()
    {
        return $this->belongsTo(InventoryCategory::class, 'parent_id');
    }

    /**
     * رابطه با دسته‌بندی‌های فرزند
     */
    public function children()
    {
        return $this->hasMany(InventoryCategory::class, 'parent_id');
    }

    /**
     * رابطه با کالاها
     */
    public function materials()
    {
        return $this->hasMany(Material::class, 'category_id');
    }

    // ============ Scopes ============

    /**
     * اسکوپ دسته‌بندی‌های فعال
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * اسکوپ دسته‌بندی‌های ریشه (بدون والد)
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * اسکوپ بر اساس شرکت
     */
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // ============ Accessors ============

    /**
     * دریافت برچسب وضعیت
     */
    public function getStatusLabelAttribute()
    {
        return $this->status === 'active' ? 'فعال' : 'غیرفعال';
    }

    /**
     * دریافت برچسب وضعیت برای نمایش در Tag
     */
    public function getStatusSeverityAttribute()
    {
        return $this->status === 'active' ? 'success' : 'secondary';
    }

    /**
     * دریافت مسیر کامل درخت
     */
    public function getFullPathAttribute()
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' / ', $path);
    }

    // ============ Helper Methods ============

    /**
     * بررسی وجود زیرمجموعه
     */
    public function hasChildren()
    {
        return $this->children()->exists();
    }

    /**
     * بررسی وجود کالا
     */
    public function hasMaterials()
    {
        return $this->materials()->exists();
    }

    /**
     * دریافت تعداد کل زیرمجموعه‌ها (recursive)
     */
    public function getDescendantCount()
    {
        $count = 0;
        foreach ($this->children as $child) {
            $count += 1 + $child->getDescendantCount();
        }
        return $count;
    }

    // ============ Boot ============

    protected static function boot()
    {
        parent::boot();

        // قبل از حذف، بررسی کنید که زیرمجموعه یا کالا نداشته باشد
        static::deleting(function ($category) {
            if ($category->hasChildren() || $category->hasMaterials()) {
                return false;
            }
        });
    }
}
