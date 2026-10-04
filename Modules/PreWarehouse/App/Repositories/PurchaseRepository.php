<?php

namespace Modules\PreWarehouse\App\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\PreWarehouse\App\Models\Allocation;
use Modules\PreWarehouse\App\Models\Approval;
use Modules\PreWarehouse\App\Models\Location;
use Modules\PreWarehouse\App\Models\Purchase;
use Modules\PreWarehouse\App\Models\TemporaryExit;
use Modules\PreWarehouse\App\Repositories\Contracts\PurchaseRepositoryInterface;

class PurchaseRepository implements PurchaseRepositoryInterface
{
    public function getAll(array $filters = [], ?int $userId = null)
    {
        $query = Purchase::with([
            'commercialUser.employeePosition.unit',
            'item',
            'targetUnit',
            'allocations.warehouse',
        ]);

        // ═══════════════════════════════════════════
        // فیلتر هوشمند بر اساس نقش کاربر
        // ═══════════════════════════════════════════
        if ($userId) {
            $user = \Modules\Auth\App\Models\User::with(['roles', 'employeePosition.unit'])->find($userId);

            if ($user) {
                // بررسی اینکه آیا کاربر نقش "متولی" دارد
                $isCustodian = $user->hasRole('unit_custodian');

                // بررسی اینکه آیا کاربر دسترسی view_all دارد (برای اطمینان)
                $canViewAll = $user->can('pre_warehouse.view_all');

                // ══════ منطق اصلی ═══════
                // فقط متولیان محدود به واحد خود هستند
                // سایر نقش‌ها (انبار، بازرگانی، مدیر) همه را می‌بینند
                if ($isCustodian && !$canViewAll) {
                    $userUnitId = $user->employeePosition?->organizational_unit_id;

                    if ($userUnitId) {
                        // متولی فقط خریدهای واحد خودش را می‌بیند
                        $query->where('target_unit_id', $userUnitId);
                    } else {
                        // اگر متولی واحد سازمانی ندارد، هیچ خریدی نمی‌بیند
                        $query->whereRaw('1 = 0');
                    }
                }
                // در غیر این صورت (انبار، بازرگانی، مدیر) → بدون فیلتر، همه خریدها
            }
        }

        // ═══════════════════════════════════════════
        // سایر فیلترها (بدون تغییر)
        // ═══════════════════════════════════════════
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['commercial_user_id'])) {
            $query->where('commercial_user_id', $filters['commercial_user_id']);
        }
        if (!empty($filters['target_unit_id'])) {
            $query->where('target_unit_id', $filters['target_unit_id']);
        }
        if (!empty($filters['item_id'])) {
            $query->where('item_id', $filters['item_id']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('item', fn($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    public function findById(int $id)
    {
        return Purchase::with([
            'commercialUser.employeePosition.unit',
            'item',
            'targetUnit',

            'allocations.warehouse',
            'allocations.warehouse.quarantineLocation',
            'allocations.temporaryExits.registeredBy',
            'allocations.locations.warehouseLocation',

            'approvals.approver',
            'auditLogs.user',

            'voucherEnteredBy',
            'receiptEnteredBy',

            // برگشت
            'warehouseReturnScheduledBy',
            'commercialReceivedBy',
            'supplierReturnedBy',
        ])->findOrFail($id);
    }

    public function create(array $data)
    {
        return Purchase::create($data);
    }

    public function update(int $id, array $data)
    {
        $purchase = Purchase::findOrFail($id);
        $purchase->update($data);
        return $purchase->fresh();
    }

    /**
     * رد توسط انبار کلی
     */
    public function rejectByWarehouse(int $purchaseId, string $reason, int $userId)
    {
        return DB::transaction(function () use ($purchaseId, $reason, $userId) {
            $purchase = Purchase::findOrFail($purchaseId);

            if (!$purchase->canBeApprovedByWarehouse()) {
                throw new \Exception('این خرید در وضعیت تایید انبار نیست');
            }

            Approval::updateOrCreate(
                ['purchase_id' => $purchaseId, 'approver_type' => 'warehouse_manager'],
                [
                    'approver_id' => $userId,
                    'status' => 'rejected',
                    'rejection_reason' => $reason,
                    'rejected_at' => now(),
                ]
            );

            $purchase->update([
                'status' => 'rejected_by_warehouse',
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return $purchase->fresh();
        });
    }



    /**
     * تخصیص به انبارها
     */
    /**
     * تخصیص کالا به انبارها (با انتقال خودکار به قرنطینه)
     */
    public function allocateWarehouses(int $purchaseId, array $allocations)
    {
        return DB::transaction(function () use ($purchaseId, $allocations) {
            $purchase = Purchase::findOrFail($purchaseId);

            if (!$purchase->canBeAllocated()) {
                throw new \Exception('این خرید قابل تخصیص نیست');
            }

            // حذف تخصیص‌های قبلی (اگر reallocating هستیم)
            if ($purchase->isPendingReallocation()) {
                $purchase->allocations()->where('status', 'rejected_by_destination')->delete();
            }

            $totalNewAllocation = array_sum(array_column($allocations, 'allocated_qty'));
            $newTotalAllocated = $purchase->total_allocated_qty + $totalNewAllocation;

            if ($newTotalAllocated > $purchase->quantity) {
                throw new \Exception('مجموع تخصیص‌ها نمی‌تواند بیشتر از مقدار کل باشد');
            }


            foreach ($allocations as $allocation) {
                $warehouse = \Modules\PreWarehouse\App\Models\Warehouse::findOrFail(
                    $allocation['warehouse_id']
                );

                // اطمینان از وجود محل قرنطینه
                $quarantine = $warehouse->ensureQuarantine();

                // ابتدا ایجاد تخصیص و دریافت شناسه آن
                $createdAllocation = Allocation::create([
                    'purchase_id'   => $purchaseId,
                    'warehouse_id'  => $warehouse->id,
                    'allocated_qty' => $allocation['allocated_qty'],
                    'status'        => 'in_quarantine',
                ]);

                // ثبت محل قرنطینه با اتصال به تخصیص واقعی
                Location::create([
                    'allocation_id'        => $createdAllocation->id,
                    'warehouse_id'         => $warehouse->id,
                    'warehouse_location_id'=> $quarantine->id,
                    'location_name'        => "قرنطینه - {$warehouse->name}",
                    'section_code'         => 'QUARANTINE',
                    'assigned_qty'         => $allocation['allocated_qty'],
                    'description'          => "کالا به صورت خودکار به قرنطینه انبار {$warehouse->name} منتقل شد",
                ]);
            }

            $purchase->update([
                'total_allocated_qty' => $newTotalAllocated,
                'available_for_allocation' => $purchase->quantity - $newTotalAllocated,
                'status' => $newTotalAllocated >= $purchase->quantity
                    ? 'pending_custodian_approval'  // ✅ بعد از تخصیص → تایید متولی
                    : 'allocated',
                'allocated_at' => now(),
            ]);

            return $purchase->fresh();
        });
    }

    /**
     * رد توسط انبار مقصد (برگشت به استخر تخصیص)
     */
    public function rejectByDestination(int $allocationId, string $reason, ?int $userId)
    {
        return DB::transaction(function () use ($allocationId, $reason, $userId) {
            $allocation = Allocation::findOrFail($allocationId);
            $purchase = $allocation->purchase;

            $allocation->update([
                'status' => 'rejected_by_destination',
                'rejection_reason' => $reason,
                'rejected_by' => $userId,
                'rejected_at' => now(),
            ]);

            // کاهش total_allocated_qty و افزایش available_for_allocation
            $newTotalAllocated = $purchase->allocations()
                ->where('status', '!=', 'rejected_by_destination')
                ->sum('allocated_qty');

            $newAvailable = $purchase->quantity - $newTotalAllocated;

            // بررسی وضعیت purchase
            $hasActiveAllocations = $purchase->allocations()
                ->where('status', '!=', 'rejected_by_destination')
                ->exists();

            $newStatus = $hasActiveAllocations ? 'allocated' : 'pending_reallocation';

            $purchase->update([
                'total_allocated_qty' => $newTotalAllocated,
                'available_for_allocation' => $newAvailable,
                'status' => $newStatus,
            ]);

            return $allocation->fresh();
        });
    }

    /**
     * تعیین محل نگهداری
     */
    /**
     * تعیین محل نگهداری برای یک تخصیص
     */
    /**
     * تعیین محل نگهداری برای یک تخصیص
     */
    public function assignLocation(int $allocationId, array $locationData)
    {
        return DB::transaction(function () use ($allocationId, $locationData) {
            $allocation = Allocation::with('purchase')->findOrFail($allocationId);

            // تخصیص نهایی بعد از رسید حواله و رسید انبار: مقصد و محل نگهداری تعیین می‌شود.
            if ($allocation->purchase->status === 'pending_final_allocation') {
                $warehouse = \Modules\PreWarehouse\App\Models\Warehouse::findOrFail($locationData['warehouse_id']);
                $warehouseLocation = \Modules\PreWarehouse\App\Models\WarehouseLocation::where('warehouse_id', $warehouse->id)
                    ->where('id', $locationData['warehouse_location_id'] ?? 0)
                    ->where('is_active', true)
                    ->first();
                if (!$warehouseLocation) {
                    throw new \Exception('محل انتخاب‌شده متعلق به انبار مقصد نیست یا غیرفعال است');
                }
                $remainingQty = (int) $allocation->allocated_qty - (int) ($allocation->temporary_exit_qty ?? 0);
                if ((int) ($locationData['assigned_qty'] ?? 0) !== $remainingQty) {
                    throw new \Exception("مقدار ثبت‌شده باید برابر با باقیمانده کالا ({$remainingQty}) باشد");
                }
                $allocation->update([
                    'warehouse_id' => $warehouse->id,
                    'status' => 'location_assigned',
                ]);
                $allocation->locations()->delete();
                if ($remainingQty > 0) {
                    Location::create([
                        'allocation_id' => $allocationId,
                        'warehouse_id' => $warehouse->id,
                        'warehouse_location_id' => $warehouseLocation->id,
                        'location_name' => $warehouseLocation->name,
                        'section_code' => $warehouseLocation->code,
                        'assigned_qty' => $remainingQty,
                        'description' => $locationData['description'] ?? null,
                    ]);
                }
                $purchase = $allocation->purchase()->first();
                $unfinished = $purchase->allocations()
                    ->whereRaw('allocated_qty > COALESCE(temporary_exit_qty, 0)')
                    ->where('status', '!=', 'location_assigned')
                    ->exists();
                if (!$unfinished) {
                    $purchase->update([
                        'status' => 'fully_received',
                        'fully_received_at' => now(),
                        'location_assigned_at' => now(),
                    ]);
                }
                return $allocation->fresh(['locations.warehouseLocation', 'warehouse', 'purchase']);
            }

            if ($allocation->status !== 'pending') {
                throw new \Exception('این تخصیص در وضعیت تعیین محل نیست');
            }

            // ایجاد رکورد محل
            Location::create([
                'allocation_id' => $allocationId,
                'warehouse_id' => $allocation->warehouse_id,
                'warehouse_location_id' => $locationData['warehouse_location_id'] ?? null,
                'location_name' => $locationData['location_name'] ?? null,
                'section_code' => $locationData['section_code'] ?? null,
                'assigned_qty' => $locationData['assigned_qty'] ?? $allocation->allocated_qty,
                'description' => $locationData['description'] ?? null,
            ]);

            // ✅ به‌روزرسانی وضعیت allocation به location_assigned
            $allocation->update([
                'status' => 'location_assigned',
            ]);

            // بررسی تکمیل محل‌ها
            $purchase = $allocation->purchase;
            $allAllocationsHaveLocation = $purchase->allocations()
                    ->where('status', '!=', 'rejected_by_destination')
                    ->whereDoesntHave('locations')
                    ->count() === 0;

            if ($allAllocationsHaveLocation) {
                // ✅ تغییر وضعیت purchase به pending_commercial_voucher (نه location_assigned)
                $purchase->update([
                    'status' => 'pending_commercial_voucher',
                    'location_assigned_at' => now(),
                ]);
            }

            return $allocation->fresh(['location', 'warehouse']);
        });
    }

    /**
     * نهایی‌سازی
     */
    /**
     * نهایی‌سازی خرید با شماره حواله
     */
    public function finalizePurchase(int $purchaseId, int $userId, string $voucherNumber)
    {
        return DB::transaction(function () use ($purchaseId, $userId, $voucherNumber) {
            $purchase = Purchase::findOrFail($purchaseId);

            if ($purchase->status !== 'location_assigned') {
                throw new \Exception('برای نهایی‌سازی، ابتدا باید محل نگهداری همه تخصیص‌ها مشخص شود');
            }

            // ✅ بررسی یکتا بودن شماره حواله
            $exists = Purchase::where('voucher_number', $voucherNumber)
                ->where('id', '!=', $purchaseId)
                ->exists();

            if ($exists) {
                throw new \Exception("شماره حواله '{$voucherNumber}' قبلاً ثبت شده است");
            }

            $purchase->update([
                'status' => 'finalized',
                'finalized_at' => now(),
                'voucher_number' => $voucherNumber,
                'finalized_by' => $userId,
            ]);

            return $purchase->fresh();
        });
    }


    /**
     * ✅ تایید نهایی متولی (بعد از تخصیص - رویت کالا)
     */
    public function finalApproveByCustodian(int $purchaseId, int $userId, ?string $notes)
    {
        return DB::transaction(function () use ($purchaseId, $userId, $notes) {
            $purchase = Purchase::findOrFail($purchaseId);

            if (!$purchase->canBeFinalApprovedByCustodian()) {
                throw new \Exception('این خرید در وضعیت تایید نهایی متولی نیست');
            }

            Approval::updateOrCreate(
                ['purchase_id' => $purchaseId, 'approver_type' => 'custodian'],
                [
                    'approver_id' => $userId,
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approval_notes' => $notes,
                ]
            );

            $purchase->update([
                'status' => 'pending_commercial_voucher',
                'custodian_approved_at' => now(),
            ]);

            return $purchase->fresh();
        });
    }

    /**
     * تایید توسط انبار کلی → مستقیماً می‌رود به pending_allocation
     */
    public function approveByWarehouse(int $purchaseId, int $userId, ?string $notes)
    {
        return DB::transaction(function () use ($purchaseId, $userId, $notes) {
            $purchase = Purchase::findOrFail($purchaseId);

            if (!$purchase->canBeApprovedByWarehouse()) {
                throw new \Exception('این خرید در وضعیت تایید انبار نیست');
            }

            Approval::updateOrCreate(
                ['purchase_id' => $purchaseId, 'approver_type' => 'warehouse_manager'],
                [
                    'approver_id' => $userId,
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approval_notes' => $notes,
                ]
            );

            $purchase->update([
                'status' => 'pending_allocation',
            ]);

            return $purchase->fresh();
        });
    }

    /**
     * ✅ تایید توسط متولی (بعد از تایید انبار) → می‌رود به pending_allocation
     */
    /**
     * تایید توسط متولی (با ثبت خروج موقت)
     */
    public function approveByCustodian(int $purchaseId, int $userId, ?string $notes, array $temporaryExits = [])
    {
        return DB::transaction(function () use ($purchaseId, $userId, $notes, $temporaryExits) {
            $purchase = Purchase::findOrFail($purchaseId);

            if (!$purchase->canBeApprovedByCustodian()) {
                throw new \Exception('این خرید در وضعیت تایید متولی نیست');
            }

            // ثبت تایید
            Approval::updateOrCreate(
                ['purchase_id' => $purchaseId, 'approver_type' => 'custodian'],
                [
                    'approver_id' => $userId,
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approval_notes' => $notes,
                ]
            );

            // ثبت خروج‌های موقت
            foreach ($temporaryExits as $exit) {
                $allocation = Allocation::where('purchase_id', $purchaseId)
                    ->findOrFail($exit['allocation_id']);

                // بررسی اینکه مقدار خروج از مقدار موجود در قرنطینه بیشتر نباشد
                $remainingInQuarantine = $allocation->allocated_qty - $allocation->temporary_exit_qty - $allocation->received_qty;
                if ($exit['quantity'] > $remainingInQuarantine) {
                    throw new \Exception("مقدار خروج برای allocation #{$allocation->id} بیشتر از موجودی قرنطینه است");
                }

                TemporaryExit::create([
                    'allocation_id' => $allocation->id,
                    'purchase_id' => $purchaseId,
                    'quantity' => $exit['quantity'],
                    'target_type' => $exit['target_type'] ?? 'equipment',
                    'target_code' => $exit['target_code'],
                    'target_description' => $exit['target_description'],
                    'site_name' => $exit['site_name'] ?? null,
                    'registered_by' => $userId,
                    'notes' => $exit['notes'] ?? null,
                    'exit_date' => now(),
                ]);

                // به‌روزرسانی temporary_exit_qty در allocation
                $allocation->increment('temporary_exit_qty', $exit['quantity']);
            }

            // پس از تایید متولی و ثبت خروج‌های موقت، نوبت ثبت حواله بازرگانی است.
            $purchase->update([
                'status' => 'pending_commercial_voucher',
                'custodian_approved_at' => now(),
            ]);

            return $purchase->fresh(['temporaryExits.registeredBy', 'temporaryExits.allocation']);
        });
    }

    /**
     * رد توسط متولی
     */
    public function rejectByCustodian(
        int $purchaseId,
        string $reason,
        int $userId
    ) {
        return DB::transaction(function () use (
            $purchaseId,
            $reason,
            $userId
        ) {
            $purchase = Purchase::findOrFail($purchaseId);

            if (!$purchase->canBeApprovedByCustodian()) {
                throw new \Exception(
                    'این خرید در وضعیت تایید متولی نیست'
                );
            }

            Approval::updateOrCreate(
                [
                    'purchase_id' => $purchaseId,
                    'approver_type' => 'custodian',
                ],
                [
                    'approver_id' => $userId,
                    'status' => 'rejected',
                    'rejection_reason' => $reason,
                    'rejected_at' => now(),
                ]
            );

            $purchase->update([
                'status' => 'rejected_by_custodian',
                'rejected_at' => now(),
                'rejection_reason' => $reason,

                // پاک‌سازی احتمالی اطلاعات برگشت قبلی
                'warehouse_return_scheduled_at' => null,
                'warehouse_return_scheduled_by' => null,
                'commercial_received_at' => null,
                'commercial_received_by' => null,
                'supplier_returned_at' => null,
                'supplier_returned_by' => null,
                'return_notes' => null,
            ]);

            return $purchase->fresh();
        });
    }

    /**
     * ✅ ورود شماره حواله توسط بازرگانی
     */
    public function enterVoucherNumber(int $purchaseId, int $userId, string $voucherNumber)
    {
        return DB::transaction(function () use ($purchaseId, $userId, $voucherNumber) {
            $purchase = Purchase::findOrFail($purchaseId);

            if ($purchase->status !== 'pending_commercial_voucher') {
                throw new \Exception('این خرید در وضعیت ورود حواله نیست');
            }

            $exists = Purchase::where('voucher_number', $voucherNumber)
                ->where('id', '!=', $purchaseId)
                ->exists();

            if ($exists) {
                throw new \Exception("شماره حواله '{$voucherNumber}' قبلاً ثبت شده است");
            }

            $purchase->update([
                'status' => 'pending_warehouse_receipt',
                'voucher_number' => $voucherNumber,
                'voucher_entered_by' => $userId,
                'voucher_entered_at' => now(),
            ]);

            return $purchase->fresh();
        });
    }

    /**
     * ✅ ورود شماره رسید انبار
     */
    public function enterWarehouseReceipt(int $purchaseId, int $userId, string $receiptNumber)
    {
        return DB::transaction(function () use ($purchaseId, $userId, $receiptNumber) {
            $purchase = Purchase::findOrFail($purchaseId);

            if ($purchase->status !== 'pending_warehouse_receipt') {
                throw new \Exception('این خرید در وضعیت ورود رسید انبار نیست');
            }

            $exists = Purchase::where('warehouse_receipt_number', $receiptNumber)
                ->where('id', '!=', $purchaseId)
                ->exists();

            if ($exists) {
                throw new \Exception("شماره رسید '{$receiptNumber}' قبلاً ثبت شده است");
            }

            // خروج‌های موقت از مقدار قابل نگهداری کسر می‌شوند؛ مقدار منفی نباید
            // باعث تکمیل زودهنگام یا وضعیت نامعتبر خرید شود.
            $remainingQty = $purchase->allocations()
                ->get(['allocated_qty', 'temporary_exit_qty'])
                ->sum(fn ($allocation) => max(
                    0,
                    (int) $allocation->allocated_qty - (int) ($allocation->temporary_exit_qty ?? 0)
                ));
            $hasRemainingForStorage = $remainingQty > 0;
            $purchase->update([
                'status' => $hasRemainingForStorage ? 'pending_final_allocation' : 'fully_received',
                'warehouse_receipt_number' => $receiptNumber,
                'receipt_entered_by' => $userId,
                'receipt_entered_at' => now(),
                'fully_received_at' => $hasRemainingForStorage ? null : now(),
            ]);

            return $purchase->fresh();
        });
    }


    /**
     * دریافت تخصیص متعلق به یک خرید، همراه با اطلاعات انبار
     */
    public function findAllocationByIdForPurchase(
        int $allocationId,
        int $purchaseId
    ): ?Allocation {
        return Allocation::with('warehouse')
            ->where('purchase_id', $purchaseId)
            ->find($allocationId);
    }


    public function scheduleWarehouseReturn(
        int $purchaseId,
        int $userId,
        string $scheduledAt,
        ?string $notes = null
    ) {
        return DB::transaction(function () use (
            $purchaseId,
            $userId,
            $scheduledAt,
            $notes
        ) {
            $purchase = Purchase::findOrFail($purchaseId);

            if (!$purchase->canScheduleWarehouseReturn()) {
                throw new \Exception(
                    'این خرید در وضعیت تعیین تاریخ تحویل به بازرگانی نیست'
                );
            }

            $purchase->update([
                'status' => 'warehouse_return_scheduled',
                'warehouse_return_scheduled_at' => $scheduledAt,
                'warehouse_return_scheduled_by' => $userId,
                'return_notes' => $notes,
            ]);

            return $purchase->fresh();
        });
    }


    public function confirmCommercialReceived(
        int $purchaseId,
        int $userId,
        ?string $notes = null
    ) {
        return DB::transaction(function () use (
            $purchaseId,
            $userId,
            $notes
        ) {
            $purchase = Purchase::findOrFail($purchaseId);

            if (!$purchase->canConfirmCommercialReceived()) {
                throw new \Exception(
                    'این خرید در وضعیت تحویل به بازرگانی نیست'
                );
            }

            $purchase->update([
                'status' => 'commercial_received',
                'commercial_received_at' => now(),
                'commercial_received_by' => $userId,
                'return_notes' => $notes,
            ]);

            return $purchase->fresh();
        });
    }

    public function confirmSupplierReturned(
        int $purchaseId,
        int $userId,
        ?string $notes = null
    ) {
        return DB::transaction(function () use (
            $purchaseId,
            $userId,
            $notes
        ) {
            $purchase = Purchase::findOrFail($purchaseId);

            if (!$purchase->canConfirmSupplierReturned()) {
                throw new \Exception(
                    'این خرید در وضعیت برگشت به تأمین‌کننده نیست'
                );
            }

            $purchase->update([
                'status' => 'supplier_returned',
                'supplier_returned_at' => now(),
                'supplier_returned_by' => $userId,
                'return_notes' => $notes,
            ]);

            return $purchase->fresh();
        });
    }


}
