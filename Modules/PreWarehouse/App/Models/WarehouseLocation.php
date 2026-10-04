<?php

namespace Modules\PreWarehouse\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseLocation extends Model
{
    use HasFactory, SoftDeletes;

    // ✅ نام جدول به صورت صریح مشخص شود
    protected $table = 'warehouse_locations';

    protected $fillable = [
        'warehouse_id',
        'name',
        'code',
        'description',
        'section',
        'is_active',
        'description', 'is_active', 'is_quarantine', // ✅ جدید
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_quarantine' => 'boolean', // ✅ جدید
    ];

    // Relations
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeQuarantine($query)
    {
        return $query->where('is_quarantine', true);
    }
}
