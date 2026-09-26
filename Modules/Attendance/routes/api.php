<?php

// Modules/Attendance/routes/api.php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\App\Http\Controllers\AttendanceController;

Route::middleware(['auth:sanctum', 'user.can_login'])->prefix('v1')->name('api.')->group(function () {
    // خلاصه تردد کاربر فعلی
    Route::get('attendance/summary', [AttendanceController::class, 'summary']);
    Route::get('attendance/latest', [AttendanceController::class, 'latest']);

    // مدیران HR
    Route::middleware(['permission:AdminHr.view'])->group(function () {
        Route::get('attendance/employees', [AttendanceController::class, 'employees']);
        Route::get('attendance/employees/{personnelCode}/summary', [AttendanceController::class, 'summaryForEmployee']);
        Route::get('attendance/employees/{personnelCode}/daily', [AttendanceController::class, 'dailyAttendance']);
        Route::get('attendance/employees/{personnelCode}/months', [AttendanceController::class, 'availableMonths']);  // 🔑 جدید

    });

});
