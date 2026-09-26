<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\App\Http\Controllers\EquipmentCostController;
use Modules\Finance\App\Http\Controllers\EquipmentTypeAccessController;
use Modules\Finance\App\Http\Controllers\UserListController;


Route::middleware(['auth:sanctum'])->prefix('v1/finance')->group(function () {

    // گزارش هزینه تجهیزات
    Route::prefix('equipment-costs')->group(function () {
        // ✅ اصلاح شد: فقط انواعی که کاربر دسترسی دارد
        Route::get('/types', [EquipmentCostController::class, 'getTypes']);
        Route::get('/equipments', [EquipmentCostController::class, 'getEquipments']);
        Route::get('/report', [EquipmentCostController::class, 'getCostReport']);
    });

    // مدیریت دسترسی‌ها (فقط admin)
    Route::prefix('equipment-access')->group(function () {
        Route::get('/', [EquipmentTypeAccessController::class, 'index']);
        Route::get('/all-types', [EquipmentTypeAccessController::class, 'getAllTypes']);
        Route::post('/', [EquipmentTypeAccessController::class, 'store']);
        Route::delete('/{id}', [EquipmentTypeAccessController::class, 'destroy']);
        Route::get('/my-accesses', [EquipmentTypeAccessController::class, 'myAccesses']);


        // لیست کاربران برای Dropdown
        Route::get('/users', [UserListController::class, 'index']);

        // لیست نقش‌ها برای Dropdown
        Route::get('/roles', function () {
            $roles = \Spatie\Permission\Models\Role::select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $roles,
            ]);
    });

});

});


