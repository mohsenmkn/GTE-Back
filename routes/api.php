<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

use App\Http\Controllers\AuthController;

/*Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/forgot-password/reset', [AuthController::class, 'resetPassword']);
Route::post('/forgot-password/verify-otp', [AuthController::class, 'verifyOtp']);
Route::get('/captcha', [AuthController::class, 'getCaptcha']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    // سایر روت‌های ماژول‌ها باید داخل این گروه قرار بگیرند تا محافظت شوند
});*/
