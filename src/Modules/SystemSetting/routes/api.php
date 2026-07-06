<?php

use Illuminate\Support\Facades\Route;
use Modules\SystemSetting\Http\Controllers\SystemSettingController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('systemsettings', SystemSettingController::class)->names('systemsetting');
});
