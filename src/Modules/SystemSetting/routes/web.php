<?php

use Illuminate\Support\Facades\Route;
use Modules\SystemSetting\Http\Controllers\SystemSettingController;

/*
 * システム設定ルート（詳細設計 §2.1 / FR-13）。admin 限定（auth + admin）。
 */
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/system-settings', [SystemSettingController::class, 'index'])->name('system-settings.index');
    Route::post('/system-settings', [SystemSettingController::class, 'update'])->name('system-settings.update');
    Route::post('/system-settings/test-mail', [SystemSettingController::class, 'sendTestMail'])->name('system-settings.testMail');
});
