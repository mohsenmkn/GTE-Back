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
    use HasFactory, SoftDeletes;

    protected $table = 'pre_warehouse_purchases';

    protected $fillable = [
        'commercial_user_id', 'item_id', 'target_unit_id',
        'quantity', 'unit_of_measurement', 'description',
        'status', 'total_allocated_qty', 'total_received_qty',
        'available_for_allocation',
        'metadata', 'allocated_at', 'fully_received_at',
        'custodian_approved_at', 'location_assigned_at',
        'finalized_at', 'rejected_at', 'rejection_reason',
        'voucher_number', 'finalized_by',
        'supplier', 'brand', 'item_code',
        // ✅ فیلدهای جدید
        'warehouse_receipt_number',
        'voucher_entered_by',
        'receipt_entered_by',
        'voucher_entered_at',
        'receipt_entered_at',

        // برگشت خرید
        'warehouse_return_scheduled_at',
        'warehouse_return_scheduled_by',
        'commercial_received_at',
        'commercial_received_by',
        'supplier_returned_at',
        'supplier_returned_by',
        'return_notes',
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
        'voucher_entered_at' => 'datetime',
        'receipt_entered_at' => 'datetime',
        // برگشت خرید
        'warehouse_return_scheduled_at' => 'datetime',
        'commercial_received_at' => 'datetime',
        'supplier_returned_at' => 'datetime',
    ];

    // ═══════════════════════════════════════
    // Relations
    // ═══════════════════════════════════════
    public function commercialUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_user_id');
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

    public function voucherEnteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voucher_entered_by');
    }

    public function receiptEnteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receipt_entered_by');
    }

    // ═══════════════════════════════════════
    // Workflow Helpers - جدید
    // ═══════════════════════════════════════

    /**
     * ✅ جدید: آیا می‌توان توسط متولی تایید نهایی شد؟ (بعد از تخصیص)
     */
    public function canBeFinalApprovedByCustodian(): bool
    {
        return $this->status === 'pending_custodian_final_approval';
    }

    /**
     * ✅ جدید: آیا می‌توان شماره حواله وارد کرد؟
     */
    public function canEnterVoucher(): bool
    {
        return $this->status === 'pending_commercial_voucher';
    }

    /**
     * ✅ جدید: آیا می‌توان شماره رسید انبار وارد کرد؟
     */
    public function canEnterWarehouseReceipt(): bool
    {
        return $this->status === 'pending_warehouse_receipt';
    }

    public function isFullyReceived(): bool
    {
        return in_array($this->status, ['pending_final_allocation', 'fully_received'], true);
    }

    public function isRejected(): bool
    {
        return in_array($this->status, [
            'rejected_by_warehouse',
            'rejected_by_custodian',
        ]);
    }

    public function canEdit(): bool
    {
        return in_array($this->status, ['registered', 'pending_warehouse_approval']);
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->commercial_user_id === $userId;
    }

    public function getRemainingQtyAttribute(): int
    {
        return $this->quantity - $this->total_allocated_qty;
    }

    public function getAllocationProgressAttribute(): float
    {
        return $this->quantity > 0
            ? round(($this->total_allocated_qty / $this->quantity) * 100, 2)
            : 0;
    }


    // اضافه کردن relation:
    public function temporaryExits(): HasMany
    {
        return $this->hasMany(TemporaryExit::class);
    }

