<?php

namespace Modules\PreWarehouse\App\Repositories\Contracts;

interface PurchaseRepositoryInterface
{
    /**
     * دریافت لیست خریدها با فیلتر
     */
    /**
     * دریافت لیست خریدها با فیلتر
     */
    public function getAll(array $filters = [], ?int $userId = null);

    /**
     * دریافت یک خرید با تمام روابط
     */
    public function findById(int $id);

    /**
     * ثبت خرید جدید
     */
    public function create(array $data);

    /**
     * ویرایش خرید
     */
    public function update(int $id, array $data);

    // ═══════════════════════════════════════
    // مرحله 2: تایید/رد توسط انبار کلی
    // ═══════════════════════════════════════

    /**
     * تایید خرید توسط انبار کلی (warehouse_manager)
     */
    public function approveByWarehouse(int $purchaseId, int $userId, ?string $notes);

    /**
     * رد خرید توسط انبار کلی
     */
    public function rejectByWarehouse(int $purchaseId, string $reason, int $userId);

    // ═══════════════════════════════════════
    // مرحله 3: تایید/رد توسط متولی
    // ═══════════════════════════════════════

    /**
     * تایید خرید توسط متولی کالا
     */
    public function approveByCustodian(int $purchaseId, int $userId, ?string $notes);

    /**
     * رد خرید توسط متولی کالا
     */
    public function rejectByCustodian(int $purchaseId, string $reason, int $userId);

    // ═══════════════════════════════════════
    // مرحله 4: تخصیص به انبارها
    // ═══════════════════════════════════════

    /**
     * تخصیص کالا به انبارها (تقسیم بین چند انبار)
     */
    public function allocateWarehouses(int $purchaseId, array $allocations);

    // ═══════════════════════════════════════
    // مرحله 5: تعیین محل / رد توسط انبار مقصد
    // ══════════════════════════════════════

    /**
     * رد تخصیص توسط انبار مقصد (برگشت به استخر تخصیص)
     */
    public function rejectByDestination(int $allocationId, string $reason, ?int $userId);

    /**
     * تعیین محل نگهداری برای یک تخصیص
     */
    public function assignLocation(int $allocationId, array $locationData);

    // ═══════════════════════════════════════
    // نهایی‌سازی
    // ═══════════════════════════════════════

    /**
     * نهایی‌سازی خرید (آماده ثبت رسید انبار)
     */
    public function finalizePurchase(int $purchaseId, int $userId, string $voucherNumber);

}
