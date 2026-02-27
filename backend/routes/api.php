<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
| Prefix: /api/v1/ (Sudah diatur di bootstrap/app.php)
*/

// --- 1. Public Routes (tidak perlu token) ---
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// --- 2. Protected Routes (Harus Punya Token) ---
Route::middleware('auth:sanctum', 'karyawan')->group(function () {
    
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    });

    // Placeholder untuk hari-hari berikutnya
    // Route::apiResource('/cuti', CutiController::class);

    // Admin Routes (Hanya untuk admin)
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Placeholder
        // Route::get('/cuti', [AdminCutiController::class, 'index']);
    
});