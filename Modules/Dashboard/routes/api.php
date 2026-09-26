<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\App\Http\Controllers\DashboardController;

Route::prefix('v1/dashboard')->middleware(['auth:sanctum', 'user.can_login'])->group(function () {
    Route::get('/payslip-summary', [DashboardController::class, 'getPayslipSummary']);
    Route::get('/announcements', [DashboardController::class, 'getAnnouncements']);
    Route::post('/announcements/{id}/read', [DashboardController::class, 'markAsRead']);
    Route::get('/data', [DashboardController::class, 'getDashboardData']);
});
