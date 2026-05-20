<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:api', 'throttle:60,1'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('role:admin,editor')->group(function () {
        Route::post('/workflows', [WorkflowController::class, 'store']);
        Route::put('/workflows/{workflow}', [WorkflowController::class, 'update']);
    });

    Route::middleware('role:admin,editor,viewer')->group(function () {
        Route::get('/workflows', [WorkflowController::class, 'index']);
    });
});
