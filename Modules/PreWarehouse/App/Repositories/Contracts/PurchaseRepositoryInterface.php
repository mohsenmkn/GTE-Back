<?php

namespace Modules\PreWarehouse\App\Repositories\Contracts;

interface PurchaseRepositoryInterface
{
    public function getAll(array $filters = [], ?int $userId = null);
    public function findById(int $id);
    public function create(array $data);
    public function update(int $id, array $data);

    // مرحله 2: تایید/رد انبار کلی
    public function approveByWarehouse(int $purchaseId, int $userId, ?string $notes);
    public function rejectByWarehouse(int $purchaseId, string $reason, int $userId);

    // ✅ مرحله 3: تایید/رد متولی (بعد از تایید انبار، قبل از تخصیص)
    public function approveByCustodian(int $purchaseId, int $userId, ?string $notes);
    public function rejectByCustodian(int $purchaseId, string $reason, int $userId);

    // مرحله 4: تخصیص
    public function allocateWarehouses(int $purchaseId, array $allocations);

    // مرحله 5: رد توسط انبار مقصد + تعیین محل
    public function rejectByDestination(int $allocationId, string $reason, ?int $userId);
    public function assignLocation(int $allocationId, array $locationData);

    // ✅ مرحله 6: ورود شماره حواله توسط بازرگانی
    public function enterVoucherNumber(int $purchaseId, int $userId, string $voucherNumber);

    // ✅ مرحله 7: ورود شماره رسید انبار
    public function enterWarehouseReceipt(int $purchaseId, int $userId, string $receiptNumber);
}
