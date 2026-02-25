<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Karyawan extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'karyawan';

    protected $fillable = [
        'nik',
        'nama',
        'departemen',
        'role',
        'email',
        'password',
        'sisa_cuti',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password'   => 'hashed',
        'sisa_cuti'  => 'integer',
    ];

    // Semua pengajuan cuti milik karyawan ini
    public function cutiKaryawan(): HasMany
    {
        return $this->hasMany(CutiKaryawan::class);
    }

    // Semua pengajuan yang disetujui/ditolak oleh admin ini
    public function cutiYangDiproses(): HasMany
    {
        return $this->hasMany(CutiKaryawan::class, 'approved_by');
    }

    // Helper: apakah karyawan ini admin?
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}