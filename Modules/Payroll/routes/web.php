<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Payroll is exposed through the API routes. The old resource route pointed
// to a controller that no longer exists and made Laravel fail while compiling
// the route list (and during application boot in some environments).
Route::view('/payroll', 'Payroll::index')->name('payroll.index');
