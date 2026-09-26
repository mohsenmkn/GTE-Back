<?php

namespace Modules\PreWarehouse\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\App\Models\User;

class Purchase extends Model
{
    protected $table = 'pre_warehouse_purchases';
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'commercial_user_id', 'item_id', 'target_unit_id',
        'quantity', 'unit_of_measurement', 'description',
        'status', 'total_allocated_qty', 'total_received_qty',
        'available_for_allocation',
        'metadata', 'allocated_at', 'fully_received_at',
        'custodian_approved_at', 'location_assigned_at',
        'finalized_at', 'rejected_at', 'rejection_reason',
        'voucher_number', 'finalized_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'total_allocated_qty' => 'integer',
        'total_received_qty' => 'integer',
        'available_for_allocation' => 'integer',
        'metadata' => 'array',
        'allocated_at' => 'datetime',
        'fully_received_at' => 'datetime',
        'custodian_approved_at' => 'datetime',
        'location_assigned_at' => 'datetime',
        'finalized_at' => 'datetime',
        'rejected_at' => 'datetime',
        'finalized_by' => 'integer',
    ];

    // ═══════════════════════════════════════
    // Relations
    // ══════════════════════════════════════
    public function commercialUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_user_id');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
    /**
     * آیا کاربر فعلی مسئول ثبت این خرید است؟
     */
    public function isOwnedBy(int $userId): bool
    {
        return $this->commercial_user_id === $userId;
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function targetUnit(): BelongsTo
    {
        return $this->belongsTo(\Modules\HR\App\Models\OrganizationalUnit::class, 'target_unit_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    // ═══════════════════════════════════════
    // Scopes
    // ═══════════════════════════════════════
    public function scopePendingWarehouseApproval($query) {
        return $query->where('status', 'pending_warehouse_approval');
    }

    public function scopePendingCustodianApproval($query) {
        return $query->where('status', 'pending_custodian_approval');
    }

    public function scopePendingAllocation($query) {
        return $query->whereIn('status', ['pending_allocation', 'pending_reallocation']);
    }

    public function scopePendingLocationAssignment($query) {
        return $query->where('status', 'pending_location_assignment');
    }

    // ══════════════════════════════════════
    // Workflow Helpers
    // ══════════════════════════════════════

    /**
     * آیا می‌توان توسط انبار کلی تایید/رد کرد؟
     */
    public function canBeApprovedByWarehouse(): bool
    {
        return $this->status === 'pending_warehouse_approval';
    }

    /**
     * آیا می‌توان توسط متولی تایید/رد کرد؟
     */
    public function canBeApprovedByCustodian(): bool
    {
        return $this->status === 'pending_custodian_approval';
    }

    /**
     * آیا می‌توان تخصیص داد؟
     */
    public function canBeAllocated(): bool
    {
        return in_array($this->status, ['pending_allocation', 'pending_reallocation']);
    }

    /**
     * آیا می‌توان محل تعیین کرد؟
     */
    public function canAssignLocation(): bool
    {
        return $this->status === 'pending_location_assignment';
    }

    /**
     * آیا در انتظار تخصیص مجدد است؟
     */
    public function isPendingReallocation(): bool
    {
        return $this->status === 'pending_reallocation';
    }

    /**
     * آیا نهایی شده است؟
     */
    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    /**
     * آیا رد شده است؟
     */
    public function isRejected(): bool
    {
        return in_array($this->status, [
            'rejected_by_warehouse',
            'rejected_by_custodian',
        ]);
    }

    /**
     * آیا می‌توان ویرایش کرد؟
     */
    public function canEdit(): bool
    {
        return in_array($this->status, ['registered', 'pending_warehouse_approval']);
    }

    /**
     * مقدار باقیمانده برای تخصیص
     */
    public function getRemainingQtyAttribute(): int
    {
        return $this->quantity - $this->total_allocated_qty;
    }

    /**
     * پیشرفت تخصیص
     */
    public function getAllocationProgressAttribute(): float
    {
        return $this->quantity > 0
            ? round(($this->total_allocated_qty / $this->quantity) * 100, 2)
            : 0;
    }

    /**
     * برچسب وضعیت
     */
    public function getStatusLabelAttribute(): string
    {
        return [
            'registered' => 'ثبت شده',
            'pending_warehouse_approval' => 'در انتظار تایید انبار',
            'approved_by_warehouse' => 'تایید شده توسط انبار',
            'pending_custodian_approval' => 'در انتظار تایید متولی',
            'approved_by_custodian' => 'تایید شده توسط متولی',
            'pending_allocation' => 'در انتظار تخصیص',
            'allocated' => 'تخصیص داده شده',
            'pending_location_assignment' => 'در انتظار تعیین محل',
            'location_assigned' => 'محل تعیین شده',
            'finalized' => 'نهایی شده',
            'rejected_by_warehouse' => 'رد شده توسط انبار',
            'rejected_by_custodian' => 'رد شده توسط متولی',
            'rejected_by_destination' => 'رد شده توسط انبار مقصد',
            'pending_reallocation' => 'در انتظار تخصیص مجدد',
        ][$this->status] ?? $this->status;
    }

    /**
     * رنگ وضعیت برای UI
     */
    public function getStatusColorAttribute(): string
    {
        return [
            'registered' => 'bg-blue-100 text-blue-800',
            'pending_warehouse_approval' => 'bg-yellow-100 text-yellow-800',
            'approved_by_warehouse' => 'bg-green-100 text-green-800',
            'pending_custodian_approval' => 'bg-yellow-100 text-yellow-800',
            'approved_by_custodian' => 'bg-green-100 text-green-800',
            'pending_allocation' => 'bg-purple-100 text-purple-800',
            'allocated' => 'bg-purple-100 text-purple-800',
            'pending_location_assignment' => 'bg-orange-100 text-orange-800',
            'location_assigned' => 'bg-teal-100 text-teal-800',
            'finalized' => 'bg-gray-100 text-gray-800',
            'rejected_by_warehouse' => 'bg-red-100 text-red-800',
            'rejected_by_custodian' => 'bg-red-100 text-red-800',
            'rejected_by_destination' => 'bg-red-100 text-red-800',
            'pending_reallocation' => 'bg-orange-100 text-orange-800',
        ][$this->status] ?? 'bg-gray-100 text-gray-800';
    }
}
