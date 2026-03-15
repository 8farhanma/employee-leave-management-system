<?php

namespace App\Services;

use App\Constants\LeaveConstants;
use App\Exceptions\LeaveException;
use App\Models\CutiKaryawan;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CutiService
{
    // ──────────────────────────────────────────────────────────────
    // UTILITIES
    // ──────────────────────────────────────────────────────────────

    /**
     * Hitung jumlah hari cuti secara inklusif.
     * Contoh: 10 Mar -> 12 Mar = 3 hari
     * 
     * @param string $mulai     Format: Y-m-d
     * @param string $selesai   Format: Y-m-d
     */
    public function hitungHari(string $mulai, string $selesai): int
    {
        return Carbon::parse($mulai)
                     ->diffInDays(Carbon::parse($selesai)) + 1;
    }

    /**
     * Validasi apakah karyawan bisa mengajukan cuti sejumlah hari.
     * Lempar exception jika tidak bisa.
     * 
     * @throws \Exception
     */
    public function validasiSisaCuti(Karyawan $karyawan, JenisCuti $jenisCuti, int $jumlahHari): void
    {
        if ($jenisCuti->potong_jatah && !$karyawan->bisaSubmitCuti($jumlahHari)) {
            throw new \Exception(
                "Sisa cuti tidak mencukupi. " .
                "Anda membutuhkan {$jumlahHari} hari, " .
                "sisa cuti Anda {$karyawan->sisa_cuti} hari."
            );
        }
    }

    // ──────────────────────────────────────────────────────────────
    // KARYAWAN ACTIONS
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
    public function submit(Karyawan $karyawan, array $data): CutiKaryawan
    {
        // Guard 1: hanya karyawan produksi yang bisa submit cuti
        if (! $karyawan->isKaryawanProduksi()) {
            throw new \Exception(
                'Akun ini bukan karyawan produksi dan tidak dapat mengsubmit cuti.'
            );
        }

        $jenisCuti  = JenisCuti::findOrFail($data['jenis_cuti_id']);
        $jumlahHari = $this->hitungHari(
            $data['tanggal_mulai'],
            $data['tanggal_selesai']
        );

        // Guard 2: cek sisa cuti jika jenis cuti memotong jatah
        if ($jenisCuti->potong_jatah && ! $karyawan->bisasubmitCuti($jumlahHari)) {
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
        $cuti = CutiKaryawan::create([
            'karyawan_id'     => $karyawan->id,
            'jenis_cuti_id'   => $jenisCuti->id,
            'tanggal_mulai'   => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'jumlah_hari'     => $jumlahHari,
            'keterangan'      => $data['keterangan'] ?? null,
            'status'          => 'pending',
        ]);

        // Logging
        Log::info('Cuti diajukan', [
            'karyawan'      => $karyawan->nik,
            'jenis_cuti'    => $data['jenis_cuti_id'],
            'tanggal'       => $data['tanggal_mulai'] . ' s/d ' . $data['tanggal_selesai'],
            'jumlah_hari'   => $jumlahHari,
        ]);

        return $cuti;
    }

    /**
     * Batalkan pengajuan cuti (hanya boleh saat pending)
     *
     * @throws \Exception
     */
    public function cancel(CutiKaryawan $cuti, Karyawan $karyawan): void
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

        $cuti->forceDelete();
    }

    /**
     * List pengajuan cuti milik karyawan tertentu, dengan filter opsional.
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
    // ADMIN ACTIONS
    // ──────────────────────────────────────────────────────────────

    /**
     * Approve pengajuan cuti.
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

        // Logging
        Log::info('Cuti disetujui', [
            'cuti_id'       => $cuti->id,
            'karyawan'      => $cuti->karyawan->nik,
            'admin'         => $admin->nik,
            'jumlah_hari'   => $cuti->jumlah_hari,
            'jenis_cuti'    => $cuti->jenisCuti->nama,
            'timestamp'     => now()->toDateTimeString(),
        ]);

        // Refresh model agar data terbaru (setelah decrement)
        return $cuti->fresh(['karyawan', 'jenisCuti', 'approvedBy']);
    }

    /**
     * Reject pengajuan cuti.
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
            throw LeaveException::sudahDiproses('rejected');
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

        // Logging
        Log::info('Cuti ditolak', [
            'cuti_id'       => $cuti->id,
            'karyawan'      => $cuti->karyawan->nik,
            'admin'         => $admin->nik,
            'catatan'       => $catatan,
            'timestamp'     => now()->toDateTimeString(),
        ]);

        return $cuti->fresh(['karyawan', 'jenisCuti', 'approvedBy']);
    }

    /**
     * Ambil semua pengajuan cuti untuk halaman admin.
     * Bisa difilter by status, departemen, dan/atau tahun.
     */
    public function listForAdmin(
        ?string $status = null,
        ?string $departemen = null,
        ?int $tahun = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = CutiKaryawan::with(['karyawan', 'jenisCuti', 'approvedBy'])
            ->latest('cuti_karyawan.created_at');

        if ($status) {
            $query->status($status);
        }

        if ($departemen) {
            $query->whereRelation('karyawan', 'departemen', $departemen);
        }

        if ($tahun) {
            $query->whereYear('cuti_karyawan.tanggal_mulai', $tahun);
        }

        return $query->paginate($perPage);
    }

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

    /**
     * Reset sisa_cuti semua karyawan produksi ke 12 di awal tahun baru.
     * Dipanggil via Artisan command atau scheduler.
     */
    public function resetJatahTahunan(): int
    {
        return Karyawan::where('role', LeaveConstants::ROLE_KARYAWAN)
                        ->update(['sisa_cuti' => LeaveConstants::ANNUAL_QUOTA]);
    }
}