// اضافه کردن helper:
    public function getTotalTemporaryExitAttribute(): int
    {
        return $this->temporaryExits()->sum('quantity');
    }


    /**
     * آیا می‌توان توسط انبار کلی تایید/رد کرد؟
     */
    public function canBeApprovedByWarehouse(): bool
    {
        return $this->status === 'pending_warehouse_approval';
    }

    /**
     * ✅ جدید: آیا می‌توان توسط متولی تایید/رد کرد؟ (بعد از تایید انبار)
     */
    public function canBeApprovedByCustodian(): bool
    {
        return $this->status === 'pending_custodian_approval';
    }

    /**
     * آیا می‌توان تخصیص داد؟ (بعد از تایید متولی)
     */
    public function canBeAllocated(): bool
    {
        return in_array($this->status, ['pending_allocation', 'pending_reallocation']);
    }

    /**
     * آیا در انتظار تخصیص مجدد است؟
     */
    public function isPendingReallocation(): bool
    {
        return $this->status === 'pending_reallocation';
    }

    /**
     * برچسب وضعیت به‌روزرسانی‌شده
     */

    public function getStatusLabelAttribute(): string
    {
        return [
            'registered' => 'ثبت شده',
            'pending_warehouse_approval' => 'در انتظار تایید انبار',
            'approved_by_warehouse' => 'تایید شده توسط انبار',
            'pending_custodian_approval' => 'در انتظار تایید متولی',
            'pending_allocation' => 'در انتظار تخصیص',
            'allocated' => 'تخصیص داده شده',
            'in_quarantine' => 'در قرنطینه',  // ✅ جدید
            'pending_commercial_voucher' => 'در انتظار حواله بازرگانی',
            'voucher_entered' => 'حواله وارد شد',
            'pending_warehouse_receipt' => 'در انتظار رسید انبار',
            'receipt_entered' => 'رسید انبار وارد شد',
            'pending_final_allocation' => 'در انتظار تخصیص نهایی انبار',
            'fully_received' => 'دریافت و تخصیص نهایی شد',
            'rejected_by_warehouse' => 'رد شده توسط انبار',
            'rejected_by_custodian' => 'رد شده توسط متولی',
            'rejected_by_destination' => 'رد شده توسط انبار مقصد',
            'pending_reallocation' => 'در انتظار تخصیص مجدد',
            'pending_warehouse_return' => 'در انتظار تعیین تاریخ تحویل به بازرگانی',
            'warehouse_return_scheduled' => 'تاریخ تحویل به بازرگانی تعیین شد',
            'commercial_received' => 'تحویل بازرگانی شد',
            'supplier_returned' => 'به تأمین‌کننده برگشت داده شد',
        ][$this->status] ?? $this->status;
    }

    /**
     * رنگ وضعیت به‌روزرسانی‌شده
     */
    public function getStatusColorAttribute(): string
    {
        return [
            'registered' => 'bg-blue-100 text-blue-800',
            'pending_warehouse_approval' => 'bg-yellow-100 text-yellow-800',
            'approved_by_warehouse' => 'bg-green-100 text-green-800',
            'pending_custodian_approval' => 'bg-orange-100 text-orange-800',
            'pending_allocation' => 'bg-purple-100 text-purple-800',
            'allocated' => 'bg-indigo-100 text-indigo-800',
            'in_quarantine' => 'bg-orange-100 text-orange-800',  // ✅ جدید
            'pending_commercial_voucher' => 'bg-cyan-100 text-cyan-800',
            'voucher_entered' => 'bg-teal-100 text-teal-800',
            'pending_warehouse_receipt' => 'bg-amber-100 text-amber-800',
            'receipt_entered' => 'bg-lime-100 text-lime-800',
            'pending_final_allocation' => 'bg-orange-100 text-orange-800',
            'fully_received' => 'bg-gray-800 text-white',
            'rejected_by_warehouse' => 'bg-red-100 text-red-800',
            'rejected_by_custodian' => 'bg-rose-100 text-rose-800',
            'rejected_by_destination' => 'bg-pink-100 text-pink-800',
            'pending_reallocation' => 'bg-yellow-100 text-yellow-800',
            'pending_warehouse_return' => 'bg-amber-100 text-amber-800',
            'warehouse_return_scheduled' => 'bg-cyan-100 text-cyan-800',
            'commercial_received' => 'bg-indigo-100 text-indigo-800',
            'supplier_returned' => 'bg-green-100 text-green-800',
        ][$this->status] ?? 'bg-gray-100 text-gray-800';
    }



    /**
     * آیا خرید در فرآیند برگشت به تأمین‌کننده است؟
     */
    public function isInReturnProcess(): bool
    {
        return in_array($this->status, [
            'rejected_by_custodian',
            'pending_warehouse_return',
            'warehouse_return_scheduled',
            'commercial_received',
            'supplier_returned',
        ], true);
    }

    /**
     * آیا انبار می‌تواند تاریخ تحویل به بازرگانی را تعیین کند؟
     */
    public function canScheduleWarehouseReturn(): bool
    {
        return $this->status === 'rejected_by_custodian';
    }

    /**
     * آیا بازرگانی می‌تواند تحویل گرفتن کالا را ثبت کند؟
     */
    public function canConfirmCommercialReceived(): bool
    {
        return $this->status === 'warehouse_return_scheduled';
    }

    /**
     * آیا بازرگانی می‌تواند برگشت به تأمین‌کننده را ثبت کند؟
     */
    public function canConfirmSupplierReturned(): bool
    {
        return $this->status === 'commercial_received';
    }


    //برگشت
    public function warehouseReturnScheduledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'warehouse_return_scheduled_by'
        );
    }

    public function commercialReceivedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'commercial_received_by'
        );
    }

    public function supplierReturnedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'supplier_returned_by'
        );
    }



}
