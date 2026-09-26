<?php

use Illuminate\Support\Facades\Route;
use Modules\PreWarehouse\App\Http\Controllers\PurchaseController;
use Modules\PreWarehouse\App\Http\Controllers\ItemController;
use Modules\PreWarehouse\App\Http\Controllers\WarehouseController;
use Modules\PreWarehouse\App\Http\Controllers\WarehouseLocationController;

Route::middleware(['auth:sanctum', 'user.can_login'])
    ->prefix('v1/pre-warehouse')
    ->name('pre-warehouse.')
    ->group(function () {

        // ═══════ Items ══════
        Route::get('/items', [ItemController::class, 'index'])
            ->middleware('permission:pre_warehouse.view')
            ->name('items.index');

        Route::get('/items/all', [ItemController::class, 'all'])
            ->middleware('permission:pre_warehouse.view')
            ->name('items.all');

        Route::post('/items', [ItemController::class, 'store'])
            ->middleware('permission:pre_warehouse.item_manage')
            ->name('items.store');

        Route::get('/items/{id}', [ItemController::class, 'show'])
            ->middleware('permission:pre_warehouse.view')
            ->name('items.show');

        Route::put('/items/{id}', [ItemController::class, 'update'])
            ->middleware('permission:pre_warehouse.item_manage')
            ->name('items.update');

        Route::delete('/items/{id}', [ItemController::class, 'destroy'])
            ->middleware('permission:pre_warehouse.item_manage')
            ->name('items.destroy');

        // ═══════ Warehouses ═══════
        Route::get('/warehouses', [WarehouseController::class, 'index'])
            ->middleware('permission:pre_warehouse.view')
            ->name('warehouses.index');

        Route::get('/warehouses/all', [WarehouseController::class, 'all'])
            ->middleware('permission:pre_warehouse.view')
            ->name('warehouses.all');

        Route::post('/warehouses', [WarehouseController::class, 'store'])
            ->middleware('permission:pre_warehouse.warehouse_manage')
            ->name('warehouses.store');

        Route::get('/warehouses/{id}', [WarehouseController::class, 'show'])
            ->middleware('permission:pre_warehouse.view')
            ->name('warehouses.show');

        Route::put('/warehouses/{id}', [WarehouseController::class, 'update'])
            ->middleware('permission:pre_warehouse.warehouse_manage')
            ->name('warehouses.update');

        Route::delete('/warehouses/{id}', [WarehouseController::class, 'destroy'])
            ->middleware('permission:pre_warehouse.warehouse_manage')
            ->name('warehouses.destroy');

        // ═══════ Warehouse Locations ═══════
        Route::get('/warehouses/{warehouseId}/locations', [WarehouseLocationController::class, 'index'])
            ->middleware('permission:pre_warehouse.view')
            ->name('warehouses.locations.index');

        Route::post('/warehouses/{warehouseId}/locations', [WarehouseLocationController::class, 'store'])
            ->middleware('permission:pre_warehouse.warehouse_manage')
            ->name('warehouses.locations.store');

        Route::put('/warehouses/{warehouseId}/locations/{id}', [WarehouseLocationController::class, 'update'])
            ->middleware('permission:pre_warehouse.warehouse_manage')
            ->name('warehouses.locations.update');

        Route::delete('/warehouses/{warehouseId}/locations/{id}', [WarehouseLocationController::class, 'destroy'])
            ->middleware('permission:pre_warehouse.warehouse_manage')
            ->name('warehouses.locations.destroy');

        // ═══════ Purchases ═══════
        Route::get('/purchases', [PurchaseController::class, 'index'])
            ->middleware('permission:pre_warehouse.view')
            ->name('purchases.index');

        Route::get('/purchases/{id}', [PurchaseController::class, 'show'])
            ->middleware('permission:pre_warehouse.view')
            ->name('purchases.show');

        Route::post('/purchases', [PurchaseController::class, 'store'])
            ->middleware('permission:pre_warehouse.commercial_create')
            ->name('purchases.store');

        Route::put('/purchases/{id}', [PurchaseController::class, 'update'])
            ->middleware('permission:pre_warehouse.commercial_create')
            ->name('purchases.update');

        // ═══════ Warehouse Approval (تایید/رد انبار کلی) ═══════
        Route::post('/purchases/{id}/approve-warehouse', [PurchaseController::class, 'approveByWarehouse'])
            ->middleware('permission:pre_warehouse.warehouse_approve')
            ->name('purchases.approve_warehouse');

        Route::post('/purchases/{id}/reject-warehouse', [PurchaseController::class, 'rejectByWarehouse'])
            ->middleware('permission:pre_warehouse.warehouse_approve')
            ->name('purchases.reject_warehouse');

        // ═══════ Custodian Approval (تایید/رد متولی) ═══════
        Route::post('/purchases/{id}/approve-custodian', [PurchaseController::class, 'approveByCustodian'])
            ->middleware('permission:pre_warehouse.custodian_approve')
            ->name('purchases.approve_custodian');

        Route::post('/purchases/{id}/reject-custodian', [PurchaseController::class, 'rejectByCustodian'])
            ->middleware('permission:pre_warehouse.custodian_approve')
            ->name('purchases.reject_custodian');

        // ══════ Allocation (تخصیص) ═══════
        Route::post('/purchases/{id}/allocate', [PurchaseController::class, 'allocate'])
            ->middleware('permission:pre_warehouse.warehouse_allocate')
            ->name('purchases.allocate');

        // ═══════ Destination Rejection (رد انبار مقصد) ═══════
        Route::post('/allocations/{id}/reject-destination', [PurchaseController::class, 'rejectByDestination'])
            ->middleware('permission:pre_warehouse.warehouse_receive')
            ->name('allocations.reject_destination');

        // ═══════ Location Assignment (تعیین محل) ═══════
        Route::post('/allocations/{id}/location', [PurchaseController::class, 'assignLocation'])
            ->middleware('permission:pre_warehouse.location_assign')
            ->name('allocations.location');

        // ═══════ Finalize (نهایی‌سازی) ═══════
        Route::post('/purchases/{id}/finalize', [PurchaseController::class, 'finalize'])
            ->middleware('permission:pre_warehouse.commercial_finalize')
            ->name('purchases.finalize');

        // ═══════ History (تاریخچه) ═══════
        Route::get('/purchases/{id}/history', [PurchaseController::class, 'history'])
            ->middleware('permission:pre_warehouse.history_view')
            ->name('purchases.history');

        // ══════ Custodian Mappings (مدیریت متولیان) ═══════
        Route::get('/custodians', [\Modules\PreWarehouse\App\Http\Controllers\CustodianMappingController::class, 'index'])
            ->middleware('permission:pre_warehouse.view')
            ->name('custodians.index');

        Route::post('/custodians', [\Modules\PreWarehouse\App\Http\Controllers\CustodianMappingController::class, 'store'])
            ->middleware('permission:pre_warehouse.warehouse_manage')
            ->name('custodians.store');

        Route::put('/custodians/{id}', [\Modules\PreWarehouse\App\Http\Controllers\CustodianMappingController::class, 'update'])
            ->middleware('permission:pre_warehouse.warehouse_manage')
            ->name('custodians.update');

        Route::delete('/custodians/{id}', [\Modules\PreWarehouse\App\Http\Controllers\CustodianMappingController::class, 'destroy'])
            ->middleware('permission:pre_warehouse.warehouse_manage')
            ->name('custodians.destroy');
    });
