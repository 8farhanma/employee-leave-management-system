<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
| Prefix: /api/v1/ (Sudah diatur di bootstrap/app.php)
*/

// --- 1. Public Routes (Bisa diakses tanpa login) ---
Route::get('/status', function () {
    return response()->json([
        'app_name' => 'ELMS API',
        'version' => 'v1.0',
        'status' => 'Connected'
    ]);
});

// --- 2. Protected Routes (Harus Login/Punya Token) ---
Route::middleware('auth:sanctum')->group(function () {
    
    // Ambil data profil user yang sedang login
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Contoh Grouping untuk fitur ELMS ke depannya
    // Route::prefix('leaves')->group(function () {
    //     Route::get('/', [LeaveController::class, 'index']);
    //     Route::post('/apply', [LeaveController::class, 'store']);
    // });
    
});