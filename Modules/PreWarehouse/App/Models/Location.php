<?php

namespace Modules\PreWarehouse\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use HasFactory, SoftDeletes;

    // ✅ نام جدول به صورت صریح مشخص شود
    protected $table = 'pre_warehouse_locations';

    protected $fillable = [
        'allocation_id',
        'warehouse_id',
        'warehouse_location_id',
        'location_name', // ✅ توجه: location_name نه name
        'description',
        'section_code',
        'assigned_qty',
    ];

    protected $casts = [
        'assigned_qty' => 'integer',
    ];

    // Relations
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class);
    }
}
