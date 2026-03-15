<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class JenisCuti extends Model
{
    use HasFactory;

    protected $table = 'jenis_cuti';

    protected $fillable = [
        'nama',
        'potong_jatah',
        'keterangan',
    ];

    protected $cast = [
        'potong_jatah' => 'boolean',
    ];

    // ── RELATIONSHIPS ─────────────────────────────────────────────
    
    public function cutiKaryawan(): HasMany
    {
        return $this->hasMany(CutiKaryawan::class);
    }

    // ── SCOPES ───────────────────────────────────────────────────

    /**
     * Hanya jenis cuti yang memotong jatah tahunan
     * Usage: JenisCuti::potongJatah()->get();
     */
    public function scopePotongJatah(Builder $query): Builder
    {
        return $query->where('potong_jatah', true);
    }

    /**
     * Hanya jenis cuti yang tidak memotong jatah tahunan
     * Usage: JenisCuti::tidakPotongJatah()->get();
     */
    public function scopeTidakPotongJatah(Builder $query): Builder
    {
        return $query->where('potong_jatah', false);
    }
}