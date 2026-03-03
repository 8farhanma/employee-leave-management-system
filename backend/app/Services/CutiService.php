<?php

namespace App\Services;

use App\Models\CutiKaryawan;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class CutiService
{
    // ──────────────────────────────────────────────────────────────
    // HELPER: Hitung jumlah hari (inklusif)
    // Contoh: 10 Mar – 12 Mar = 3 hari
    // ──────────────────────────────────────────────────────────────

    public function hitungHari(string $tanggalMulai, string $tanggalSelesai): int
    {
        return Carbon::parse($tanggalMulai)
                     ->diffInDays(Carbon::parse($tanggalSelesai)) + 1;
    }

    // ──────────────────────────────────────────────────────────────
    // PENGAJUAN CUTI (oleh karyawan)
    // ──────────────────────────────────────────────────────────────

    /**
     * Buat pengajuan cuti baru.
     *
     * Aturan bisnis yang dicek di sini:
     * - Karyawan harus karyawan produksi (punya departemen)
     * - Jika jenis cuti potong_jatah = true, sisa cuti harus cukup
     * - Tidak ada pengajuan pending yang tumpang-tindih tanggalnya
     *
     * @throws \Exception
     */
    public function ajukan(Karyawan $karyawan, array $data): CutiKaryawan
    {
        // Guard 1: hanya karyawan produksi yang bisa ajukan cuti
        if (! $karyawan->isKaryawanProduksi()) {
            throw new \Exception(
                'Akun ini bukan karyawan produksi dan tidak dapat mengajukan cuti.'
            );
        }

        $jenisCuti  = JenisCuti::findOrFail($data['jenis_cuti_id']);
        $jumlahHari = $this->hitungHari(
            $data['tanggal_mulai'],
            $data['tanggal_selesai']
        );

        // Guard 2: cek sisa cuti jika jenis cuti memotong jatah
        if ($jenisCuti->potong_jatah && ! $karyawan->bisaAjukanCuti($jumlahHari)) {
            throw new \Exception(
                "Sisa cuti tidak mencukupi. " .
                "Anda memiliki {$karyawan->sisa_cuti} hari, " .
                "pengajuan membutuhkan {$jumlahHari} hari."
            );
        }

        // Guard 3: cek tumpang-tindih dengan pengajuan yang sudah ada
        // (pending atau approved — bukan rejected)
        $tumpangTindih = CutiKaryawan::where('karyawan_id', $karyawan->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($query) use ($data) {
                // Overlap terjadi jika:
                // tanggal_mulai_baru <= tanggal_selesai_lama
                // DAN tanggal_selesai_baru >= tanggal_mulai_lama
                $query->where('tanggal_mulai', '<=', $data['tanggal_selesai'])
                      ->where('tanggal_selesai', '>=', $data['tanggal_mulai']);
            })
            ->exists();

        if ($tumpangTindih) {
            throw new \Exception(
                'Tanggal yang dipilih bertabrakan dengan pengajuan cuti Anda yang sudah ada.'
            );
        }

        // Semua guard lolos — simpan pengajuan
        return CutiKaryawan::create([
            'karyawan_id'     => $karyawan->id,
            'jenis_cuti_id'   => $jenisCuti->id,
            'tanggal_mulai'   => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'jumlah_hari'     => $jumlahHari,
            'keterangan'      => $data['keterangan'] ?? null,
            'status'          => 'pending',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // APPROVE CUTI (oleh admin)
    // ──────────────────────────────────────────────────────────────

    /**
     * Admin menyetujui pengajuan cuti.
     *
     * Jika jenis cuti potong_jatah = true, sisa cuti karyawan dikurangi.
     * Seluruh operasi dibungkus DB Transaction untuk atomicity.
     *
     * @throws \Exception
     */
    public function approve(
        CutiKaryawan $cuti,
        Karyawan $admin,
        ?string $catatan = null
    ): CutiKaryawan {
        // Guard: hanya pengajuan pending yang bisa di-approve
        if (! $cuti->isPending()) {
            throw new \Exception(
                "Pengajuan ini sudah berstatus '{$cuti->status}' dan tidak dapat diproses ulang."
            );
        }

        DB::transaction(function () use ($cuti, $admin, $catatan) {

            // Update status pengajuan
            $cuti->update([
                'status'        => 'approved',
                'catatan_admin' => $catatan,
                'approved_by'   => $admin->id,
                'approved_at'   => now(),
            ]);

            // Kurangi sisa cuti hanya jika jenis cuti memotong jatah
            if ($cuti->jenisCuti->potong_jatah) {

                // Double-check sisa cuti masih cukup saat approve
                // (bisa saja berubah antara waktu pengajuan dan approve)
                $karyawan = $cuti->karyawan()->lockForUpdate()->first();

                if ($karyawan->sisa_cuti < $cuti->jumlah_hari) {
                    throw new \Exception(
                        "Sisa cuti karyawan tidak lagi mencukupi saat approve. " .
                        "Sisa: {$karyawan->sisa_cuti} hari, " .
                        "dibutuhkan: {$cuti->jumlah_hari} hari."
                    );
                }

                $karyawan->decrement('sisa_cuti', $cuti->jumlah_hari);
            }
        });

        // Refresh model agar data terbaru (setelah decrement)
        return $cuti->fresh(['karyawan', 'jenisCuti', 'approvedBy']);
    }

    // ──────────────────────────────────────────────────────────────
    // REJECT CUTI (oleh admin)
    // ──────────────────────────────────────────────────────────────

    /**
     * Admin menolak pengajuan cuti.
     *
     * Sisa cuti tidak berubah karena belum pernah dipotong saat pengajuan.
     * Jika sebelumnya sudah di-approve lalu ingin di-reject
     * (misalnya salah approve), sisa cuti dikembalikan.
     *
     * @throws \Exception
     */
    public function reject(
        CutiKaryawan $cuti,
        Karyawan $admin,
        string $catatan
    ): CutiKaryawan {
        // Guard: tidak bisa reject yang sudah rejected
        if ($cuti->isRejected()) {
            throw new \Exception(
                'Pengajuan ini sudah ditolak sebelumnya.'
            );
        }

        DB::transaction(function () use ($cuti, $admin, $catatan) {

            $sudahApproved = $cuti->isApproved();

            // Update status pengajuan
            $cuti->update([
                'status'        => 'rejected',
                'catatan_admin' => $catatan,
                'approved_by'   => $admin->id,
                'approved_at'   => now(),
            ]);

            // Jika sebelumnya sudah approved dan jenis cuti potong jatah,
            // kembalikan sisa cuti karyawan
            if ($sudahApproved && $cuti->jenisCuti->potong_jatah) {
                $cuti->karyawan()->lockForUpdate()->first();

                $cuti->karyawan->increment('sisa_cuti', $cuti->jumlah_hari);

                // Pastikan sisa cuti tidak melebihi batas maksimal 12
                if ($cuti->karyawan->sisa_cuti > 12) {
                    $cuti->karyawan->update(['sisa_cuti' => 12]);
                }
            }
        });

        return $cuti->fresh(['karyawan', 'jenisCuti', 'approvedBy']);
    }

    // ──────────────────────────────────────────────────────────────
    // BATALKAN CUTI (oleh karyawan sendiri)
    // ──────────────────────────────────────────────────────────────

    /**
     * Karyawan membatalkan pengajuannya sendiri.
     * Hanya boleh jika masih berstatus pending.
     *
     * @throws \Exception
     */
    public function batalkan(CutiKaryawan $cuti, Karyawan $karyawan): void
    {
        // Guard 1: hanya pemilik pengajuan yang bisa membatalkan
        if ($cuti->karyawan_id !== $karyawan->id) {
            throw new \Exception(
                'Anda tidak memiliki izin untuk membatalkan pengajuan ini.'
            );
        }

        // Guard 2: hanya bisa batalkan jika masih pending
        if (! $cuti->isPending()) {
            throw new \Exception(
                "Pengajuan yang sudah berstatus '{$cuti->status}' tidak dapat dibatalkan."
            );
        }

        $cuti->delete();
    }

    // ──────────────────────────────────────────────────────────────
    // LIST CUTI (untuk karyawan — hanya milik sendiri)
    // ──────────────────────────────────────────────────────────────

    /**
     * Ambil daftar cuti milik karyawan tertentu.
     * Bisa difilter by status dan/atau tahun.
     */
    public function listByKaryawan(
        Karyawan $karyawan,
        ?string $status = null,
        ?int $tahun = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        $query = CutiKaryawan::with(['jenisCuti', 'approvedBy'])
            ->where('karyawan_id', $karyawan->id)
            ->latest('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        if ($tahun) {
            $query->whereYear('tanggal_mulai', $tahun);
        }

        return $query->paginate($perPage);
    }

    // ──────────────────────────────────────────────────────────────
    // LIST CUTI (untuk admin — semua karyawan)
    // ──────────────────────────────────────────────────────────────

    /**
     * Ambil semua pengajuan cuti untuk halaman admin.
     * Bisa difilter by status, departemen, dan/atau tahun.
     */
    public function listSemua(
        ?string $status = null,
        ?string $departemen = null,
        ?int $tahun = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = CutiKaryawan::with(['karyawan', 'jenisCuti', 'approvedBy'])
            ->latest('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        if ($departemen) {
            $query->whereHas('karyawan', function ($q) use ($departemen) {
                $q->where('departemen', $departemen);
            });
        }

        if ($tahun) {
            $query->whereYear('tanggal_mulai', $tahun);
        }

        return $query->paginate($perPage);
    }

    // ──────────────────────────────────────────────────────────────
    // REKAP SISA CUTI (untuk admin — dashboard)
    // ──────────────────────────────────────────────────────────────

    /**
     * Ringkasan statistik cuti per departemen.
     * Dipakai di halaman dashboard admin.
     */
    public function rekapPerDepartemen(): array
    {
        $departemenList = ['Sewing', 'Cutting', 'Finishing', 'QA'];
        $rekap          = [];

        foreach ($departemenList as $dept) {
            $rekap[$dept] = [
                'total_karyawan' => Karyawan::departemen($dept)->bukanAdmin()->count(),
                'pending'        => CutiKaryawan::departemen($dept)->pending()->count(),
                'approved'       => CutiKaryawan::departemen($dept)->approved()
                                        ->whereYear('tanggal_mulai', now()->year)
                                        ->count(),
                'rejected'       => CutiKaryawan::departemen($dept)->rejected()
                                        ->whereYear('tanggal_mulai', now()->year)
                                        ->count(),
            ];
        }

        return $rekap;
    }

    // ──────────────────────────────────────────────────────────────
    // RESET JATAH CUTI TAHUNAN (dijalankan awal tahun)
    // ──────────────────────────────────────────────────────────────

    /**
     * Reset sisa_cuti semua karyawan produksi ke 12 di awal tahun baru.
     * Dipanggil via Artisan command atau scheduler.
     */
    public function resetJatahTahunan(): int
    {
        return Karyawan::whereNotNull('departemen')
                        ->update(['sisa_cuti' => 12]);
    }
}