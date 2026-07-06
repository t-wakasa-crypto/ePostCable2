<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\LoginController;

/*
 * 認証ルート（詳細設計 §2.1 / FR-16 / FR-17）。
 * 全 POST は CSRF（VerifyCsrfToken）で保護される（NFR-S-05）。
 */

// ログイン画面・ログイン処理（guest）
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
});

// ログアウト（auth）
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
