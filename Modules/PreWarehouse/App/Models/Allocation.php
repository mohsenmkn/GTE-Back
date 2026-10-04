<?php

namespace Modules\PreWarehouse\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasOne; // ✅ اضافه کنید

class Allocation extends Model
{

    use HasFactory, SoftDeletes;

    protected $table = 'pre_warehouse_allocations'; // ✅

    protected $fillable = [
        'purchase_id', 'warehouse_id', 'allocated_qty', 'received_qty',
        'status', 'received_by', 'received_at', 'receive_notes',
        'rejection_reason', 'rejected_by', 'rejected_at',
        'temporary_exit_qty',  // ✅ جدید

    ];

    protected $casts = [
        'allocated_qty' => 'integer',
        'received_qty' => 'integer',
        'received_at' => 'datetime',
        'rejected_at' => 'datetime',
        'temporary_exit_qty' => 'integer',  // ✅ جدید
    ];

    // Relations
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }


    // اضافه کردن relation جدید:
    public function temporaryExits(): HasMany
    {
        return $this->hasMany(TemporaryExit::class);
    }

// اضافه کردن helper:
    public function getRemainingInQuarantineAttribute(): int
    {
        return $this->allocated_qty - $this->temporary_exit_qty - $this->received_qty;
    }

    public function getTotalTemporaryExitAttribute(): int
    {
        return $this->temporaryExits()->sum('quantity');
    }



    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    // Helpers
    public function getRemainingQtyAttribute(): int
    {
        return $this->allocated_qty - $this->received_qty;
    }

    public function isFullyReceived(): bool { return $this->status === 'fully_received'; }
    public function isPending(): bool { return $this->status === 'pending'; }
    public function isRejected(): bool { return in_array($this->status, ['rejected', 'rejected_by_destination']); }

    public function getStatusLabelAttribute(): string
    {
        return [
            'pending' => 'در انتظار',
            'partially_received' => 'دریافت جزئی',
            'fully_received' => 'دریافت کامل',
            'rejected' => 'رد شده',
            'rejected_by_destination' => 'رد شده توسط مقصد',
            'location_assigned' => 'محل تعیین شده',  // ✅ اضافه شد

            'in_quarantine' => 'در قرنطینه',  // ✅ جدید

        ][$this->status] ?? $this->status;
    }

    public function location(): HasOne
    {
        return $this->hasOne(Location::class);
    }

    /**
     * رنگ وضعیت
     */
    public function getStatusColorAttribute(): string
    {
        return [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'partially_received' => 'bg-orange-100 text-orange-800',
            'fully_received' => 'bg-green-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800',
            'rejected_by_destination' => 'bg-pink-100 text-pink-800',
            'location_assigned' => 'bg-teal-100 text-teal-800',  // ✅ اضافه شد
            'in_quarantine' => 'bg-orange-100 text-orange-800',  // ✅ جدید
        ][$this->status] ?? 'bg-gray-100 text-gray-800';
    }
}
