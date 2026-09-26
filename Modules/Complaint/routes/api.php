<?php

use Illuminate\Support\Facades\Route;

use Modules\Complaint\App\Http\Controllers\Api\Admin\ComplaintManagerController;
use Modules\Complaint\App\Http\Controllers\Api\AdminComplaintController;
use Modules\Complaint\App\Http\Controllers\Api\CategoryController;
use Modules\Complaint\App\Http\Controllers\Api\ComplaintController;
use Modules\Complaint\App\Http\Controllers\Api\ComplaintStatisticsController;

Route::middleware(['auth:sanctum', 'user.can_login'])->prefix('v1')->group(function ()
{

    // ─── سمت پرسنل ───
    Route::prefix('complaints')->group(function () {

        // ✅ مسیرهای ثابت (قبل از {complaint})
        Route::get('categories', [ComplaintController::class, 'categories'])
            ->middleware('permission:complaints.create');

        Route::get('organizational-units', [ComplaintController::class, 'organizationalUnits'])
            ->middleware('permission:complaints.create');

        Route::get('organizational-units/{unit}/manager', [ComplaintController::class, 'unitManager'])
            ->middleware('permission:complaints.create');

        Route::get('assigned-to-me', [ComplaintController::class, 'assignedToMe'])
            ->middleware('permission:complaints.review');

        Route::get('my', [ComplaintController::class, 'my'])
            ->middleware('permission:complaints.read');

        Route::post('/', [ComplaintController::class, 'store'])
            ->middleware('permission:complaints.create');

        Route::post('{complaint}/replies', [AdminComplaintController::class, 'storeReply'])
            ->middleware('permission:complaints.reply');

        // ✅ مسیرهای پارامتری (بعد از مسیرهای ثابت)
        Route::get('{complaint}', [ComplaintController::class, 'show'])
            ->middleware('permission:complaints.read');

        Route::put('{complaint}', [ComplaintController::class, 'update'])
            ->middleware('permission:complaints.update');

        Route::delete('{complaint}', [ComplaintController::class, 'destroy'])
            ->middleware('permission:complaints.delete');

        Route::post('{complaint}/attachments', [ComplaintController::class, 'uploadAttachment'])
            ->middleware('permission:complaints.create');
    });

    // ─── سمت ادمین ───
    Route::prefix('admin/complaints')->group(function () {

        // ✅ مسیرهای ثابت (قبل از {complaint})
        Route::get('statistics', [ComplaintStatisticsController::class, 'index'])
            ->middleware('permission:complaintstatistics.view');

        Route::get('export', [AdminComplaintController::class, 'export'])
            ->middleware('permission:complaints.export');

        Route::get('/', [AdminComplaintController::class, 'index'])
            ->middleware('permission:complaints.manage');

        // ✅ مسیرهای پارامتری
        Route::get('{complaint}', [AdminComplaintController::class, 'show'])
            ->middleware('permission:complaints.manage');

        Route::put('{complaint}/status', [AdminComplaintController::class, 'updateStatus'])
            ->middleware('permission:complaints.manage');

        Route::post('{complaint}/replies', [AdminComplaintController::class, 'storeReply'])
            ->middleware('permission:complaints.reply');
    });

    // ─── مدیریت مسئولین پیگیری (ادمین) ───
    Route::prefix('admin/complaint-managers')->group(function () {

        Route::get('users', [ComplaintManagerController::class, 'users'])
            ->middleware('permission:complaintmanagers.read');

        Route::get('/', [ComplaintManagerController::class, 'index'])
            ->middleware('permission:complaintmanagers.read');

        Route::post('/', [ComplaintManagerController::class, 'store'])
            ->middleware('permission:complaintmanagers.create');

        Route::put('{complaintManager}', [ComplaintManagerController::class, 'update'])
            ->middleware('permission:complaintmanagers.update');

        Route::delete('{complaintManager}', [ComplaintManagerController::class, 'destroy'])
            ->middleware('permission:complaintmanagers.delete');
    });

    // ─── مدیریت دسته‌بندی‌ها ───
    Route::prefix('admin/complaint-categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])
            ->middleware('permission:complaintcategories.read');

        Route::post('/', [CategoryController::class, 'store'])
            ->middleware('permission:complaintcategories.create');

        Route::put('{complaintCategory}', [CategoryController::class, 'update'])
            ->middleware('permission:complaintcategories.update');

        Route::delete('{complaintCategory}', [CategoryController::class, 'destroy'])
            ->middleware('permission:complaintcategories.delete');
    });
});
