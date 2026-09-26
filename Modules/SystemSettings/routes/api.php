<?php
// Modules/SystemSettings/Routes/api.php

use Illuminate\Support\Facades\Route;
use Modules\SystemSettings\App\Http\Controllers\DatabaseConnectionController;

Route::middleware(['auth:sanctum'])->group(function () {

    Route::prefix('v1/system-settings')->name('system-settings.')->group(function () {

        // مدیریت اتصالات دیتابیس
        Route::prefix('database-connections')->group(function () {
            Route::get('/', [DatabaseConnectionController::class, 'index'])->name('index');
            Route::post('/', [DatabaseConnectionController::class, 'store'])->name('store');
            Route::get('/{database_connection}', [DatabaseConnectionController::class, 'show'])->name('show');
            Route::put('/{database_connection}', [DatabaseConnectionController::class, 'update'])->name('update');
            Route::delete('/{database_connection}', [DatabaseConnectionController::class, 'destroy'])->name('destroy');

            Route::post('/test', [DatabaseConnectionController::class, 'test'])->name('test');
            Route::post('/{database_connection}/test-saved', [DatabaseConnectionController::class, 'testSaved'])->name('test-saved');
            Route::patch('/{database_connection}/toggle', [DatabaseConnectionController::class, 'toggle'])->name('toggle');
            Route::post('/clear-cache', [DatabaseConnectionController::class, 'clearCache'])->name('clear-cache');
        });
    });
});
