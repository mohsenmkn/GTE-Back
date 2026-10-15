<?php

use Illuminate\Support\Facades\Route;
use Modules\TripExpense\App\Http\Controllers\DriverAssignmentController;
use Modules\TripExpense\App\Http\Controllers\ExpenseCategoryController;
use Modules\TripExpense\App\Http\Controllers\ExpenseLimitController;
use Modules\TripExpense\App\Http\Controllers\ReportController;
use Modules\TripExpense\App\Http\Controllers\TripController;
use Modules\TripExpense\App\Http\Controllers\TripExpenseController;
use Modules\TripExpense\App\Http\Controllers\TruckController;

Route::prefix('v1/trip-expense')
    ->middleware(['auth:sanctum'])
    ->group(function () {

        // ═══════ روت‌های عمومی ═══════
        Route::get('/expense-categories/options', [ExpenseCategoryController::class, 'publicIndex'])
            ->name('trip-expense.expense-categories.options');

        // ═══════ روت‌های راننده ═══════
        Route::middleware(['permission:trip_expense.view'])->group(function () {

            Route::get('/my-trips', [TripController::class, 'myTrips'])
                ->name('trip-expense.my-trips')
                ->middleware('permission:trip_expense.view_trips');

            Route::post('/trips', [TripController::class, 'store'])
                ->name('trip-expense.trips.store')
                ->middleware('permission:trip_expense.create_trip');

            Route::get('/trips/{trip}', [TripController::class, 'show'])
                ->name('trip-expense.trips.show')
                ->middleware('permission:trip_expense.view_trips');

            Route::patch('/trips/{trip}/finish', [TripController::class, 'finish'])
                ->name('trip-expense.trips.finish');

            Route::get('/trips/{trip}/expenses', [TripExpenseController::class, 'index'])
                ->name('trip-expense.trips.expenses.index')
                ->middleware('permission:trip_expense.view_expenses');

            Route::post('/trips/{trip}/expenses', [TripExpenseController::class, 'store'])
                ->name('trip-expense.trips.expenses.store')
                ->middleware('permission:trip_expense.create_expense');

            Route::delete('/expenses/{expense}', [TripExpenseController::class, 'destroy'])
                ->name('trip-expense.expenses.destroy');

            Route::get('/expenses/{expense}/receipt', [TripExpenseController::class, 'receipt'])
                ->name('trip-expense.expenses.receipt');

            Route::get('/trips/{trip}/remaining-budget', [TripExpenseController::class, 'remainingBudget'])
                ->name('trip-expense.trips.remaining-budget');

            Route::get('/my-current-truck', [TruckController::class, 'myCurrentTruck'])
                ->name('trip-expense.my-current-truck')
                ->middleware('permission:trip_expense.create_trip');
        });

        // ═══════ روت‌های مدیر ═══════
        Route::middleware(['permission:trip_expense.manage'])->group(function () {

            Route::get('/trips', [TripController::class, 'index'])
                ->name('trip-expense.trips.index')
                ->middleware('permission:trip_expense.view_trips');

            Route::post('/expenses/{expense}/approve', [TripExpenseController::class, 'approve'])
                ->name('trip-expense.expenses.approve')
                ->middleware('permission:trip_expense.approve_expense');

            Route::post('/expenses/{expense}/reject', [TripExpenseController::class, 'reject'])
                ->name('trip-expense.expenses.reject')
                ->middleware('permission:trip_expense.reject_expense');


                Route::get('/expenses', [TripExpenseController::class, 'indexAll'])
                    ->name('trip-expense.expenses.index-all')
                    ->middleware('permission:trip_expense.view_expenses');


            // ═══════ مدیریت اختصاص راننده به کامیون ═══════
            Route::prefix('driver-assignments')->name('trip-expense.assignments.')->group(function () {
                Route::get('/', [DriverAssignmentController::class, 'index'])
                    ->name('index')
                    ->middleware('permission:trip_expense.manage_assignments_trucks');

                Route::post('/', [DriverAssignmentController::class, 'store'])
                    ->name('store')
                    ->middleware('permission:trip_expense.manage_assignments_trucks');

                Route::get('/available-drivers', [DriverAssignmentController::class, 'availableDrivers'])
                    ->name('available-drivers')
                    ->middleware('permission:trip_expense.manage_assignments_trucks');

                Route::get('/available-trucks', [DriverAssignmentController::class, 'availableTrucks'])
                    ->name('available-trucks')
                    ->middleware('permission:trip_expense.manage_assignments_trucks');

                Route::get('/driver/{driverId}/history', [DriverAssignmentController::class, 'driverHistory'])
                    ->name('driver-history')
                    ->middleware('permission:trip_expense.manage_assignments_trucks');

                Route::get('/truck/{truckId}/history', [DriverAssignmentController::class, 'truckHistory'])
                    ->name('truck-history')
                    ->middleware('permission:trip_expense.manage_assignments_trucks');

                Route::get('/{driverAssignment}', [DriverAssignmentController::class, 'show'])
                    ->name('show')
                    ->middleware('permission:trip_expense.manage_assignments_trucks');

                Route::put('/{driverAssignment}', [DriverAssignmentController::class, 'update'])
                    ->name('update')
                    ->middleware('permission:trip_expense.manage_assignments_trucks');

                Route::delete('/{driverAssignment}', [DriverAssignmentController::class, 'destroy'])
                    ->name('destroy')
                    ->middleware('permission:trip_expense.manage_assignments_trucks');

                Route::post('/{driverAssignment}/end', [DriverAssignmentController::class, 'endAssignment'])
                    ->name('end')
                    ->middleware('permission:trip_expense.manage_assignments_trucks');
                // دریافت کامیون فعلی یک راننده خاص
                Route::get('/trucks/driver/{userId}/current', [TruckController::class, 'getDriverCurrentTruck'])
                    ->name('trip-expense.trucks.driver-current')
                    ->middleware('permission:trip_expense.manage');

            });

            // ═══════ گزارش‌ها ═══════
            Route::prefix('reports')->name('trip-expense.reports.')->group(function () {
                Route::get('/summary', [ReportController::class, 'summary'])
                    ->name('summary')
                    ->middleware('permission:trip_expense.view_reports');

                Route::get('/by-driver', [ReportController::class, 'byDriver'])
                    ->name('by-driver')
                    ->middleware('permission:trip_expense.view_reports');

                Route::get('/by-category', [ReportController::class, 'byCategory'])
                    ->name('by-category')
                    ->middleware('permission:trip_expense.view_reports');

                Route::get('/by-truck', [ReportController::class, 'byTruck'])
                    ->name('by-truck')
                    ->middleware('permission:trip_expense.view_reports');

                Route::get('/by-route', [ReportController::class, 'byRoute'])
                    ->name('by-route')
                    ->middleware('permission:trip_expense.view_reports');

                Route::get('/by-month', [ReportController::class, 'byMonth'])
                    ->name('by-month')
                    ->middleware('permission:trip_expense.view_reports');

                Route::get('/by-status', [ReportController::class, 'byStatus'])
                    ->name('by-status')
                    ->middleware('permission:trip_expense.view_reports');

                Route::get('/expense-details', [ReportController::class, 'expenseDetails'])
                    ->name('expense-details')
                    ->middleware('permission:trip_expense.view_reports');

            });



            // ✅ اصلاح شد: اضافه کردن نام‌های اختصاصی
            Route::apiResource('trucks', TruckController::class)
                ->names([
                    'index' => 'trip-expense.trucks.index',
                    'store' => 'trip-expense.trucks.store',
                    'show' => 'trip-expense.trucks.show',
                    'update' => 'trip-expense.trucks.update',
                    'destroy' => 'trip-expense.trucks.destroy',
                ])
                ->middleware('permission:trip_expense.manage_trucks');

            // ✅ اصلاح شد: اضافه کردن نام‌های اختصاصی
            Route::apiResource('expense-categories', ExpenseCategoryController::class)
                ->names([
                    'index' => 'trip-expense.expense-categories.index',
                    'store' => 'trip-expense.expense-categories.store',
                    'show' => 'trip-expense.expense-categories.show',
                    'update' => 'trip-expense.expense-categories.update',
                    'destroy' => 'trip-expense.expense-categories.destroy',
                ])
                ->middleware('permission:trip_expense.manage_categories');

            // ✅ اصلاح شد: اضافه کردن نام‌های اختصاصی
            Route::apiResource('expense-limits', ExpenseLimitController::class)
                ->names([
                    'index' => 'trip-expense.expense-limits.index',
                    'store' => 'trip-expense.expense-limits.store',
                    'show' => 'trip-expense.expense-limits.show',
                    'update' => 'trip-expense.expense-limits.update',
                    'destroy' => 'trip-expense.expense-limits.destroy',
                ])
                ->middleware('permission:trip_expense.manage_limits');
        });
    });
