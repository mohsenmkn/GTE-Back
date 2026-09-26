<?php

use Illuminate\Support\Facades\Route;
use Modules\Warehouse\App\Http\Controllers\MaterialController;
use Modules\Warehouse\App\Http\Controllers\InventoryCategoryController;
use Modules\Warehouse\App\Http\Controllers\WarehouseTransactionController;
use Modules\Warehouse\App\Http\Controllers\InventoryReportController;

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'user.can_login'])
    ->group(function () {

        // Categories
        Route::prefix('inventory-categories')->group(function () {
            Route::get('/', [InventoryCategoryController::class, 'index']);
            Route::post('/', [InventoryCategoryController::class, 'store']);

            // Static route BEFORE dynamic route
            Route::get('/tree', [InventoryCategoryController::class, 'tree']);

            Route::get('/{category}', [InventoryCategoryController::class, 'show']);
            Route::put('/{category}', [InventoryCategoryController::class, 'update']);
            Route::delete('/{category}', [InventoryCategoryController::class, 'destroy']);
        });

        // Materials
        Route::prefix('materials')->group(function () {
            Route::get('/', [MaterialController::class, 'index']);
            Route::post('/', [MaterialController::class, 'store']);
            Route::get('/{material}/transactions', [MaterialController::class, 'transactions']);
            Route::get('/{material}/stock-history', [MaterialController::class, 'stockHistory']);
            Route::get('/{material}', [MaterialController::class, 'show']);
            Route::put('/{material}', [MaterialController::class, 'update']);
            Route::delete('/{material}', [MaterialController::class, 'destroy']);
        });

        // Warehouse Transactions
        Route::prefix('warehouse-transactions')->group(function () {
            Route::get('/', [WarehouseTransactionController::class, 'index']);
            Route::post('/', [WarehouseTransactionController::class, 'store']);

            Route::get('/by-material/{materialId}', [WarehouseTransactionController::class, 'byMaterial']);
            Route::get('/by-project/{projectId}', [WarehouseTransactionController::class, 'byProject']);

            Route::get('/{transaction}', [WarehouseTransactionController::class, 'show']);
            Route::delete('/{transaction}', [WarehouseTransactionController::class, 'destroy']);
        });

        // Reports
        Route::prefix('inventory-reports')->group(function () {
            Route::get('/stock', [InventoryReportController::class, 'stockReport']);
            Route::get('/consumption', [InventoryReportController::class, 'consumptionReport']);
            Route::get('/project-consumption/{projectId}', [InventoryReportController::class, 'projectConsumption']);
            Route::get('/wbs-consumption/{wbsItemId}', [InventoryReportController::class, 'wbsConsumption']);
            Route::get('/movement', [InventoryReportController::class, 'movementReport']);
            Route::get('/low-stock', [InventoryReportController::class, 'lowStockReport']);
        });

        Route::get(
            '/projects/{projectId}/materials',
            [InventoryReportController::class, 'projectMaterials']
        );
    });
