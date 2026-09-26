<?php
// Modules/Inventory/App/Models/Material.php

namespace Modules\Warehouse\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Company\App\Models\Company;

class Material extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'category_id',
        'code',
        'name',
        'unit',
        'unit_price',
        'current_stock',
        'min_stock',
        'max_stock',
        'description',
        'status',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'current_stock' => 'decimal:3',
        'min_stock' => 'decimal:3',
        'max_stock' => 'decimal:3',
    ];

    // Relations
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class);
    }

    public function transactions()
    {
        return $this->hasMany(WarehouseTransaction::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('current_stock', '<=', 'min_stock');
    }

    // Helper
    public function updateStock($quantity, $type)
    {
        if ($type === 'purchase' || $type === 'return') {
            $this->current_stock += $quantity;
        } else if ($type === 'sale' || $type === 'transfer' || $type === 'damage') {
            $this->current_stock -= $quantity;
        }
        $this->save();
    }

    public function getStatusLabelAttribute()
    {
        return $this->status === 'active' ? 'فعال' : 'غیرفعال';
    }
}
