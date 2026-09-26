<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
// Modules/Document/Routes/api.php

use Modules\Document\App\Http\Controllers\DocumentController;

Route::middleware(['auth:sanctum', 'user.can_login'])->prefix('v1')->group(function () {
    Route::get('documents', [DocumentController::class, 'index']);
    Route::post('documents/upload', [DocumentController::class, 'upload']);
    Route::get('documents/{document}', [DocumentController::class, 'show']);
    Route::get('documents/{document}/download', [DocumentController::class, 'download']);
    Route::delete('documents/{document}', [DocumentController::class, 'destroy']);
});
