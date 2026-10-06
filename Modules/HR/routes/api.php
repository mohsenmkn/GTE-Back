<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\HR\App\Http\Controllers\EmployeeController;
use Modules\HR\App\Http\Controllers\OrganizationalPositionController;
use Modules\HR\App\Http\Controllers\OrganizationalUnitController;
use Modules\HR\App\Http\Controllers\OrgChartController;
use Modules\HR\App\Http\Controllers\OrgStructureController;
use Modules\User\App\Http\Controllers\UserController;

/*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    |
    | Here is where you can register API routes for your application. These
    | routes are loaded by the RouteServiceProvider within a group which
    | is assigned the "api" middleware group. Enjoy building your API!
    |
*/

Route::middleware(['auth:sanctum', 'user.can_login'])->prefix('v1/hr')->name('hr.')->group(function () {
    // ─────────────────────────────────────────────
    //  چارت سازمانی
    // ─────────────────────────────────────────────

    // GET /api/v1/hr/org-chart
    // دریافت درخت کامل چارت سازمانی + پرسنل هر واحد
    Route::get('/org-chart', [OrgChartController::class, 'index'])
        ->middleware('permission:hr.view')
        ->name('org-chart.index');

    // GET /api/v1/hr/org-chart/units
    // لیست تخت واحدها (برای Select، فیلتر و جستجو)
    Route::get('/org-chart/units', [OrgChartController::class, 'units'])
        ->middleware('permission:hr.view')
        ->name('org-chart.units');

    // GET /api/v1/hr/org-chart/search?q=نام
    // جستجو در چارت (واحد یا پرسنل)
    Route::get('/org-chart/search', [OrgChartController::class, 'search'])
        ->middleware('permission:hr.view')
        ->name('org-chart.search');

    // ── ✅ پرسنل (جدید) ──
    Route::get('/employees', [EmployeeController::class, 'index'])
        ->middleware('permission:hr.view')
        ->name('employees.index');

    Route::get('/employees/{user}', [EmployeeController::class, 'show'])
        ->middleware('permission:hr.view')
        ->whereNumber('user')
        ->name('employees.show');

    // ✅ اطلاعات خانواده کارمند
    Route::get('/employees/{user}/relatives', [EmployeeController::class, 'relatives'])
        ->middleware('permission:hr.view')
        ->whereNumber('user')
        ->name('employees.relatives');

    // ✅ sync دستی یک کاربر (فقط ادمین)
    Route::post('/sync/user/{user}', function (\Modules\Auth\App\Models\User $user) {
        $position = app(\Modules\HR\App\Services\GtarabarSyncService::class)
            ->syncUser($user, 'api');

        if ($position) {
            return response()->json([
                'message' => 'sync با موفقیت انجام شد.',
                'position' => [
                    'post_title' => $position->post_title,
                    'job_title'  => $position->job_title,
                    'unit'       => $position->unit?->title,
                    'synced_at'  => $position->synced_at,
                ],
            ]);
        }

        return response()->json(['message' => 'خطا در sync'], 500);
    })
        ->middleware('permission:hr.manage')
        ->name('sync.user');

// ✅ وضعیت sync
    Route::get('/sync/status', function () {
        return response()->json([
            'positions' => \Modules\HR\App\Models\EmployeePosition::count(),
            'relatives' => \Modules\HR\App\Models\EmployeeRelative::count(),
            'units'     => \Modules\HR\App\Models\OrganizationalUnit::count(),
            'last_sync' => \Modules\HR\App\Models\EmployeePosition::max('synced_at'),
            'today_success' => \Modules\HR\App\Models\HRSyncLog::today()->success()->count(),
            'today_failed'  => \Modules\HR\App\Models\HRSyncLog::today()->failed()->count(),
        ]);
    })
        ->middleware('permission:hr.manage')
        ->name('sync.status');

    Route::get('/employees/{user}/history', [EmployeeController::class, 'history'])
        ->middleware('permission:hr.view')
        ->whereNumber('user')
        ->name('employees.history');

    Route::get('/employees/{user}/history/details', [EmployeeController::class, 'historyDetails'])
        ->middleware('permission:hr.view')
        ->whereNumber('user')
        ->name('employees.history.details');

    // ✅ تردد کارمند
    Route::get('/employees/{user}/attendance', [EmployeeController::class, 'attendance'])
        ->middleware('permission:hr.view')
        ->whereNumber('user')
        ->name('employees.attendance');

    // ✅ آموزش پرسنل
    Route::get('/employees/{user}/training', [EmployeeController::class, 'training'])
        ->middleware('permission:hr.view')
        ->whereNumber('user')
        ->name('employees.training');

    Route::post('/employees/{user}/training/sync', [EmployeeController::class, 'syncTraining'])
        ->middleware('permission:hr.manage')
        ->whereNumber('user')
        ->name('employees.training.sync');

    Route::post('/employees/{user}/training/enrich-dates', [EmployeeController::class, 'enrichTrainingDates'])
        ->middleware('permission:hr.manage')
        ->whereNumber('user')
        ->name('employees.training.enrich');

// ── مدیریت ساختار سازمانی (فقط ادمین) ──
    Route::get('/org-structure', [OrgStructureController::class, 'index'])
        ->middleware('permission:hr.manage')
        ->name('org-structure.index');

//    Route::put('/org-structure/{unit}', [OrgStructureController::class, 'updateParent'])
//        ->middleware('permission:hr.manage')
//        ->whereNumber('unit')
//        ->name('org-structure.update');

    Route::post('/org-structure/reset', [OrgStructureController::class, 'reset'])
        ->middleware('permission:hr.manage')
        ->name('org-structure.reset');

    // ✅ ایجاد واحد جدید
    Route::post('/org-structure', [OrgStructureController::class, 'store'])
        ->middleware('permission:hr.manage')
        ->name('org-structure.store');

// ✅ ویرایش واحد
    Route::put('/org-structure/{unit}', [OrgStructureController::class, 'update'])
        ->middleware('permission:hr.manage')
        ->whereNumber('unit')
        ->name('org-structure.update');

    Route::prefix('/organizational-units')->group(function () {
        Route::get('/', [OrganizationalUnitController::class, 'index']);
        Route::post('/{organizationalUnit}/move', [OrganizationalUnitController::class, 'move']);
    });

    Route::get('/users/search', [UserController::class, 'search'])->middleware('permission:hr.manage');

// ✅ حذف واحد
    Route::delete('/org-structure/{unit}', [OrgStructureController::class, 'destroy'])
        ->middleware('permission:hr.manage')
        ->whereNumber('unit')
        ->name('org-structure.destroy');

            Route::post(
                '/organizational-units/{organizationalUnit}/move',
                [OrganizationalUnitController::class, 'move']
            );
            Route::get(
                '/organizational-positions/tree',
                [OrganizationalPositionController::class, 'tree']
            );

            Route::post(
                '/organizational-positions/{organizationalPosition}/move',
                [OrganizationalPositionController::class, 'move']
            );


            Route::apiResource(
                'organizational-positions',
                OrganizationalPositionController::class
            );

});
