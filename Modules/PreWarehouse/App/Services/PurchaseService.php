<?php

namespace Modules\PreWarehouse\App\Services;

use Illuminate\Support\Facades\Log;
use Modules\PreWarehouse\App\Models\AuditLog;
use Modules\PreWarehouse\App\Models\CustodianMapping;
use Modules\PreWarehouse\App\Models\Purchase;
use Illuminate\Support\Facades\DB;
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
        $data['status'] = 'pending_warehouse_approval';
        $data['available_for_allocation'] = 0;

        /*
        |--------------------------------------------------------------------------
        | تاریخ تحویل به انبار
        |--------------------------------------------------------------------------
        | تاریخ در metadata به صورت JSON ذخیره می‌شود:
        | {
        |     "purchase_date": "1405/12/29"
        | }
        |
        | قبل از ایجاد Purchase مقدار را استخراج می‌کنیم تا
        | در AuditLog نیز دقیقاً همان مقدار ثبت شود.
        |--------------------------------------------------------------------------
        */
        $purchaseDate = null;

        if (isset($data['metadata'])) {

            // اگر metadata به صورت JSON String ارسال شده باشد
            if (is_string($data['metadata'])) {
                $metadata = json_decode($data['metadata'], true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($metadata)) {
                    $purchaseDate = $metadata['purchase_date'] ?? null;
                }
            }

            // اگر metadata قبلاً به صورت array باشد
            elseif (is_array($data['metadata'])) {
                $purchaseDate = $data['metadata']['purchase_date'] ?? null;
            }
        }

        $purchase = $this->repository->create($data);

        /*
        |--------------------------------------------------------------------------
        | Load Relations
        |--------------------------------------------------------------------------
        */
        $purchase->load([
            'item',
            'targetUnit',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Audit Log - ایجاد خرید
        |--------------------------------------------------------------------------
        */
        $this->logAudit(
            $purchase,
            'created',
            null,
            'pending_warehouse_approval',
            $userId,
            [
                'item_name' => $purchase->item?->name,
                'item_id' => $purchase->item_id,

                'quantity' => $purchase->quantity,
                'unit_of_measurement' => $purchase->unit_of_measurement,

                'target_unit_id' => $purchase->target_unit_id,
                'target_unit_name' => $purchase->targetUnit?->title,

                // تاریخ تحویل به انبار
                'purchase_date' => $purchaseDate,

                'description' => $purchase->description,

                'supplier' => $purchase->supplier,
                'brand' => $purchase->brand,
                'item_code' => $purchase->item_code,
            ]
        );

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

        $this->logAudit($purchase, 'approved', $oldStatus, 'pending_allocation', $userId, [
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
     * تخصیص کالا به انبارها
     */
    public function allocateWarehouses(int $purchaseId, array $allocations, int $userId)
    {
        $purchase = $this->repository->findById($purchaseId);

        if (!$purchase->canBeAllocated()) {
            throw new \Exception('این خرید قابل تخصیص نیست');
        }

        $totalNewAllocation = array_sum(array_column($allocations, 'allocated_qty'));
        $newTotalAllocated = $purchase->total_allocated_qty + $totalNewAllocation;

        if ($newTotalAllocated > $purchase->quantity) {
            throw new \Exception('مجموع تخصیص‌ها نمی‌تواند بیشتر از مقدار کل باشد');
        }

        $oldStatus = $purchase->status;
        $result = $this->repository->allocateWarehouses($purchaseId, $allocations);

        // ✅ ساخت لیست خوانا از انبارها برای audit log
        $warehouseNames = [];
        foreach ($allocations as $allocation) {
            $warehouse = \Modules\PreWarehouse\App\Models\Warehouse::find($allocation['warehouse_id']);
            if ($warehouse) {
                $warehouseNames[] = [
                    'warehouse_id' => $warehouse->id,
                    'warehouse_name' => $warehouse->name,
                    'warehouse_code' => $warehouse->code,
                    'allocated_qty' => $allocation['allocated_qty'],
                ];
            }
        }

        $this->logAudit($purchase, 'allocated', $oldStatus, $result->status, $userId, [
            'allocations' => $warehouseNames, // ✅ به جای JSON خام، نام انبارها
            'total_allocated_qty' => $newTotalAllocated,
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

        if ($allocation->purchase->status === 'pending_final_allocation') {
            $remainingQty = (int) $allocation->allocated_qty - (int) ($allocation->temporary_exit_qty ?? 0);
            if ((int) ($locationData['assigned_qty'] ?? 0) !== $remainingQty) {
                throw new \Exception("مقدار تخصیص نهایی باید دقیقاً برابر با باقیمانده قابل نگهداری ({$remainingQty}) باشد");
            }
            if (empty($locationData['warehouse_id'])) {
                throw new \Exception('انتخاب انبار مقصد الزامی است');
            }
        } else {
            if ($allocation->status !== 'pending') {
                throw new \Exception('این تخصیص در وضعیت مناسب برای تعیین محل نیست');
            }
            $existingQty = $allocation->locations()->sum('assigned_qty');
            if ($existingQty + $locationData['assigned_qty'] > $allocation->allocated_qty) {
                throw new \Exception('مجموع مقادیر محل‌ها نمی‌تواند بیشتر از مقدار تخصیص‌یافته باشد');
            }
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


    /**
     * ✅ تایید نهایی متولی (بعد از تخصیص - رویت کالا)
     */
    public function finalApproveByCustodian(int $purchaseId, int $userId, ?string $notes = null)
    {
        $purchase = $this->repository->findById($purchaseId);

        if (!$purchase->canBeFinalApprovedByCustodian()) {
            throw new \Exception('این خرید در وضعیت تایید نهایی متولی نیست');
        }

        // بررسی اینکه کاربر متولی واحد مربوطه است
        if ($purchase->target_unit_id) {
            $custodian = CustodianMapping::getCustodianForUnit($purchase->target_unit_id);
            if (!$custodian || $custodian->id !== $userId) {
                throw new \Exception('شما متولی این واحد نیستید');
            }
        }

        $oldStatus = $purchase->status;
        $result = $this->repository->finalApproveByCustodian($purchaseId, $userId, $notes);

        $this->logAudit($purchase, 'approved', $oldStatus, 'pending_commercial_voucher', $userId, [
            'notes' => $notes,
        ]);

        return $result;
    }





    /**
     * تایید توسط متولی (با خروج موقت)
     */
    public function approveByCustodian(
        int $purchaseId,
        int $userId,
        ?string $notes = null,
        array $temporaryExits = []
    ) {
        $purchase = $this->repository->findById($purchaseId);

        if (!$purchase->canBeApprovedByCustodian()) {
            throw new \Exception(
                'این خرید در وضعیت تایید متولی نیست'
            );
        }

        // بررسی اینکه کاربر متولی واحد مربوطه است
        if ($purchase->target_unit_id) {
            $custodian = CustodianMapping::getCustodianForUnit(
                $purchase->target_unit_id
            );

            if (!$custodian || $custodian->id !== $userId) {
                throw new \Exception(
                    'شما متولی این واحد نیستید'
                );
            }
        }

        // اعتبارسنجی خروج‌های موقت
        foreach ($temporaryExits as $exit) {
            if (empty($exit['allocation_id'])) {
                throw new \Exception(
                    'allocation_id الزامی است'
                );
            }

            if (
                !isset($exit['quantity']) ||
                !is_numeric($exit['quantity']) ||
                $exit['quantity'] <= 0
            ) {
                throw new \Exception(
                    'مقدار خروج باید بیشتر از صفر باشد'
                );
            }

            if (empty($exit['target_code'])) {
                throw new \Exception(
                    'کد تجهیز/وسیله الزامی است'
                );
            }

            if (empty($exit['target_description'])) {
                throw new \Exception(
                    'توضیحات محل مصرف الزامی است'
                );
            }
        }

        /*
         * قبل از ثبت خروج‌ها، اطلاعات تخصیص و انبار را
         * دریافت می‌کنیم تا جزئیات همان عملیات در تاریخچه ثبت شود.
         */
        $temporaryExitDetails = [];

        foreach ($temporaryExits as $exit) {
            $allocation = $this->repository
                ->findAllocationByIdForPurchase(
                    (int) $exit['allocation_id'],
                    $purchaseId
                );

            if (!$allocation) {
                throw new \Exception(
                    'تخصیص موردنظر برای این خرید یافت نشد.'
                );
            }

            $temporaryExitDetails[] = [
                'allocation_id' => (int) $allocation->id,
                'warehouse_id' => $allocation->warehouse_id,
                'warehouse_name' => $allocation->warehouse?->name,
                'quantity' => (int) $exit['quantity'],
                'target_type' => $exit['target_type'] ?? 'equipment',
                'target_code' => $exit['target_code'],
                'target_description' => $exit['target_description'],
                'site_name' => $exit['site_name'] ?? null,
            ];
        }

        $oldStatus = $purchase->status;

        // ثبت تأیید متولی و خروج‌های موقت
        $result = $this->repository->approveByCustodian(
            $purchaseId,
            $userId,
            $notes,
            $temporaryExits
        );

        // اطلاعات رویداد تاریخچه
        $auditChanges = [
            'notes' => $notes,
        ];

        if (!empty($temporaryExitDetails)) {
            $auditChanges['temporary_exits_count'] =
                count($temporaryExitDetails);

            $auditChanges['total_temporary_exit_qty'] =
                array_sum(
                    array_column(
                        $temporaryExitDetails,
                        'quantity'
                    )
                );

            $auditChanges['temporary_exit_details'] =
                $temporaryExitDetails;
        }

        // فقط یک لاگ برای این عملیات ثبت می‌شود
        $this->logAudit(
            $purchase,
            'custodian_approved',
            $oldStatus,
            'pending_commercial_voucher',
            $userId,
            $auditChanges
        );

        return $result;
    }

    /**
     * رد توسط متولی
     */
    /**
     * رد کامل خرید توسط متولی
     *
     * بعد از رد، خرید وارد فرآیند برگشت به بازرگانی می‌شود.
     */
    public function rejectByCustodian(
        int $purchaseId,
        string $reason,
        int $userId
    ) {
        if (empty(trim($reason))) {
            throw new \Exception(
                'دلیل رد کردن الزامی است'
            );
        }

        $purchase = $this->repository->findById($purchaseId);

        if (!$purchase->canBeApprovedByCustodian()) {
            throw new \Exception(
                'این خرید در وضعیت تایید متولی نیست'
            );
        }

        if ($purchase->target_unit_id) {
            $custodian = CustodianMapping::getCustodianForUnit(
                $purchase->target_unit_id
            );

            if (!$custodian || $custodian->id !== $userId) {
                throw new \Exception(
                    'شما متولی این واحد نیستید'
                );
            }
        }

        $oldStatus = $purchase->status;

        $result = $this->repository->rejectByCustodian(
            $purchaseId,
            $reason,
            $userId
        );

        /*
         * وضعیت جاری خرید بعد از رد کامل:
         * منتظر اقدام انبار برای تعیین تاریخ تحویل به بازرگانی
         */
        $this->logAudit(
            $purchase,
            'custodian_rejected',
            $oldStatus,
            'pending_warehouse_return',
            $userId,
            [
                'reason' => $reason,
                'return_required' => true,
                'return_scope' => 'whole_purchase',
            ]
        );

        return $result;
    }

    /**
     * ورود شماره حواله توسط بازرگانی
     */
    public function enterVoucherNumber(int $purchaseId, int $userId, string $voucherNumber)
    {
        $purchase = $this->repository->findById($purchaseId);

        if ($purchase->status !== 'pending_commercial_voucher') {
            throw new \Exception('این خرید در وضعیت ورود حواله نیست');
        }

        if (!$purchase->isOwnedBy($userId)) {
            throw new \Exception('فقط مسئول ثبت این خرید می‌تواند شماره حواله را وارد کند');
        }

        if (empty(trim($voucherNumber))) {
            throw new \Exception('شماره حواله الزامی است');
        }

        $oldStatus = $purchase->status;
        $result = $this->repository->enterVoucherNumber($purchaseId, $userId, $voucherNumber);

        $this->logAudit($purchase, 'voucher_entered', $oldStatus, 'pending_warehouse_receipt', $userId, [
            'voucher_number' => $voucherNumber,
        ]);

        return $result;
    }

    /**
     * ورود شماره رسید انبار
     */
    public function enterWarehouseReceipt(int $purchaseId, int $userId, string $receiptNumber)
    {
        $purchase = $this->repository->findById($purchaseId);

        if ($purchase->status !== 'pending_warehouse_receipt') {
            throw new \Exception('این خرید در وضعیت ورود رسید انبار نیست');
        }

        if (empty(trim($receiptNumber))) {
            throw new \Exception('شماره رسید انبار الزامی است');
        }

        $oldStatus = $purchase->status;
        $result = $this->repository->enterWarehouseReceipt($purchaseId, $userId, $receiptNumber);

        // ✅ محاسبه مقدار باقیمانده برای انبار
        $totalAllocated = $purchase->total_allocated_qty;
        $totalTemporaryExit = $purchase->temporaryExits()->sum('quantity');
        $remainingForStorage = $totalAllocated - $totalTemporaryExit;

        $this->logAudit($purchase, 'receipt_entered', $oldStatus, 'fully_received', $userId, [
            'receipt_number' => $receiptNumber,
            'remaining_qty_for_storage' => $remainingForStorage,
            'total_allocated_qty' => $totalAllocated,
            'total_temporary_exit_qty' => $totalTemporaryExit,
        ]);

        return $result;
    }

    /**
     * تعیین تاریخ تحویل خرید برگشتی به بازرگانی توسط انبار
     */
    /**
     * تعیین تاریخ تحویل خرید برگشتی به بازرگانی توسط انبار
     */
    public function scheduleNonconformityHandover(
        int $purchaseId,
        string $handoverDate,
        int $userId
    ) {
        $purchase = $this->repository->findById($purchaseId);
        if (!$purchase->canScheduleWarehouseReturn()) {
            throw new \Exception(
                'این خرید در وضعیت تعیین تاریخ تحویل به بازرگانی نیست'
            );
        }
        if (empty(trim($handoverDate))) {
            throw new \Exception(
                'تاریخ تحویل به بازرگانی الزامی است'
            );
        }
        $oldStatus = $purchase->status;
        $result = $this->repository->scheduleWarehouseReturn(
            $purchaseId,
            $userId,
            $handoverDate,
            null
        );
        $this->logAudit(
            $purchase,
            'warehouse_return_scheduled',
            $oldStatus,
            'warehouse_return_scheduled',
            $userId,
            [
                'scheduled_at' => $handoverDate,
            ]
        );
        return $result;
    }

    /**
     * ثبت تحویل خرید برگشتی به بازرگانی
     */
    public function confirmNonconformityPickup(
        int $purchaseId,
        int $userId
    ) {
        $purchase = $this->repository->findById($purchaseId);
        if (!$purchase->canConfirmCommercialReceived()) {
            throw new \Exception(
                'این خرید در وضعیت تحویل به بازرگانی نیست'
            );
        }
        if (!$purchase->isOwnedBy($userId)) {
            throw new \Exception(
                'فقط مسئول ثبت این خرید می‌تواند تحویل کالا را ثبت کند'
            );
        }
        if (!$purchase->warehouse_return_scheduled_at) {
            throw new \Exception(
                'تاریخ تحویل توسط انبار تعیین نشده است'
            );
        }
        $scheduledDate = $purchase
            ->warehouse_return_scheduled_at
            ->startOfDay();
        $today = now()->startOfDay();
        if (!$today->equalTo($scheduledDate)) {
            throw new \Exception(
                'تحویل کالا فقط در تاریخ تعیین‌شده توسط انبار امکان‌پذیر است'
            );
        }
        $oldStatus = $purchase->status;
        $result = $this->repository->confirmCommercialReceived(
            $purchaseId,
            $userId,
            null
        );
        $this->logAudit(
            $purchase,
            'commercial_received',
            $oldStatus,
            'commercial_received',
            $userId,
            [
                'scheduled_at' =>
                    $purchase->warehouse_return_scheduled_at?->toDateTimeString(),
                'received_at' => now()->toDateTimeString(),
            ]
        );
        return $result;
    }
    /**
     * ثبت برگشت کالا به تأمین‌کننده توسط بازرگانی
     */
    public function confirmSupplierReturned(
        int $purchaseId,
        int $userId,
        ?string $notes = null
    ) {
        $purchase = $this->repository->findById($purchaseId);

        if (!$purchase->canConfirmSupplierReturned()) {
            throw new \Exception(
                'این خرید در وضعیت برگشت به تأمین‌کننده نیست'
            );
        }

        if (!$purchase->isOwnedBy($userId)) {
            throw new \Exception(
                'فقط مسئول ثبت این خرید می‌تواند برگشت به تأمین‌کننده را ثبت کند'
            );
        }

        $oldStatus = $purchase->status;

        $result = $this->repository->confirmSupplierReturned(
            $purchaseId,
            $userId,
            $notes
        );

        $this->logAudit(
            $purchase,
            'supplier_returned',
            $oldStatus,
            'supplier_returned',
            $userId,
            [
                'supplier' => $purchase->supplier,
                'returned_at' => now()->toDateTimeString(),
                'notes' => $notes,
            ]
        );

        return $result;
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
}
