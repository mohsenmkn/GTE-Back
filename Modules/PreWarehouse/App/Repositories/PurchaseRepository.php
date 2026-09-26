<?php

namespace Modules\PreWarehouse\App\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\PreWarehouse\App\Models\Allocation;
use Modules\PreWarehouse\App\Models\Approval;
use Modules\PreWarehouse\App\Models\Location as LocationModel;
use Modules\PreWarehouse\App\Models\Purchase;
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
            'allocations.receivedBy',
            'allocations.rejectedBy',
            'allocations.locations.warehouseLocation',
            'approvals.approver',
            'auditLogs.user',
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
     * تایید توسط انبار کلی
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
                'status' => 'pending_custodian_approval',
            ]);

            return $purchase->fresh();
        });
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
     * تایید توسط متولی
     */
    public function approveByCustodian(int $purchaseId, int $userId, ?string $notes)
    {
        return DB::transaction(function () use ($purchaseId, $userId, $notes) {
            $purchase = Purchase::findOrFail($purchaseId);

            if (!$purchase->canBeApprovedByCustodian()) {
                throw new \Exception('این خرید در وضعیت تایید متولی نیست');
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
                'status' => 'pending_allocation',
                'available_for_allocation' => $purchase->quantity,
            ]);

            return $purchase->fresh();
        });
    }

    /**
     * رد توسط متولی
     */
    public function rejectByCustodian(int $purchaseId, string $reason, int $userId)
    {
        return DB::transaction(function () use ($purchaseId, $reason, $userId) {
            $purchase = Purchase::findOrFail($purchaseId);

            if (!$purchase->canBeApprovedByCustodian()) {
                throw new \Exception('این خرید در وضعیت تایید متولی نیست');
            }

            Approval::updateOrCreate(
                ['purchase_id' => $purchaseId, 'approver_type' => 'custodian'],
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
            ]);

            return $purchase->fresh();
        });
    }

    /**
     * تخصیص به انبارها
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
                Allocation::create([
                    'purchase_id' => $purchaseId,
                    'warehouse_id' => $allocation['warehouse_id'],
                    'allocated_qty' => $allocation['allocated_qty'],
                    'status' => 'pending',
                ]);
            }

            $purchase->update([
                'total_allocated_qty' => $newTotalAllocated,
                'available_for_allocation' => $purchase->quantity - $newTotalAllocated,
                'status' => $newTotalAllocated >= $purchase->quantity
                    ? 'pending_location_assignment'
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
    public function assignLocation(int $allocationId, array $locationData)
    {
        return DB::transaction(function () use ($allocationId, $locationData) {
            $allocation = Allocation::with('purchase')->findOrFail($allocationId);

            if ($allocation->status !== 'pending') {
                throw new \Exception('این تخصیص در وضعیت مناسب برای تعیین محل نیست');
            }

            LocationModel::create([
                'allocation_id' => $allocationId,
                'warehouse_id' => $allocation->warehouse_id,
                'warehouse_location_id' => $locationData['warehouse_location_id'] ?? null,
                'location_name' => $locationData['location_name'],
                'description' => $locationData['description'] ?? null,
                'section_code' => $locationData['section_code'] ?? null,
                'assigned_qty' => $locationData['assigned_qty'],
            ]);

            // بررسی تکمیل محل‌ها
            $purchase = $allocation->purchase;
            $allAllocationsHaveLocation = $purchase->allocations()
                    ->where('status', '!=', 'rejected_by_destination')
                    ->whereDoesntHave('locations')
                    ->count() === 0;

            if ($allAllocationsHaveLocation) {
                $purchase->update([
                    'status' => 'location_assigned',
                    'location_assigned_at' => now(),
                ]);
            }

            return $allocation->fresh();
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
}
