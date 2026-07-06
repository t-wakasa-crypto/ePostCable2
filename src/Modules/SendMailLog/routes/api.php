<?php

use Illuminate\Support\Facades\Route;
use Modules\SendMailLog\Http\Controllers\SendMailLogController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('sendmaillogs', SendMailLogController::class)->names('sendmaillog');
});
