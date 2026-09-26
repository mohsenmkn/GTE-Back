<?php

use Illuminate\Support\Facades\Route;
use Modules\Library\App\Http\Controllers\BookController;
use Modules\Library\App\Http\Controllers\ReservationController;
use Modules\Library\App\Http\Controllers\CategoryController;
use Modules\Library\App\Http\Controllers\DashboardController;
use Modules\Library\App\Http\Controllers\NotificationController;

Route::middleware(['auth:sanctum', 'user.can_login'])->prefix('library')->group(function () {

    // ========== کاربر عادی ==========
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/my-reservations', [ReservationController::class, 'myReservations']);
    Route::post('/reservations', [ReservationController::class, 'store'])
        ->middleware('permission:libraryreservations.create');
    Route::put('/reservations/{id}/cancel', [ReservationController::class, 'cancel']);
    Route::get('/my-notifications', [NotificationController::class, 'index']);
    Route::put('/my-notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/my-notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // ========== مدیر کتابخانه ==========

    // 🔑 تغییر: library.books.manage → librarybooks.manage
    Route::middleware(['permission:librarybooks.manage'])->group(function () {
        Route::post('/admin/books', [BookController::class, 'store']);
        Route::put('/admin/books/{id}', [BookController::class, 'update']);
        Route::delete('/admin/books/{id}', [BookController::class, 'destroy']);
        Route::post('/admin/books/{id}/copies', [BookController::class, 'storeCopy']);
        Route::put('/admin/copies/{id}', [BookController::class, 'updateCopy']);
        Route::delete('/admin/copies/{id}', [BookController::class, 'destroyCopy']);
        Route::post('/admin/categories', [CategoryController::class, 'store']);
        Route::put('/admin/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/admin/categories/{id}', [CategoryController::class, 'destroy']);
    });

    // 🔑 تغییر: library.reservations.manage → libraryreservations.manage
    Route::middleware(['permission:libraryreservations.manage'])->group(function () {
        Route::get('/admin/reservations', [ReservationController::class, 'adminIndex']);
        Route::put('/admin/reservations/{id}/approve', [ReservationController::class, 'approve']);
        Route::put('/admin/reservations/{id}/reject', [ReservationController::class, 'reject']);
        Route::put('/admin/reservations/{id}/pickup', [ReservationController::class, 'pickup']);
        Route::put('/admin/reservations/{id}/return', [ReservationController::class, 'returnBook']);
    });

    // 🔑 تغییر: library.statistics.view → librarystatistics.view
    Route::middleware(['permission:librarystatistics.view'])->group(function () {
        Route::get('/admin/statistics', [DashboardController::class, 'statistics']);
    });
});
