<?php

use Illuminate\Support\Facades\Route;
use Modules\ShipmentFetch\Http\Controllers\ShipmentFetchController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('shipmentfetches', ShipmentFetchController::class)->names('shipmentfetch');
});
