<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\CutiController;
use App\Http\Controllers\Api\Karyawan\JenisCutiController;

// ────────────────────────────────────────────────────────────────
// PUBLIC ROUTES - tidak perlu token
// ────────────────────────────────────────────────────────────────
Route::prefix('auth')->name('auth.')->group(function () {

    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
    });

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

    // Cuti Karyawan
    Route::prefix('cuti')->name('cuti.')->group(function () {
        Route::post('/', [CutiController::class, 'store'])->name('store');

        // Route::get('/', [CutiController::class, 'index'])->name('index');
        // Route::get('/{id}', [CutiController::class, 'show'])->name('show');
        // Route::delete('/{id}', [CutiController::class, 'destroy'])->name('destroy');
    });

    // ────────────────────────────────────────────────────────────────
    // ADMIN ONLY
    // ────────────────────────────────────────────────────────────────
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        
        // Register karyawan baru (admin only)
        Route::post('/karyawan/register', [AuthController::class, 'register'])
            ->name('karyawan.register');

        // Route::apiResource('cuti', AdminCutiController::class);
        // Route::apiResource('karyawan', AdminKaryawanController::class);
    });

    // ────────────────────────────────────────────────────────────────
    // KARYAWAN ROUTES
    // ────────────────────────────────────────────────────────────────
    // Route::apiResource('cuti', CutiController::class);
});