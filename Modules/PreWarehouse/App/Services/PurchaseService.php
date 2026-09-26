<?php

namespace Modules\PreWarehouse\App\Services;

use Illuminate\Support\Facades\Log;
use Modules\PreWarehouse\App\Models\AuditLog;
use Modules\PreWarehouse\App\Models\CustodianMapping;
use Modules\PreWarehouse\App\Models\Purchase;
use Modules\PreWarehouse\App\Repositories\Contracts\PurchaseRepositoryInterface;

class PurchaseService
{
    protected $repository;

    public function __construct(PurchaseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getRepository(): PurchaseRepositoryInterface
    {
        return $this->repository;
    }

    /**
     * ثبت خرید جدید توسط بازرگانی
     */
    public function createPurchase(array $data, int $userId)
    {
        $data['commercial_user_id'] = $userId;
        $data['status'] = 'pending_warehouse_approval'; // ✅ مستقیماً به تایید انبار می‌رود
        $data['available_for_allocation'] = 0;

        $purchase = $this->repository->create($data);

        $this->logAudit($purchase, 'created', null, 'pending_warehouse_approval', $userId, [
            'item_name' => $data['item_id'],
            'quantity' => $data['quantity'],
        ]);

        return $purchase;
    }

    /**
     * تایید توسط انبار کلی
     */
    public function approveByWarehouse(int $purchaseId, int $userId, ?string $notes = null)
    {
        $purchase = $this->repository->findById($purchaseId);

        if (!$purchase->canBeApprovedByWarehouse()) {
            throw new \Exception('این خرید در وضعیت تایید انبار نیست');
        }

        // ✅ حذف چک کردن نقش - permission در route چک می‌شود
        // if (!$this->userHasWarehouseManagerRole($userId)) {
        //     throw new \Exception('فقط مدیر انبار می‌تواند این عملیات را انجام دهد');
        // }

        $oldStatus = $purchase->status;
        $result = $this->repository->approveByWarehouse($purchaseId, $userId, $notes);

        $this->logAudit($purchase, 'approved', $oldStatus, 'pending_custodian_approval', $userId, [
            'notes' => $notes,
        ]);

        return $result;
    }

    /**
     * رد توسط انبار کلی
     */
    public function rejectByWarehouse(int $purchaseId, string $reason, int $userId)
    {
        if (empty(trim($reason))) {
            throw new \Exception('دلیل رد کردن الزامی است');
        }

        $purchase = $this->repository->findById($purchaseId);

        if (!$purchase->canBeApprovedByWarehouse()) {
            throw new \Exception('این خرید در وضعیت تایید انبار نیست');
        }

        // ✅ حذف چک کردن نقش
        // if (!$this->userHasWarehouseManagerRole($userId)) {
        //     throw new \Exception('فقط مدیر انبار می‌تواند این عملیات را انجام دهد');
        // }

        $oldStatus = $purchase->status;
        $result = $this->repository->rejectByWarehouse($purchaseId, $reason, $userId);

        $this->logAudit($purchase, 'rejected', $oldStatus, 'rejected_by_warehouse', $userId, [
            'reason' => $reason,
        ]);

        return $result;
    }

    /**
     * تایید توسط متولی
     */
    public function approveByCustodian(int $purchaseId, int $userId, ?string $notes = null)
    {
        $purchase = $this->repository->findById($purchaseId);

        if (!$purchase->canBeApprovedByCustodian()) {
            throw new \Exception('این خرید در وضعیت تایید متولی نیست');
        }

        // بررسی اینکه کاربر متولی واحد مربوطه است
        if ($purchase->target_unit_id) {
            $custodian = CustodianMapping::getCustodianForUnit($purchase->target_unit_id);
            if (!$custodian || $custodian->id !== $userId) {
                throw new \Exception('شما متولی این واحد نیستید');
            }
        }

        $oldStatus = $purchase->status;
        $result = $this->repository->approveByCustodian($purchaseId, $userId, $notes);

        $this->logAudit($purchase, 'approved', $oldStatus, 'pending_allocation', $userId, [
            'notes' => $notes,
        ]);

        return $result;
    }

    /**
     * رد توسط متولی
     */
    public function rejectByCustodian(int $purchaseId, string $reason, int $userId)
    {
        if (empty(trim($reason))) {
            throw new \Exception('دلیل رد کردن الزامی است');
        }

        $purchase = $this->repository->findById($purchaseId);

        if (!$purchase->canBeApprovedByCustodian()) {
            throw new \Exception('این خرید در وضعیت تایید متولی نیست');
        }

        if ($purchase->target_unit_id) {
            $custodian = CustodianMapping::getCustodianForUnit($purchase->target_unit_id);
            if (!$custodian || $custodian->id !== $userId) {
                throw new \Exception('شما متولی این واحد نیستید');
            }
        }

        $oldStatus = $purchase->status;
        $result = $this->repository->rejectByCustodian($purchaseId, $reason, $userId);

        $this->logAudit($purchase, 'rejected', $oldStatus, 'rejected_by_custodian', $userId, [
            'reason' => $reason,
        ]);

        return $result;
    }

    /**
     * تخصیص به انبارها
     */
    public function allocateWarehouses(int $purchaseId, array $allocations, int $userId)
    {
        $purchase = $this->repository->findById($purchaseId);

        if (!$purchase->canBeAllocated()) {
            throw new \Exception('این خرید قابل تخصیص نیست');
        }

        $totalNewAllocation = array_sum(array_column($allocations, 'allocated_qty'));
        $newTotal = $purchase->total_allocated_qty + $totalNewAllocation;

        if ($newTotal > $purchase->quantity) {
            throw new \Exception("مجموع تخصیص‌ها ({$newTotal}) نمی‌تواند بیشتر از مقدار کل ({$purchase->quantity}) باشد");
        }

        $oldStatus = $purchase->status;
        $result = $this->repository->allocateWarehouses($purchaseId, $allocations);

        $this->logAudit($purchase, 'allocated', $oldStatus, $result->status, $userId, [
            'allocations' => $allocations,
        ]);

        return $result;
    }

    /**
     * رد توسط انبار مقصد (برگشت به استخر)
     */
    public function rejectByDestination(int $allocationId, string $reason, int $userId)
    {
        if (empty(trim($reason))) {
            throw new \Exception('دلیل رد کردن الزامی است');
        }

        $allocation = \Modules\PreWarehouse\App\Models\Allocation::findOrFail($allocationId);
        $oldStatus = $allocation->status;
        $result = $this->repository->rejectByDestination($allocationId, $reason, $userId);

        $this->logAudit($allocation, 'rejected', $oldStatus, 'rejected_by_destination', $userId, [
            'reason' => $reason,
        ]);

        return $result;
    }

    /**
     * تعیین محل نگهداری
     */
    public function assignLocation(int $allocationId, array $locationData, int $userId)
    {
        $allocation = \Modules\PreWarehouse\App\Models\Allocation::findOrFail($allocationId);

        if ($allocation->status !== 'pending') {
            throw new \Exception('این تخصیص در وضعیت مناسب برای تعیین محل نیست');
        }

        $existingQty = $allocation->locations()->sum('assigned_qty');
        if ($existingQty + $locationData['assigned_qty'] > $allocation->allocated_qty) {
            throw new \Exception('مجموع مقادیر محل‌ها نمی‌تواند بیشتر از مقدار تخصیص‌یافته باشد');
        }

        $oldStatus = $allocation->purchase->status;
        $result = $this->repository->assignLocation($allocationId, $locationData);

        $this->logAudit($allocation, 'location_assigned', $oldStatus, $result->purchase->status, $userId, [
            'location' => $locationData,
        ]);

        return $result;
    }

    /**
     * نهایی‌سازی
     */
    /**
     * نهایی‌سازی خرید (فقط توسط مسئول ثبت)
     */
    public function finalizePurchase(int $purchaseId, int $userId, string $voucherNumber)
    {
        $purchase = $this->repository->findById($purchaseId);

        if ($purchase->status !== 'location_assigned') {
            throw new \Exception('برای نهایی‌سازی، ابتدا باید محل نگهداری همه تخصیص‌ها مشخص شود');
        }

        // ✅ بررسی اینکه کاربر فعلی، مسئول ثبت خرید است
        if (!$purchase->isOwnedBy($userId)) {
            throw new \Exception('فقط مسئول ثبت این خرید می‌تواند آن را نهایی کند');
        }

        // ✅ بررسی شماره حواله
        if (empty(trim($voucherNumber))) {
            throw new \Exception('شماره حواله الزامی است');
        }

        $oldStatus = $purchase->status;
        $result = $this->repository->finalizePurchase($purchaseId, $userId, $voucherNumber);

        $this->logAudit($purchase, 'finalized', $oldStatus, 'finalized', $userId, [
            'voucher_number' => $voucherNumber,
        ]);

        return $result;
    }

    /**
     * بررسی نقش warehouse_manager
     */
    protected function userHasWarehouseManagerRole(int $userId): bool
    {
        $user = \Modules\Auth\App\Models\User::find($userId);
        return $user && $user->hasRole('warehouse_approve');
    }

    /**
     * ثبت Audit Log
     */
    protected function logAudit($model, string $action, ?string $fromStatus, ?string $toStatus, int $userId, ?array $changes = null)
    {
        AuditLog::create([
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'user_id' => $userId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changes' => $changes,
        ]);
    }
}
