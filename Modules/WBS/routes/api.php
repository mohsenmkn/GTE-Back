<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Project\App\Http\Controllers\ProjectController;
use Modules\WBS\App\Http\Controllers\TaskController;
use Modules\WBS\App\Http\Controllers\WbsItemController;

/*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    |
    | Here is where you can register API routes for your application. These
    | routes are loaded by the RouteServiceProvider within a group which
    | is assigned the "api" middleware group. Enjoy building your API!
    |
*/

Route::middleware(['auth:sanctum', 'user.can_login'])->prefix('v1')->name('api.')->group(function () {
    Route::get('/wbs/tree', [WbsItemController::class, 'tree']);
    Route::apiResource('wbs', WbsItemController::class);
    Route::prefix('wbs/{wbsItem}')->group(function () {
        Route::get('/tasks', [TaskController::class, 'index'])->name('wbs.tasks.index');
        Route::post('/tasks', [TaskController::class, 'store'])->name('wbs.tasks.store');
        Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('wbs.tasks.show');
        Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('wbs.tasks.update');
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('wbs.tasks.destroy');
        Route::patch('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('wbs.tasks.complete');
    });
});
