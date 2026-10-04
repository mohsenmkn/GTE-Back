<?php

namespace Modules\PreWarehouse\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PreWarehouse\App\Services\PurchaseService;
use Modules\PreWarehouse\App\Http\Resources\PurchaseResource;
use Modules\PreWarehouse\App\Http\Resources\PurchaseCollection;

class PurchaseController extends Controller
{
    protected $service;

    public function __construct(PurchaseService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $filters = $request->only([
            'status', 'commercial_user_id', 'target_unit_id',
            'item_id', 'date_from', 'date_to', 'per_page', 'search',
        ]);

        $user = $request->user();
        $user->load(['roles', 'employeePosition.unit']);

        $purchases = $this->service->getRepository()->getAll(
            $filters,
            $user->id
        );

        // بررسی اینکه آیا کاربر متولی است
        $isCustodian = $user->hasRole('unit_custodian');
        $userUnitId = $user->employeePosition?->organizational_unit_id;
        $userUnitTitle = $user->employeePosition?->unit?->title;

        return response()->json([
            'data' => PurchaseCollection::make($purchases),
            'meta' => [
                'is_custodian' => $isCustodian,
                'user_unit_id' => $userUnitId,
                'user_unit_title' => $userUnitTitle,
                'can_view_all' => $user->can('pre_warehouse.view_all'),
            ],
        ]);
    }

