<?php

use Illuminate\Support\Facades\Route;
use Modules\ShipmentFetch\Http\Controllers\ShipmentFetchController;

/*
 * 出荷取得履歴ルート（詳細設計 §2.1 / FR-11）。
 * 一覧は general/admin、バッチ手動起動は admin のみ。
 */
Route::middleware('auth')->group(function () {
    Route::get('/shipment-fetch-logs', [ShipmentFetchController::class, 'index'])->name('shipment-fetch-logs.index');

    Route::middleware('admin')->group(function () {
        Route::post('/shipment-fetch-logs/run-batch', [ShipmentFetchController::class, 'runBatch'])->name('shipment-fetch-logs.runBatch');
    });
});
