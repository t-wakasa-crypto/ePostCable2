<?php

use Illuminate\Support\Facades\Route;
use Modules\DeliveryNote\Http\Controllers\DeliveryNoteController;

/*
 * 納品書ルート（詳細設計 §2.1 / FR-09）。
 * auth ミドルウェアで保護し、管理者専用操作は admin を追加する（FR-16 / FR-17）。
 * 全 POST は CSRF（VerifyCsrfToken）で保護される（NFR-S-05）。
 *
 * 注意: ワイルドカード {deliveryNote} より前に固定パス（csv/bulk-requeue/run-batch）を定義する。
 */
Route::middleware('auth')->group(function () {
    Route::get('/delivery-notes', [DeliveryNoteController::class, 'index'])->name('delivery-notes.index');
    Route::get('/delivery-notes/csv', [DeliveryNoteController::class, 'downloadCsv'])->name('delivery-notes.csv');

    Route::middleware('admin')->group(function () {
        Route::post('/delivery-notes/bulk-requeue', [DeliveryNoteController::class, 'bulkRequeue'])->name('delivery-notes.bulkRequeue');
        Route::post('/delivery-notes/run-batch', [DeliveryNoteController::class, 'runBatch'])->name('delivery-notes.runBatch');
        Route::post('/delivery-notes/{deliveryNote}/emails', [DeliveryNoteController::class, 'updateEmails'])->name('delivery-notes.emails');
    });

    Route::get('/delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'show'])->name('delivery-notes.show');
    Route::post('/delivery-notes/{deliveryNote}/resend', [DeliveryNoteController::class, 'resend'])->name('delivery-notes.resend');
    Route::get('/delivery-notes/{deliveryNote}/pdf', [DeliveryNoteController::class, 'downloadPdf'])->name('delivery-notes.pdf');
});
