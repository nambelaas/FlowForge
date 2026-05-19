<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WorkflowController;
use App\Http\Controllers\Api\WorkflowExecutionController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {

    // Hanya Editor dan Admin yang bisa membuat atau memperbarui alur kerja
    Route::middleware('role:admin,editor')->group(function () {
        Route::post('/workflows', [WorkflowController::class, 'store']);
        Route::put('/workflows/{workflow}', [WorkflowController::class, 'update']);
        Route::post('/workflows/{id}/trigger', [WorkflowExecutionController::class, 'trigger']);
    });

    // Peran Viewer, Editor, dan Admin semuanya bisa melihat daftar alur kerja
    Route::middleware('role:admin,editor,viewer')->group(function () {
        Route::get('/workflows', [WorkflowController::class, 'index']);
    });
});
