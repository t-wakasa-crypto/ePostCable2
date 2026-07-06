<?php

use Illuminate\Support\Facades\Route;
use Modules\Invoice\Http\Controllers\InvoiceController;

/*
 * 請求書ルート（詳細設計 §2.1 / FR-08 / FR-15）。
 * auth ミドルウェアで保護し、管理者専用操作は admin を追加する（FR-16 / FR-17）。
 * 全 POST は CSRF（VerifyCsrfToken）で保護される（NFR-S-05）。
 *
 * 注意: ワイルドカード {invoice} より前に固定パス（csv/bulk-requeue/run-batch）を定義する。
 */
Route::middleware('auth')->group(function () {
    // 一覧・CSV（閲覧系・general/admin）
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/csv', [InvoiceController::class, 'downloadCsv'])->name('invoices.csv');

    // 管理者専用操作（admin）
    Route::middleware('admin')->group(function () {
        Route::post('/invoices/bulk-requeue', [InvoiceController::class, 'bulkRequeue'])->name('invoices.bulkRequeue');
        Route::post('/invoices/run-batch', [InvoiceController::class, 'runBatch'])->name('invoices.runBatch');
        Route::post('/invoices/{invoice}/emails', [InvoiceController::class, 'updateEmails'])->name('invoices.emails');
    });

    // 詳細・手動再送・PDF（general/admin）
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/resend', [InvoiceController::class, 'resend'])->name('invoices.resend');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
});
