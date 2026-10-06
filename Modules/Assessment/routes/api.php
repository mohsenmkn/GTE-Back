<?php

use Illuminate\Support\Facades\Route;
use Modules\Assessment\App\Http\Controllers\AssessmentAssignmentController;
use Modules\Assessment\App\Http\Controllers\AssessmentController;
use Modules\Assessment\App\Http\Controllers\AssessmentCycleController;
use Modules\Assessment\App\Http\Controllers\AssessmentMappingController;
use Modules\Assessment\App\Http\Controllers\AssessmentPeriodController;

/*
|--------------------------------------------------------------------------
| Assessment Module Routes
|--------------------------------------------------------------------------
| پیشوند: /api/v1/assessment
| احراز هویت: Laravel Sanctum
*/

Route::prefix('v1/assessment')
    ->middleware(['auth:sanctum', 'user.can_login'])
    ->group(function () {

        /* ═══════════════════════════════════════════════════
           ۱. چرخه‌های ارزیابی (Cycles)
        ═══════════════════════════════════════════════════ */
        Route::prefix('cycles')->middleware('permission:assessment.manage')->group(function () {
            Route::get('/', [AssessmentCycleController::class, 'index']);
            Route::post('/', [AssessmentCycleController::class, 'store'])->middleware('permission:assessment.manage');
            Route::post('/{cycle}/activate', [AssessmentCycleController::class, 'activate']);
            Route::post('/{cycle}/close', [AssessmentCycleController::class, 'close']);
        });

        /* ═══════════════════════════════════════════════════
           ۲. دوره‌های ارزیابی (Periods)
        ═══════════════════════════════════════════════════ */
        Route::prefix('periods')->middleware('permission:assessment.manage')->group(function () {
            Route::get('/', [AssessmentPeriodController::class, 'index']);
            Route::post('/', [AssessmentPeriodController::class, 'store'])->middleware('permission:assessment.manage');
            Route::get('/{period}', [AssessmentPeriodController::class, 'show']);
            Route::put('/{period}', [AssessmentPeriodController::class, 'update']);
            Route::post('/{period}/generate', [AssessmentPeriodController::class, 'generate']);
            Route::post('/{period}/auto-assign', [AssessmentPeriodController::class, 'autoAssign']);
        });

        /* ══════════════════════════════════════════════════
           ۳. تخصیص خودکار (Auto-Assign)
        ══════════════════════════════════════════════════ */
        Route::prefix('auto-assign')->middleware('permission:assessment.manage')->group(function () {
            Route::get('/preview', [AssessmentAssignmentController::class, 'preview']);
            Route::post('/execute', [AssessmentAssignmentController::class, 'execute']);
        });

        /* ═══════════════════════════════════════════════════
           ۴. شناسنامه‌های شایستگی (Posts)
        ══════════════════════════════════════════════════ */
        Route::prefix('posts')->middleware('permission:assessment.view|assessment.manage|assessment.evaluate')->group(function () {
            Route::get('/', [AssessmentController::class, 'posts']);
            Route::post('/', [AssessmentController::class, 'storePost'])->middleware('permission:assessment.manage');
            Route::get('/{post}', [AssessmentController::class, 'showPost']);
            Route::put('/{post}', [AssessmentController::class, 'updatePost'])->middleware('permission:assessment.manage');
            Route::delete('/{post}', [AssessmentController::class, 'destroyPost'])->middleware('permission:assessment.manage');
            Route::get('/{post}/questions', [AssessmentController::class, 'postQuestions']);
        });

        // روت‌های عمومی‌تر برای posts (بدون middleware اضافه)
        Route::get('suggest-post', [AssessmentController::class, 'suggestPost'])
            ->middleware('permission:assessment.view');

        /* ═══════════════════════════════════════════════════
           ۵. سوالات (Questions)
        ══════════════════════════════════════════════════ */
        Route::prefix('questions')->middleware('permission:assessment.manage')->group(function () {
            Route::post('/', [AssessmentController::class, 'storeQuestion']);
            Route::put('/bulk', [AssessmentController::class, 'bulkUpdateQuestions']);
            Route::put('/{question}', [AssessmentController::class, 'updateQuestion']);
            Route::delete('/{question}', [AssessmentController::class, 'destroyQuestion']);
        });

        /* ══════════════════════════════════════════════════
           ۶. دسته‌بندی‌ها (Categories)
        ═══════════════════════════════════════════════════ */
        Route::prefix('categories')->middleware('permission:assessment.manage')->group(function () {
            Route::get('/', [AssessmentController::class, 'categories']);
            Route::post('/', [AssessmentController::class, 'storeCategory']);
        });

        /* ═══════════════════════════════════════════════════
           ۷. روش‌های رفع خلا (Methods)
        ═══════════════════════════════════════════════════ */
        Route::prefix('methods')->middleware('permission:assessment.view|assessment.manage|assessment.evaluate')->group(function () {
            Route::get('/', [AssessmentController::class, 'methods']);
            Route::post('/', [AssessmentController::class, 'storeMethod'])->middleware('permission:assessment.manage');
            Route::put('/{method}', [AssessmentController::class, 'updateMethod'])->middleware('permission:assessment.manage');
            Route::delete('/{method}', [AssessmentController::class, 'destroyMethod'])->middleware('permission:assessment.manage');
        });

        /* ═══════════════════════════════════════════════════
           ۸. ارزیابی‌ها (Assessments)
        ═══════════════════════════════════════════════════ */
        Route::prefix('assessments')->group(function () {
            Route::get('/', [AssessmentController::class, 'index']);
            Route::post('/', [AssessmentController::class, 'store'])->middleware('permission:assessment.manage');
            Route::post('/bulk', [AssessmentController::class, 'bulkStore'])->middleware('permission:assessment.manage');
            Route::get('/{assessment}', [AssessmentController::class, 'show']);
            Route::post('/{assessment}/submit', [AssessmentController::class, 'submit']);
            Route::post('/{assessment}/approve', [AssessmentController::class, 'approve'])->middleware('permission:assessment.manage');
            Route::post('/{assessment}/reject', [AssessmentController::class, 'reject'])->middleware('permission:assessment.manage');
            Route::get('/{assessment}/gaps', [AssessmentController::class, 'gaps']);
            Route::post('/{assessment}/actions', [AssessmentController::class, 'storeAction']);
        });

        /* ═══════════════════════════════════════════════════
           ۹. Import از اکسل
        ═══════════════════════════════════════════════════ */
        Route::prefix('import')->middleware('permission:assessment.manage')->group(function () {
            Route::post('/', [AssessmentController::class, 'importExcel']);
            Route::post('/preview', [AssessmentController::class, 'importPreview']);
            Route::post('/bulk', [AssessmentController::class, 'importBulk']);
        });

        /* ═══════════════════════════════════════════════════
           ۱۰. کاتالوگ‌های عمومی
        ═══════════════════════════════════════════════════ */
        Route::get('users', [AssessmentController::class, 'users'])
            ->middleware('permission:assessment.manage');

        /* ═══════════════════════════════════════════════════
           ۱۱. گزارش‌ها و کارنامه
        ═══════════════════════════════════════════════════ */
        Route::get('employees/{user}/report', [AssessmentController::class, 'employeeReport'])
            ->whereNumber('user')
            ->middleware('permission:assessment.view');

        Route::get('dashboard/stats', [AssessmentController::class, 'dashboardStats'])
            ->middleware('permission:assessment.view');

        // ═══════════════════════════════════════════════
        // ۱۲. نگاشت دستی شناسنامه‌ها (Manual Mapping)
        // ═══════════════════════════════════════════════
        Route::prefix('mappings')->middleware('permission:assessment.manage')->group(function () {
            Route::get('/', [AssessmentMappingController::class, 'index']);
            Route::post('/', [AssessmentMappingController::class, 'store'])->middleware('permission:assessment.manage');
            Route::delete('/{id}', [AssessmentMappingController::class, 'destroy']);
            Route::put('/{id}/toggle', [AssessmentMappingController::class, 'toggle']);

            // ✅ route های جدید
            Route::get('/no-evaluator', [AssessmentMappingController::class, 'noEvaluator']);
            Route::post('/assign-evaluator', [AssessmentMappingController::class, 'assignEvaluator']);
        });




    });
