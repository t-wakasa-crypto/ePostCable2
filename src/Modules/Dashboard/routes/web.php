<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Http\Controllers\DashboardController;

/*
 * ダッシュボードルート（詳細設計 §2.1 / FR-07）。
 * ルート（/）は認証必須（auth）。
 */
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
});
