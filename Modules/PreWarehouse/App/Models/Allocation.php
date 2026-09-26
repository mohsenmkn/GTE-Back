<?php

namespace Modules\PreWarehouse\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\App\Models\User;

class Allocation extends Model
{

    use HasFactory, SoftDeletes;

    protected $table = 'pre_warehouse_allocations'; // ✅

    protected $fillable = [
        'purchase_id', 'warehouse_id', 'allocated_qty', 'received_qty',
        'status', 'received_by', 'received_at', 'receive_notes',
        'rejection_reason', 'rejected_by', 'rejected_at',
    ];

    protected $casts = [
        'allocated_qty' => 'integer',
        'received_qty' => 'integer',
        'received_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    // Relations
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
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
        ][$this->status] ?? $this->status;
    }
}
