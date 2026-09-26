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
    ->middleware(['auth:sanctum'])
    ->group(function () {

        /* ═══════════════════════════════════════════════════
           ۱. چرخه‌های ارزیابی (Cycles)
        ═══════════════════════════════════════════════════ */
        Route::prefix('cycles')->group(function () {
            Route::get('/', [AssessmentCycleController::class, 'index']);
            Route::post('/', [AssessmentCycleController::class, 'store']);
            Route::post('/{cycle}/activate', [AssessmentCycleController::class, 'activate']);
            Route::post('/{cycle}/close', [AssessmentCycleController::class, 'close']);
        })->middleware('permission:assessment.manage');

        /* ═══════════════════════════════════════════════════
           ۲. دوره‌های ارزیابی (Periods)
        ═══════════════════════════════════════════════════ */
        Route::prefix('periods')->group(function () {
            Route::get('/', [AssessmentPeriodController::class, 'index']);
            Route::post('/', [AssessmentPeriodController::class, 'store']);
            Route::get('/{period}', [AssessmentPeriodController::class, 'show']);
            Route::put('/{period}', [AssessmentPeriodController::class, 'update']);
            Route::post('/{period}/generate', [AssessmentPeriodController::class, 'generate']);
            Route::post('/{period}/auto-assign', [AssessmentPeriodController::class, 'autoAssign']);
        })->middleware('permission:assessment.manage');

        /* ══════════════════════════════════════════════════
           ۳. تخصیص خودکار (Auto-Assign)
        ══════════════════════════════════════════════════ */
        Route::prefix('auto-assign')->group(function () {
            Route::get('/preview', [AssessmentAssignmentController::class, 'preview']);
            Route::post('/execute', [AssessmentAssignmentController::class, 'execute']);
        })->middleware('permission:assessment.manage');

        /* ═══════════════════════════════════════════════════
           ۴. شناسنامه‌های شایستگی (Posts)
        ══════════════════════════════════════════════════ */
        Route::prefix('posts')->group(function () {
            Route::get('/', [AssessmentController::class, 'posts']);
            Route::post('/', [AssessmentController::class, 'storePost']);
            Route::get('/{post}', [AssessmentController::class, 'showPost']);
            Route::put('/{post}', [AssessmentController::class, 'updatePost']);
            Route::delete('/{post}', [AssessmentController::class, 'destroyPost']);
            Route::get('/{post}/questions', [AssessmentController::class, 'postQuestions']);
        });

        // روت‌های عمومی‌تر برای posts (بدون middleware اضافه)
        Route::get('suggest-post', [AssessmentController::class, 'suggestPost'])
            ->middleware('permission:assessment.view');

        /* ═══════════════════════════════════════════════════
           ۵. سوالات (Questions)
        ══════════════════════════════════════════════════ */
        Route::prefix('questions')->group(function () {
            Route::post('/', [AssessmentController::class, 'storeQuestion']);
            Route::put('/bulk', [AssessmentController::class, 'bulkUpdateQuestions']);
            Route::put('/{question}', [AssessmentController::class, 'updateQuestion']);
            Route::delete('/{question}', [AssessmentController::class, 'destroyQuestion']);
        })->middleware('permission:assessment.manage');

        /* ══════════════════════════════════════════════════
           ۶. دسته‌بندی‌ها (Categories)
        ═══════════════════════════════════════════════════ */
        Route::prefix('categories')->group(function () {
            Route::get('/', [AssessmentController::class, 'categories']);
            Route::post('/', [AssessmentController::class, 'storeCategory']);
        })->middleware('permission:assessment.manage');

        /* ═══════════════════════════════════════════════════
           ۷. روش‌های رفع خلا (Methods)
        ═══════════════════════════════════════════════════ */
        Route::prefix('methods')->group(function () {
            Route::get('/', [AssessmentController::class, 'methods']);
            Route::post('/', [AssessmentController::class, 'storeMethod']);
            Route::put('/{method}', [AssessmentController::class, 'updateMethod']);
            Route::delete('/{method}', [AssessmentController::class, 'destroyMethod']);
        });

        /* ═══════════════════════════════════════════════════
           ۸. ارزیابی‌ها (Assessments)
        ═══════════════════════════════════════════════════ */
        Route::prefix('assessments')->group(function () {
            Route::get('/', [AssessmentController::class, 'index']);
            Route::post('/', [AssessmentController::class, 'store']);
            Route::post('/bulk', [AssessmentController::class, 'bulkStore']);
            Route::get('/{assessment}', [AssessmentController::class, 'show']);
            Route::post('/{assessment}/submit', [AssessmentController::class, 'submit']);
            Route::post('/{assessment}/approve', [AssessmentController::class, 'approve']);
            Route::post('/{assessment}/reject', [AssessmentController::class, 'reject']);
            Route::get('/{assessment}/gaps', [AssessmentController::class, 'gaps']);
            Route::post('/{assessment}/actions', [AssessmentController::class, 'storeAction']);
        });

        /* ═══════════════════════════════════════════════════
           ۹. Import از اکسل
        ═══════════════════════════════════════════════════ */
        Route::prefix('import')->group(function () {
            Route::post('/', [AssessmentController::class, 'importExcel']);
            Route::post('/preview', [AssessmentController::class, 'importPreview']);
            Route::post('/bulk', [AssessmentController::class, 'importBulk']);
        })->middleware('permission:assessment.manage');

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
        Route::prefix('mappings')->group(function () {
            Route::get('/', [AssessmentMappingController::class, 'index']);
            Route::post('/', [AssessmentMappingController::class, 'store']);
            Route::delete('/{id}', [AssessmentMappingController::class, 'destroy']);
            Route::put('/{id}/toggle', [AssessmentMappingController::class, 'toggle']);
        })->middleware('permission:assessment.manage');


        Route::prefix('mappings')->group(function () {
            Route::get('/', [AssessmentMappingController::class, 'index']);
            Route::post('/', [AssessmentMappingController::class, 'store']);
            Route::delete('/{id}', [AssessmentMappingController::class, 'destroy']);
            Route::put('/{id}/toggle', [AssessmentMappingController::class, 'toggle']);

            // ✅ route های جدید
            Route::get('/no-evaluator', [AssessmentMappingController::class, 'noEvaluator']);
            Route::post('/assign-evaluator', [AssessmentMappingController::class, 'assignEvaluator']);
        })->middleware('permission:assessment.manage');




    });
