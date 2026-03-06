<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterKaryawanRequest;
use App\Http\Resources\KaryawanResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Karyawan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponseTrait;

    // ────────────────────────────────────────────────────────────────
    // POST /api/auth/register
    // Hanya admin yang bisa daftarkan karyawan baru
    // ────────────────────────────────────────────────────────────────

    public function register(RegisterKaryawanRequest $request): JsonResponse
    {
        $karyawan = Karyawan::create([
            'nik'           => strtoupper($request->nik),
            'nama'          => $request->nama,
            'departemen'    => $request->departemen,
            'email'         => strtolower($request->email),
            'password'      => Hash::make($request->password),
            'role'          => $request->role ?? 'karyawan',
            'sisa_cuti'     => $request->sisa_cuti ?? 12,

        ]);

        return $this->createdResponse(
            new KaryawanResource($karyawan),
            "Karyawan {$karyawan->nama} berhasil didaftarkan."
        );
    }

    // ────────────────────────────────────────────────────────────────
    // POST /api/auth/login
    // Login dengan email atau nik
    // ────────────────────────────────────────────────────────────────
    
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login'     => ['required', 'string'],       //bisa email atau nik
            'password'  => ['required','string'],
        ], [
            'login.required'    => 'NIK atau email wajib diisi',
            'password.required' => 'Password wajib diisi',
        ]);

        // Coba cari karyawan berdasarkan email dulu, lalu nik
        $karyawan = Karyawan::where('email', $request->login)
                            ->orWhere('nik', strtoupper($request->login))
                            ->first();

        // Cek karyawan ada dan password cocok
        if (! $karyawan || ! Hash::check($request->password, $karyawan->password)) {
            throw ValidationException::withMessages([
                'login' => ['NIK/Email atau password salah.'],
            ]);
        }

        // Hapus token lama -> single active session
        $karyawan->tokens()->delete();

        // Buat token baru dengan abilities sesuai role
        $abilities = $karyawan->isAdmin
            ? ['admin', 'karyawan']         // Admin bisa akses semua
            : ['karyawan'];                 // Karyawan biasa hanya bisa akses fitur karyawan

        $token = $karyawan->createToken(
            name: "auth_{$karyawan->nik}",
            abilities: $abilities,
        )->plainTextToken;

        return $this->successResponse([
            'token'     => $token,
            'token_type'=> 'Bearer',
            'expires_in'=> config('sanctum.expiration') . ' menit',
            'karyawan'  => new KaryawanResource($karyawan),
        ], 'Login berhasil. Selamat datang, ' . $karyawan->nama . '!');
    }

    // ────────────────────────────────────────────────────────────────
    // POST /api/auth/logout
    // Cabut token yang sedang aktif
    // ────────────────────────────────────────────────────────────────
    
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $nama = $user->nama;

        // Hapus semua token milik user ini — paling reliable di test & production
        $user->tokens()->delete();

        return $this->successResponse(
            message: "Sampai jumpa, {$nama}! Anda berhasil logout."
            );
    }

    // ────────────────────────────────────────────────────────────────
    // GET /api/me
    // Profil karyawan yang sedang login + sisa cuti
    // ────────────────────────────────────────────────────────────────
    
    public function me(Request $request): JsonResponse
    {
        $karyawan = $request->user();

        $totalPengajuan = $karyawan->cutiKaryawan()->count();
        $totalPending   = $karyawan->cutiKaryawan()->where('status', 'pending')->count();
        $totalApproved  = $karyawan->cutiKaryawan()->where('status', 'approved')->count();
        $totalRejected  = $karyawan->cutiKaryawan()->where('status', 'rejected')->count();

        return $this->successResponse([
            'profil'    => new KaryawanResource($karyawan),
            'statistik' => [
                'total_pengajuan' => $totalPengajuan,
                'total_pending'   => $totalPending,
                'total_approved'  => $totalApproved,
                'total_rejected'  => $totalRejected,
                'sisa_cuti'       => $karyawan->sisa_cuti,
                'jatah_tahunan'   => 12,
                'cuti_terpakai'   => 12 - $karyawan->sisa_cuti,
            ],
        ]);
    }
}