    public function show(int $id)
    {
        $purchase = $this->service->getRepository()->findById($id);

        return response()->json([
            'data' => PurchaseResource::make($purchase),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:pre_warehouse_items,id',
            'quantity' => 'required|integer|min:1',
            'unit_of_measurement' => 'required|string|max:50',
            'target_unit_id' => 'nullable|exists:organizational_units,id',
            'description' => 'nullable|string|max:1000',

            // ✅ فیلدهای جدید اجباری
            'supplier' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
            'item_code' => 'required|string|max:100',

            'metadata' => 'nullable|array',
        ], [
            'item_id.required' => 'انتخاب کالا الزامی است',
            'quantity.required' => 'مقدار کالا الزامی است',
            'quantity.min' => 'مقدار کالا باید حداقل 1 باشد',
            'unit_of_measurement.required' => 'واحد اندازه‌گیری الزامی است',
            'supplier.required' => 'نام تامین‌کننده الزامی است',
            'brand.required' => 'برند محصول الزامی است',
            'item_code.required' => 'کد کالا الزامی است',
        ]);

        $purchase = $this->service->createPurchase($validated, $request->user()->id);

        return response()->json([
            'message' => 'خرید با موفقیت ثبت شد و در انتظار تایید انبار است',
            'data' => PurchaseResource::make($purchase),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $purchase = $this->service->getRepository()->findById($id);

        if (!$purchase->canEdit()) {
            return response()->json(['message' => 'این خرید قابل ویرایش نیست'], 422);
        }

        $validated = $request->validate([
            'description' => 'nullable|string|max:1000',
            'metadata' => 'nullable|array',
        ]);

        $purchase = $this->service->getRepository()->update($id, $validated);

        return response()->json([
            'message' => 'خرید با موفقیت ویرایش شد',
            'data' => PurchaseResource::make($purchase),
        ]);
    }

    /**
     * تایید توسط انبار کلی
     */
    public function approveByWarehouse(Request $request, int $id)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $purchase = $this->service->approveByWarehouse(
                $id,
                $request->user()->id,
                $validated['notes'] ?? null
            );

            return response()->json([
                'message' => 'خرید تایید شد و برای تخصیص انبار ارسال شد',
                'data' => PurchaseResource::make($purchase),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * رد توسط انبار کلی
     */
    public function rejectByWarehouse(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'دلیل رد کردن الزامی است',
        ]);

        try {
            $purchase = $this->service->rejectByWarehouse(
                $id,
                $validated['reason'],
                $request->user()->id
            );

            return response()->json([
                'message' => 'خرید رد شد',
                'data' => PurchaseResource::make($purchase),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }


    /**
     * تخصیص به انبارها
     */
    public function allocate(Request $request, int $id)
    {
        $validated = $request->validate([
            'allocations' => 'required|array|min:1',
            'allocations.*.warehouse_id' => 'required|exists:warehouses,id',
            'allocations.*.allocated_qty' => 'required|integer|min:1',
        ], [
            'allocations.required' => 'حداقل یک تخصیص الزامی است',
            'allocations.*.warehouse_id.required' => 'انتخاب انبار الزامی است',
            'allocations.*.allocated_qty.required' => 'مقدار تخصیص‌یافته الزامی است',
            'allocations.*.allocated_qty.min' => 'مقدار باید حداقل 1 باشد',
        ]);

        try {
            $purchase = $this->service->allocateWarehouses(
                $id,
                $validated['allocations'],
                $request->user()->id
            );

            return response()->json([
                'message' => 'تخصیص انبارها با موفقیت انجام شد',
                'data' => PurchaseResource::make($purchase),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * رد توسط انبار مقصد
     */
    public function rejectByDestination(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'دلیل رد کردن الزامی است',
        ]);

        try {
            $allocation = $this->service->rejectByDestination(
                $id,
                $validated['reason'],
                $request->user()->id
            );

            return response()->json([
                'message' => 'تخصیص رد شد و به استخر تخصیص بازگشت',
                'data' => $allocation,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * تعیین محل نگهداری
     */
    public function assignLocation(Request $request, int $id)
    {
        $validated = $request->validate([
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'warehouse_location_id' => 'nullable|exists:warehouse_locations,id',
            'location_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'section_code' => 'nullable|string|max:50',
            'assigned_qty' => 'required|integer|min:1',
        ], [
            'location_name.required' => 'نام محل نگهداری الزامی است',
            'assigned_qty.required' => 'مقدار تخصیص‌یافته الزامی است',
            'assigned_qty.min' => 'مقدار باید حداقل 1 باشد',
        ]);

        try {
            $allocation = $this->service->assignLocation(
                $id,
                $validated,
                $request->user()->id
            );

            return response()->json([
                'message' => 'محل نگهداری تعیین شد',
                'data' => $allocation,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * نهایی‌سازی
     */
    /**
     * نهایی‌سازی خرید با شماره حواله
     */
    public function finalize(Request $request, int $id)
    {
        // ✅ دیباگ: لاگ کردن تمام داده‌های دریافتی
        \Log::info('Finalize Request Data:', [
            'id Purches' => $id,
            'all' => $request->all(),
            'input' => $request->input('voucher_number'),
            'json' => $request->json()->all(),
            'content' => $request->getContent(),
        ]);

        $validated = $request->validate([
            'voucher_number' => 'required|string|max:100',
        ], [
            'voucher_number.required' => 'شماره حواله الزامی است',
            'voucher_number.max' => 'شماره حواله نباید بیشتر از 100 کاراکتر باشد',
        ]);

        try {
            $purchase = $this->service->finalizePurchase(
                $id,
                $request->user()->id,
                $validated['voucher_number']
            );

            return response()->json([
                'message' => 'خرید با موفقیت نهایی شد',
                'data' => PurchaseResource::make($purchase),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function history(int $id)
    {
        $purchase = $this->service->getRepository()->findById($id);

        return response()->json([
            'data' => $purchase->auditLogs()->with('user')->latest()->get()->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'from_status' => $log->from_status,
                    'to_status' => $log->to_status,
                    'notes' => $log->notes,
                    'changes' => $log->changes,
                    'user' => [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                        'position' => $log->user->employeePosition?->post_title,
                    ],
                    'created_at' => $log->created_at,
                ];
            }),
        ]);
    }



    /**
     * ✅ تایید نهایی متولی (بعد از تخصیص)
     */
    public function finalApproveByCustodian(Request $request, int $id)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $purchase = $this->service->finalApproveByCustodian(
                $id,
                $request->user()->id,
                $validated['notes'] ?? null
            );

            return response()->json([
                'message' => 'کالا تایید نهایی شد و آماده ورود حواله است',
                'data' => PurchaseResource::make($purchase),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }




    /**
     * ✅ تایید توسط متولی (بعد از تایید انبار)
     */
    /**
     * تایید توسط متولی (با خروج موقت)
     */
    public function approveByCustodian(Request $request, int $id)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
            'temporary_exits' => 'nullable|array',
            'temporary_exits.*.allocation_id' => 'required|exists:pre_warehouse_allocations,id',
            'temporary_exits.*.quantity' => 'required|integer|min:1',
            'temporary_exits.*.target_type' => 'nullable|string|in:equipment,vehicle,project,other',
            'temporary_exits.*.target_code' => 'required|string|max:100',
            'temporary_exits.*.target_description' => 'required|string|max:500',
            'temporary_exits.*.site_name' => 'nullable|string|max:255',
            'temporary_exits.*.notes' => 'nullable|string|max:1000',
        ], [
            'temporary_exits.*.quantity.required' => 'مقدار خروج الزامی است',
            'temporary_exits.*.quantity.min' => 'مقدار خروج باید حداقل 1 باشد',
            'temporary_exits.*.target_code.required' => 'کد تجهیز/وسیله الزامی است',
            'temporary_exits.*.target_description.required' => 'توضیحات محل مصرف الزامی است',
        ]);

        try {
            $purchase = $this->service->approveByCustodian(
                $id,
                $request->user()->id,
                $validated['notes'] ?? null,
                $validated['temporary_exits'] ?? []
            );

            return response()->json([
                'message' => 'خرید تایید شد و خروج‌های موقت ثبت شدند',
                'data' => PurchaseResource::make($purchase),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * رد توسط متولی
     */
    public function rejectByCustodian(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'دلیل رد کردن الزامی است',
        ]);

        try {
            $purchase = $this->service->rejectByCustodian(
                $id,
                $validated['reason'],
                $request->user()->id
            );

            return response()->json([
                'message' => 'خرید رد شد',
                'data' => PurchaseResource::make($purchase),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * ✅ ورود شماره حواله توسط بازرگانی
     */
    public function enterVoucher(Request $request, int $id)
    {
        $validated = $request->validate([
            'voucher_number' => 'required|string|max:100',
        ], [
            'voucher_number.required' => 'شماره حواله الزامی است',
        ]);

        try {
            $purchase = $this->service->enterVoucherNumber(
                $id,
                $request->user()->id,
                $validated['voucher_number']
            );

            return response()->json([
                'message' => 'شماره حواله با موفقیت ثبت شد',
                'data' => PurchaseResource::make($purchase),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * ✅ ورود شماره رسید انبار
     */
    public function enterWarehouseReceipt(Request $request, int $id)
    {
        $validated = $request->validate([
            'receipt_number' => 'required|string|max:100',
        ], [
            'receipt_number.required' => 'شماره رسید انبار الزامی است',
        ]);

        try {
            $purchase = $this->service->enterWarehouseReceipt(
                $id,
                $request->user()->id,
                $validated['receipt_number']
            );

            $message = $purchase->status === 'pending_final_allocation'
                ? 'رسید انبار ثبت شد؛ اکنون انبار مقصد و محل نگهداری تعداد باقیمانده را مشخص کنید'
                : 'رسید انبار ثبت شد و به دلیل خروج موقت کامل، مقدار قابل نگهداری باقی نمانده است';

            return response()->json([
                'message' => $message,
                'data' => PurchaseResource::make($purchase),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** تایید تحویل گرفتن کالای مرجوعی توسط بازرگانی */

    /**
     * ثبت تاریخ تحویل کالای مرجوعی به بازرگانی توسط انبار
     */
    public function scheduleWarehouseReturn(Request $request, int $id)
    {
        $validated = $request->validate([
            'scheduled_at' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ], [
            'scheduled_at.required' => 'تاریخ تحویل الزامی است',
        ]);

        try {
            $purchase = $this->service->scheduleWarehouseReturn(
                $id,
                $request->user()->id,
                $validated['scheduled_at'],
                $validated['notes'] ?? null
            );

            return response()->json([
                'message' => 'تاریخ تحویل به بازرگانی با موفقیت ثبت شد',
                'data' => PurchaseResource::make($purchase),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * تایید تحویل گرفتن کالای مرجوعی توسط بازرگانی
     */
    public function confirmCommercialReceived(Request $request, int $id)
    {
        try {
            $purchase = $this->service->confirmCommercialReceived(
                $id,
                $request->user()->id
            );

            return response()->json([
                'message' => 'تحویل کالای مرجوعی توسط بازرگانی با موفقیت ثبت شد',
                'data' => PurchaseResource::make($purchase),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * ثبت برگشت کالا به تأمین‌کننده توسط بازرگانی
     */
    public function confirmSupplierReturned(Request $request, int $id)
    {
        try {
            $purchase = $this->service->confirmSupplierReturned(
                $id,
                $request->user()->id
            );

            return response()->json([
                'message' => 'برگشت کالا به تأمین‌کننده با موفقیت ثبت شد',
                'data' => PurchaseResource::make($purchase),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }


}
