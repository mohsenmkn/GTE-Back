<?php

// Modules/Contract/Routes/api.php

use Modules\Contract\App\Http\Controllers\ContractorController;
use Modules\Contract\App\Http\Controllers\ContractController;
use Modules\Contract\App\Http\Controllers\ProgressReportController;

Route::middleware(['auth:sanctum', 'user.can_login'])->prefix('v1')->group(function ()
{

    Route::apiResource('contracts', ContractController::class);

    // مسیرهای اضافی
    Route::get('contracts/{contract}/summary', [ContractController::class, 'summary']);
    Route::patch('contracts/{contract}/status', [ContractController::class, 'changeStatus']);

    // ✅ پیمانکاران
    Route::apiResource('contractors', ContractorController::class);
    Route::get('contractors-all', [ContractorController::class, 'index']);
});
