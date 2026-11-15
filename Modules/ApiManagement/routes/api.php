<?php

use Illuminate\Support\Facades\Route;
use Modules\ApiManagement\App\Http\Controllers\ApiClientController;
use Modules\ApiManagement\App\Http\Controllers\ExternalHrApiController;

Route::middleware(['api', 'auth:sanctum', 'permission:hr.manage'])
    ->prefix('api/v1/api-management')
    ->name('api-management.')
    ->group(function () {
        Route::get('/clients', [ApiClientController::class, 'index'])->name('clients.index');
        Route::post('/clients', [ApiClientController::class, 'store'])->name('clients.store');
        Route::delete('/clients/{token}', [ApiClientController::class, 'destroy'])->whereNumber('token')->name('clients.destroy');
    });

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api/v1/external/hr')
    ->name('external.hr.')
    ->group(function () {
        Route::get('/employees', [ExternalHrApiController::class, 'employees'])
            ->middleware('abilities:hr:employees:read')->name('employees.index');
        Route::get('/org-chart', [ExternalHrApiController::class, 'orgChart'])
            ->middleware('abilities:hr:hierarchy:read')->name('org-chart');
        Route::get('/attendance', [ExternalHrApiController::class, 'attendanceEmployees'])
            ->middleware('abilities:hr:attendance:read')->name('attendance.index');
    });
