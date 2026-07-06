<?php

use Illuminate\Support\Facades\Route;
use Modules\DeliveryNote\Http\Controllers\DeliveryNoteController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('deliverynotes', DeliveryNoteController::class)->names('deliverynote');
});
