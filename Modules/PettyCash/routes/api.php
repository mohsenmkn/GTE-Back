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

// Modules/PettyCash/Routes/api.php

use Modules\PettyCash\App\Http\Controllers\PettyCashController;
use Modules\PettyCash\App\Http\Controllers\PettyCashTransactionController;

Route::middleware(['auth:sanctum', 'user.can_login'])->prefix('v1')->group(function () {

    Route::apiResource('petty-cashes', PettyCashController::class);

    Route::prefix('petty-cashes/{pettyCash}')->group(function () {
        Route::get('transactions', [PettyCashTransactionController::class, 'index']);
        Route::post('transactions', [PettyCashTransactionController::class, 'store']);
        Route::get('transactions/{transaction}', [PettyCashTransactionController::class, 'show']);
        Route::delete('transactions/{transaction}', [PettyCashTransactionController::class, 'destroy']);
        Route::get('summary', [PettyCashTransactionController::class, 'summary']);
    });
});
