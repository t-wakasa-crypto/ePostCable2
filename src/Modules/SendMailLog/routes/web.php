<?php

use Illuminate\Support\Facades\Route;
use Modules\SendMailLog\Http\Controllers\SendMailLogController;

/*
 * メール送信履歴ルート（詳細設計 §2.1 / FR-10）。
 * 閲覧のみ（general/admin）。complete 機能は廃止（OQ-08）。
 */
Route::middleware('auth')->group(function () {
    Route::get('/send-mail-logs', [SendMailLogController::class, 'index'])->name('send-mail-logs.index');
    Route::get('/send-mail-logs/{sendmaillog}', [SendMailLogController::class, 'show'])->name('send-mail-logs.show');
});
