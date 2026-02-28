<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login - bisa pakai email atau nik
     */
    public function login(request $request): JsonResponse
    {
        $request->validate([
            'login' => 'required|string', //bisa email atau nik
            'password' => 'required|string',
        ]);

        // Coba cari berdasarkan email dulu, lalu nik
        $karyawan = Karyawan::where('email', $request->login)
                            ->orWhere('nik', $request->login)
                            ->first();

        // Cek karyawan ada dan password cocok
        if (!$karyawan || !Hash::check($request->password, $karyawan->password)) {
            throw ValidationException::withMessages([
                'login' => ['NIK/Email atau password salah.'],
            ]);
        }

        // Hapus token lama (single session per user)
        $karyawan->tokens()->delete();

        // BUat token baru dengan abilities sesuai role
        $abilities = $karyawan->is_admin
            ? ['admin', 'karyawan'] // Admin bisa akses semua
            : ['karyawan'];         // Karyawan biasa hanya bisa akses fitur karyawan

        $token = $karyawan->createToken(
            name: 'auth-token-' . $karyawan->nik,
            abilities: $abilities,
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'karyawan' => [
                    'id' => $karyawan->id,
                    'nik' => $karyawan->nik,
                    'nama' => $karyawan->nama,
                    'email' => $karyawan->email,
                    'departemen' => $karyawan->departemen,
                    'role' => $karyawan->role,
                    'sisa_cuti' => $karyawan->sisa_cuti,
                ],
            ],
        ], 200);
    }

    /**
     * Logout - hapus token yang sedang digunakan
     */
    public function logout(Request $request): JsonResponse
    {
        // Hapus hanya token yang digunakan sekarang
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * Profil karyawan yang sedang login
     */
    public function me(Request $request): JsonResponse
    {
        $karyawan = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $karyawan->id,
                'nik' => $karyawan->nik,
                'nama' => $karyawan->nama,
                'email' => $karyawan->email,
                'departemen' => $karyawan->departemen,
                'role' => $karyawan->role,
                'sisa_cuti' => $karyawan->sisa_cuti,
            ],
        ]);
    }
}
