<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Project\App\Http\Controllers\ProjectController;
use Modules\WBS\App\Http\Controllers\TaskController;

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

Route::middleware(['auth:sanctum', 'user.can_login'])->prefix('v1')->name('api.')->group(function () {
    Route::get('projects/schema', [ProjectController::class, 'schema']);
    Route::apiResource('projects', ProjectController::class);

});
