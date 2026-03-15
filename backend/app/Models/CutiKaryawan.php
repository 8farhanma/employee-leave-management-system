<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CutiKaryawan extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'cuti_karyawan';

    protected $fillable = [
        'karyawan_id',
        'jenis_cuti_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'jumlah_hari',
        'keterangan',
        'status',
        'catatan_admin',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
        'approved_at'     => 'datetime',
        'jumlah_hari'     => 'integer',
    ];

    // ── RELATIONSHIPS ─────────────────────────────────────────────
    
    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function jenisCuti(): BelongsTo
    {
        return $this->belongsTo(JenisCuti::class);
    }

    /**
     * Admin yang memproses pengajuan cuti (approve/reject)
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'approved_by');
    }

    // ── SCOPES ─────────────────────────────────────────────
    
    /**
     * Filter by status
     * Usage: CutiKaryawan::status('pending')->get()
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Hanya yang pending
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Hanya yang approved
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Hanya yang rejected
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Filter pengajuan dalam rentang tanggal tertentu
     * Usage: CutiKaryawan::dalamPeriode('2026-01-01', '2026-12-31')->get()
     */
    public function scopeDalamPeriode(
        Builder $query, 
        string $dari, 
        string $sampai
        ): Builder {
            return $query->whereBetween('tanggal_mulai', [$dari, $sampai]);
        }

    /**
     * Filter berdasarkan departemen karyawan (via join)
     * Usage: CutiKaryawan::departemen('Sewing')->get()
     */
    public function scopeDepartemen(Builder $query, string $dept): Builder
    {
        return $query->whereRelation('karyawan', 'departemen', $dept);
    }

    // ── ACCESSORS ─────────────────────────────────────────────
    
    /**
     * Label status dengan emoji untuk readablity
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => '⏳ Menunggu Persetujuan',
            'approved' => '✅ Disetujui',
            'rejected' => '❌ Ditolak',
            default => ucfirst($this->status),
        };
    }

    /**
     * Format tanggal range cuti yang human-readable
     * Usage: $cuti->periode_label
     * Output: "10 Mar 2026 - 12 Mar 2026 (3 hari)"
     */
    public function getPeriodeLabelAttribute(): string
    {
        $mulai   = $this->tanggal_mulai->isoformat('D MMM YYYY') ?? '-';
        $selesai = $this->tanggal_selesai->isoformat('D MMM YYYY') ?? '-';

        return "{$mulai} - {$selesai} ({$this->jumlah_hari} hari)";
    }
    
        // ── HELPER METHODS ────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Apakah cuti ini memotong jatah tahunan?
     * Bergantung pada jenis cuti yang dipilih
     */
    public function isPotongJatah(): bool
    {
        return $this->jenisCuti->potong_jatah;
    }
}