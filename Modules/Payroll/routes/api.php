<?php

// Modules/Payroll/routes/api.php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\App\Http\Controllers\PayslipController;

Route::middleware(['auth:sanctum', 'user.can_login'])->prefix('v1')->name('api.')->group(function () {


    Route::prefix('/payroll')->group(function () {
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
});
