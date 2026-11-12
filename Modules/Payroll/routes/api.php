<?php

// Modules/Payroll/routes/api.php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\App\Http\Controllers\PayslipController;
use Modules\Payroll\App\Http\Controllers\PayrollAuditLogController;
use Modules\Payroll\App\Http\Controllers\SecurityAlertController;

Route::middleware(['auth:sanctum', 'user.can_login'])->prefix('v1')->name('api.')->group(function () {


    Route::prefix('/payroll')->group(function () {
        // Backward-compatible alias for clients that still request the old URL.
        Route::get('/logs', [PayrollAuditLogController::class, 'index'])->middleware('permission:Log.view');

        // لیست ماه‌های موجود
        Route::get('/months', [PayslipController::class, 'months'])->middleware('permission:Payroll.view');

        // فیش حقوقی ماه خاص
        Route::get('/payslip/{yearMonth}', [PayslipController::class, 'show'])
            ->where('yearMonth', '[0-9]{6}')->middleware('permission:Payroll.view');

        // دانلود PDF فیش حقوقی
        Route::get('/payslip/{yearMonth}/pdf', [PayslipController::class, 'downloadPdf'])
            ->where('yearMonth', '[0-9]{6}')->middleware('permission:Payroll.view');

        // مسیرهای مدیران/HR
            Route::get('/employees', [PayslipController::class, 'employees'])->middleware('permission:AdminPayroll.view');
            Route::get('/employees/{employeeId}/payslip/{yearMonth}', [PayslipController::class, 'showForEmployee'])->middleware('permission:AdminPayroll.view');
            Route::get('/employees/{employeeId}/payslip/{yearMonth}/pdf', [PayslipController::class, 'downloadEmployeePdf'])
                 ->where('yearMonth', '[0-9]{6}')->middleware('permission:AdminPayroll.view');
    });

    Route::get('/system/logs', [PayrollAuditLogController::class, 'index'])->middleware('permission:Log.view');
    Route::get('/system/security-alerts', [SecurityAlertController::class, 'index'])->middleware('permission:Log.view');
    Route::patch('/system/security-alerts/{alert}/resolve', [SecurityAlertController::class, 'resolve'])->middleware('permission:Log.view');
});
