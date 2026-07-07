<?php

use App\Http\Controllers\Api\RunnerController;
use Illuminate\Support\Facades\Route;

Route::prefix('runner')->group(function () {
    Route::post('register', [RunnerController::class, 'register']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('heartbeat', [RunnerController::class, 'heartbeat']);
        Route::post('unregister', [RunnerController::class, 'unregister']);
        Route::post('executions/{execution}/output', [RunnerController::class, 'output']);
        Route::post('executions/{execution}/finish', [RunnerController::class, 'finish']);
    });
});
