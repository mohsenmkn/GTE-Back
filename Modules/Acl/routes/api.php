<?php

use Illuminate\Support\Facades\Route;
use Modules\Acl\App\Http\Controllers\AclController;
use Modules\Acl\App\Http\Controllers\GroupController;

Route::middleware(['auth:sanctum', 'user.can_login'])->prefix('v1')->name('api.')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Roles (موجود - بدون تغییر)
    |--------------------------------------------------------------------------
    */
    Route::get('acl/roles/', [AclController::class, 'index']);
    Route::get('acl/roles/all', [AclController::class, 'getAllRoles'])
        ->middleware('permission:roles.update');
    Route::get('acl/permissions/all', [AclController::class, 'getAllPermissions'])
        ->middleware('permission:roles.update');
    Route::post('acl/roles/', [AclController::class, 'store'])
        ->middleware('permission:roles.create');
    Route::put('acl/roles/{id}', [AclController::class, 'update'])
        ->middleware('permission:roles.update');
    Route::delete('acl/roles/{id}', [AclController::class, 'destroy'])
        ->middleware('permission:roles.delete');

    /*
    |--------------------------------------------------------------------------
    | Groups (اصلاح‌شده - با استاندارد پروژه شما)
    |--------------------------------------------------------------------------
    */
    Route::prefix('acl/groups')->name('groups.')->group(function () {

        Route::get('/', [GroupController::class, 'index'])
            ->name('index')
            ->middleware('permission:groups.read');

        Route::get('/all', [GroupController::class, 'all'])
            ->name('all')
            ->middleware('permission:groups.read');

        Route::get('/{group}', [GroupController::class, 'show'])
            ->name('show')
            ->middleware('permission:groups.read');

        Route::post('/', [GroupController::class, 'store'])
            ->name('store')
            ->middleware('permission:groups.create');

        Route::put('/{group}', [GroupController::class, 'update'])
            ->name('update')
            ->middleware('permission:groups.update');

        Route::delete('/{group}', [GroupController::class, 'destroy'])
            ->name('destroy')
            ->middleware('permission:groups.delete');

        Route::post('/{group}/assign-users', [GroupController::class, 'assignUsers'])
            ->name('assign_users')
            ->middleware('permission:groups.assign_users');

        Route::post('/{group}/remove-users', [GroupController::class, 'removeUsers'])
            ->name('remove_users')
            ->middleware('permission:groups.assign_users');

        Route::get('/{group}/users', [GroupController::class, 'users'])
            ->name('users')
            ->middleware('permission:groups.read');
    });
});
