<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\HasApiTokens;

class Karyawan extends Authenticatable
{
    use HasApiTokens, HasFactory;

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

    // ── RELATIONSHIPS ─────────────────────────────────────────────
    
    /**
     * Semua pengajuan cuti milik karyawan ini
     */
    public function cutiKaryawan(): HasMany
    {
        return $this->hasMany(CutiKaryawan::class);
    }

    /**
     * Semua pengajuan yang diproses (approve/reject) oleh karyawan ini
     * Hanya relevan jika role = admin
     */
    public function cutiYangDiproses(): HasMany
    {
        return $this->hasMany(CutiKaryawan::class, 'approved_by');
    }

    /**
     * Hanya pengajuan dengan status pending
     */
    public function cutiPending(): HasMany
    {
        return $this->hasMany(CutiKaryawan::class)
                    ->where('status', 'pending');
    }

    /**
     * Hanya pengajuan dengan status approved
     */
    public function cutiApproved(): HasMany
    {
        return $this->hasMany(CutiKaryawan::class)
                    ->where('status', 'approved');
    }

    // ── SCOPES ─────────────────────────────────────────────
    
    /**
     * Filter berdasarkan departemen
     * Usage: Karyawan::departemen('Sewing')->get()
     */
    public function scopeDepartemen(Builder $query, string $dept): Builder
    {
        return $query->where('departemen', $dept);
    }

    /**
     * Hanya karyawan dengan role admin
     * Usage: Karyawan::admin()->get()
     */
    public function scopeAdmin(Builder $query): Builder
    {
        return $query->where('role', 'admin');
    }

    /**
     * Hanya karyawan biasa (bukan admin)
     * Usage: Karyawan::bukanAdmin()->get()
     */
    public function scopeBukanAdmin(Builder $query): Builder
    {
        return $query->where('role','karyawan');
    }

    /**
     * Karyawan yang masih punya sisa cuti
     * Usage: Karyawan::masihAdaCuti()->get()
     */
    public function scopeMasihAdaCuti(Builder $query): Builder
    {
        return $query->where('sisa_cuti', '>', 0);
    }

    // ── ACCESSORS ─────────────────────────────────────────────
    
    /**
     * Label departemen - bisa diperluas jika nama berubah
     * Usage: $karyawan->label_departemen
     */
    public function getLabelDepartemenAttribute(): string
    {
        return match($this->departemen) {
            'Sewing'    => 'Dept. Sewing',
            'Cuting'    => 'Dept. Cutting',
            'Finishing' => 'Dept. Finishing',
            'QA'        => 'Dept. Quality Assurance',
            null        => 'Non-Produksi',      // contoh: admin, HR, dll    
            default     => $this->departemen,   // fallback: tampilkan apa adanya
        };
    }

    /**
     * Apakah user ini karyawan produksi?
     * Karyawan produksi = punya departemen
     */
    public function isKaryawanProduksi(): bool
    {
        return $this->departemen !== null;
    }

    /**
     * Sisa cuti dalam format "X hari"
     * Usage: $karyawan->sisa_cuti_label
     */
    public function getSisaCutiLabelAttribute(): string
    {
        if ($this->sisa_cuti === 0) return 'Jatah cuti habis';
        return $this->sisa_cuti . ' hari tersisa';
    }

    // ── HELPER METHODS ─────────────────────────────────────────────
    
    public function getIsAdminAttribute(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Admin bisa approve/reject cuti siapa saja
     * Tapi jika admin juga karyawan produksi,
     * dia tetap bisa ajukan cuti sendiri.
     */
    public function bisaAjukanCuti(int $jumlahHari): bool
    {
        // Admin non-produksi tidak punya jatah cuti
        if (! $this->isKaryawanProduksi()) return false;

        return $this->sisa_cuti >= $jumlahHari;
    }
}