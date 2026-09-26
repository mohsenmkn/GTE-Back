<?php

use Illuminate\Support\Facades\Route;
use Modules\WarehouseGtrabar\App\Http\Controllers\Api\EquipmentController;
use Modules\WarehouseGtrabar\App\Http\Controllers\Api\PartTraceController;
use Modules\WarehouseGtrabar\App\Http\Controllers\Api\StockController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('warehouse-gtrabar')->middleware('auth:sanctum')->group(function () {

    // Stock Routes
    Route::get('/stock', [StockController::class, 'index']);
    Route::get('/stores', [StockController::class, 'stores']);
    Route::get('/parts/search', [StockController::class, 'searchParts']);

    // Equipment Routes
    //Route::apiResource('equipment', EquipmentController::class);

    // Part Trace Routes
    Route::get('/part-trace', [PartTraceController::class, 'index']);
    Route::get('/part-trace/{id}', [PartTraceController::class, 'show']);
    Route::post('/part-trace', [PartTraceController::class, 'store']);
    Route::post('/part-trace/{id}/remove', [PartTraceController::class, 'remove']);

    // Additional Trace Routes
    Route::get('/equipment/{equipmentId}/trace', [PartTraceController::class, 'equipmentTrace']);
    Route::get('/part/{partCode}/history', [PartTraceController::class, 'partHistory']);
});
