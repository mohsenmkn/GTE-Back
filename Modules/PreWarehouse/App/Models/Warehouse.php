<?php

namespace Modules\PreWarehouse\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\App\Models\User;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'address',
        'phone',
        'manager_id',
        'is_active',
        'quarantine_location_id', // ✅ جدید
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relations
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helpers
    public function getFullDescriptionAttribute(): string
    {
        return $this->name . ($this->description ? ' - ' . $this->description : '');
    }

     // Helpers
    /**
     * آیا قرنطینه برای این انبار تعریف شده است؟
     */
    public function hasQuarantine(): bool
    {
        return $this->quarantine_location_id !== null;
    }

    /**
     * ایجاد خودکار قرنطینه اگر وجود نداشته باشد
     */
    public function ensureQuarantine(): WarehouseLocation
    {
        if ($this->hasQuarantine()) {
            return $this->quarantineLocation;
        }
        // ایجاد محل قرنطینه
        $quarantine = WarehouseLocation::create([
            'warehouse_id' => $this->id,
            'name' => "قرنطینه - {$this->name}",
            'code' => "QUARANTINE-{$this->code}",
            'section' => 'قرنطینه',
            'description' => "محل قرنطینه انبار {$this->name}",
            'is_quarantine' => true,
            'is_active' => true,
        ]);

        // اتصال به انبار
        $this->update(['quarantine_location_id' => $quarantine->id]);

        return $quarantine->fresh();
    }


    public function quarantineLocation(): BelongsTo
    {
        return $this->belongsTo(
            WarehouseLocation::class,
            'quarantine_location_id'
        );
    }


}
