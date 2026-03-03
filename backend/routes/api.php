<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\JenisCutiController;

// ────────────────────────────────────────────────────────────────
// PUBLIC ROUTES - tidak perlu token
// ────────────────────────────────────────────────────────────────
Route::prefix('auth')->name('auth.')->group(function () {

    Route::post('/login', [AuthController::class, 'login'])
        ->name('login');

    // Health check - Cek API hidup
    Route::get('/ping', fn()=>response()->json([
        'success'   => true,
        'message'   => 'API ELMS aktif.',
        'version'   => '1.0.0',
        'time'      => now()->toDateTimeString(),
    ]))->name('ping');
});

// ────────────────────────────────────────────────────────────────
// PROTECTED ROUTES - wajib login (semua role)
// ────────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
    
    // Profil karyawan yang sedang login
    Route::get('/me', [AuthController::class, 'me'])->name('me');

    // Jenis cuti - bisa diakses semua role (butuh untuk form pengajuan)
    Route::prefix('jenis-cuti')->name('jenis-cuti.')->group(function () {
        Route::get('/', [JenisCutiController::class, 'index'])->name('index');
        Route::get('/{jenisCuti}', [JenisCutiController::class, 'show'])->name('show');
    });

    // ────────────────────────────────────────────────────────────────
    // ADMIN ONLY
    // ────────────────────────────────────────────────────────────────
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        
        // Register karyawan baru (admin only)
        Route::post('/karyawan/register', [AuthController::class, 'register'])
            ->name('karyawan.register');

        // Placeholder — akan diisi Hari 6–13
        // Route::apiResource('cuti', AdminCutiController::class);
        // Route::apiResource('karyawan', AdminKaryawanController::class);
    });

    // ────────────────────────────────────────────────────────────────
    // KARYAWAN ROUTES
    // ────────────────────────────────────────────────────────────────
    // Placeholder — akan diisi Hari 8–10
    // Route::apiResource('cuti', CutiController::class);
});