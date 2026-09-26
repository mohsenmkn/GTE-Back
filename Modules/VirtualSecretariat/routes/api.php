<?php

use Illuminate\Support\Facades\Route;
use Modules\VirtualSecretariat\App\Http\Controllers\Api\AdminRequestController;
use Modules\VirtualSecretariat\App\Http\Controllers\Api\DashboardController;
use Modules\VirtualSecretariat\App\Http\Controllers\Api\RequestController;
use Modules\VirtualSecretariat\App\Http\Controllers\Api\TemplateController;
use Modules\VirtualSecretariat\App\Http\Controllers\Api\WorkflowDashboardController;

/*
|--------------------------------------------------------------------------
| API Routes for Virtual Secretariat
|--------------------------------------------------------------------------
*/

// Routes عمومی (نیاز به احراز هویت)
Route::middleware(['auth:sanctum', 'user.can_login'])
    ->prefix('v1/virtual-secretariat')->group(function () {

    // ═══════ درخواست‌های کاربران ═══════
    Route::prefix('requests')->group(function () {
        Route::get('/', [RequestController::class, 'index'])->name('requests.index');
        Route::post('/', [RequestController::class, 'store'])->name('requests.store');
        Route::get('/{request}', [RequestController::class, 'show'])->name('requests.show');
        Route::get('/{request}/workflow', [RequestController::class, 'workflowStatus'])
            ->name('requests.workflow');
    });

    // ═══════ مدیریت قالب‌ها (ادمین) ══════
// ✅ روش صحیح - با parameter name 'template'
    Route::middleware(['permission:virtual_secretariat.manage_templates'])
        ->prefix('templates')
        ->group(function () {
            Route::get('/', [TemplateController::class, 'index'])->name('templates.index');
            Route::post('/', [TemplateController::class, 'store'])->name('templates.store');
            Route::get('/{template}', [TemplateController::class, 'show'])->name('templates.show');
            Route::put('/{template}', [TemplateController::class, 'update'])->name('templates.update');
            Route::delete('/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');
            Route::post('/{template}/toggle-status', [TemplateController::class, 'toggleStatus'])
                ->name('templates.toggle-status');

        });


        // مدیریت درخواست‌ها
        Route::middleware(['permission:virtual_secretariat.manage_templates'])
            ->prefix('requests')->group(function () {
            Route::get('/', [AdminRequestController::class, 'index'])
                ->name('admin.requests.index');

            Route::get('/statistics', [AdminRequestController::class, 'statistics'])
                ->name('admin.requests.statistics');

            Route::get('/export', [AdminRequestController::class, 'export'])
                ->name('admin.requests.export');

            Route::get('/{vsRequest}', [AdminRequestController::class, 'show'])
                ->name('admin.requests.show');

            Route::get('/{vsRequest}/workflow', [AdminRequestController::class, 'workflowStatus'])
                ->name('admin.requests.workflow');

            Route::put('/{vsRequest}/status', [AdminRequestController::class, 'updateStatus'])
                ->name('admin.requests.update-status');

            Route::post('/{vsRequest}/retry', [AdminRequestController::class, 'retry'])
                ->name('admin.requests.retry');

            Route::delete('/{vsRequest}', [AdminRequestController::class, 'destroy'])
                ->name('admin.requests.destroy');
        });




    // ═══════ داشبورد ═══════
    Route::prefix('dashboard')->group(function () {
        Route::get('/statistics', [DashboardController::class, 'statistics'])
            ->name('dashboard.statistics');
        Route::get('/status-chart', [DashboardController::class, 'statusChart'])
            ->name('dashboard.status-chart');
        Route::get('/recent-requests', [DashboardController::class, 'recentRequests'])
            ->name('dashboard.recent-requests');
    });



            // روت‌های داشبورد فرآیندها (فقط مدیران)
            Route::prefix('workflow-dashboard')
                ->middleware(['auth:sanctum', 'workflow.access'])
                ->group(function () {

                    Route::get('/statistics', [WorkflowDashboardController::class, 'getStatistics'])
                        ->name('workflow.statistics');

                    Route::get('/export', [WorkflowDashboardController::class, 'exportToExcel'])
                        ->name('workflow.export');
                });

});
