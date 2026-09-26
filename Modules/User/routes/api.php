<?php

use Illuminate\Support\Facades\Route;
use Modules\User\App\Http\Controllers\UserController;

Route::middleware(['auth:sanctum', 'user.can_login'])->prefix('v1')->name('api.')->group(function () {



    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:users.read')
        ->name('users.index');

    Route::prefix('users')->group(function () {
        Route::get('/selectable', [UserController::class, 'selectable'])
            ->middleware('permission:groups.assign_users');

        Route::get('/positions', [UserController::class, 'positions'])
            ->middleware('permission:groups.assign_users');
    });


    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:users.create')
        ->name('users.store');

    Route::get('/users/{user}', [UserController::class, 'show'])
        ->middleware('permission:users.read')
        ->name('users.show');

    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.update')
        ->name('users.update');

    Route::patch('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.update')
        ->name('users.patch');

    Route::delete('/users/{user}', [UserController::class, 'destroy'])
        ->middleware('permission:users.delete')
        ->name('users.destroy');




});
