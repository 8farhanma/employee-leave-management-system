<?php

namespace App\Services;

use App\Constants\LeaveConstants;
use App\Exceptions\LeaveException;
use App\Models\CutiKaryawan;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CutiService
{
    // ──────────────────────────────────────────────────────────────
    // BAGIAN 1 - CARBON UTILITIES
    // ──────────────────────────────────────────────────────────────

    /**
     * Hitung jumlah hari cuti secara inklusif.
     * 
     * Contoh: 10 Mar -> 10 Mar = 1 hari
     * Contoh: 10 Mar -> 12 Mar = 3 hari
     * Contoh: 28 Mar -> 03 Apr = 7 hari
     * 
     * Rumus: diffInDays(start, end) + 1
     */
    public function hitungHari(string $mulai, string $selesai): int
    {
        return Carbon::parse($mulai)
                     ->startOfDay()
                     ->diffInDays(Carbon::parse($selesai)->startOfDay()) + 1;
    }

    /**
     * Breakdown detail periode cuti menggunakan CarbonPeriod.
     * 
     * Berguna untuk debugging atau menampilkan kalender cuti.
     * Return array berisi setiap tanggal dalam periode.
     * 
     * @return array<string> Format: ['2026-04-07', '2026-04-08', ...]
     */
    public function breakdownHari(string $mulai, string $selesai): array
    {
        $period = CarbonPeriod::create(
            Carbon::parse($mulai)->startOfDay(),
            Carbon::parse($selesai)->startOfDay()
        );

        return collect($period)
            ->map(fn(Carbon $date) => $date->format('Y-m-d'))
            ->toArray();
    }

    /**
     * Hitung berapa hari tersisa hingga tanggal mulai cuti.
     * Berguna untuk fitur notifikasi / reminder.
     * 
     * @return int Negatif jika tanggal sudah lewat
     */
    public function hariMenujuCuti(string $tanggalMulai): int
    {
        $tz = 'Asia/Jakarta';

        return (int) now('Asia/Jakarta')
            ->startOfDay()
            ->diffInDays(
                // Paksa parse dengan timezone yang sama
                Carbon::parse($tanggalMulai, $tz)->startOfDay(),
                false   // false = signed (bisa negatif)
            );
    }

    // ────────────────────────────────────────────────────────────────
    // BAGIAN 2 - OVERLAP DETECTION
    // ────────────────────────────────────────────────────────────────
    
    /**
     * Cek apakah ada pengajuan cuti yang tumpang tindih dengan range baru.
     * 
     * Dua range dikatakan overlap jika:
     * start_A <= end_B AND end_A >= start_B
     * 
     * kondisi yang dicek:
     * - Status pending ATAU approved
     * - Milik karyawan yang sama
     * - Tanggal bertabrakan
     */
    public function cekOverlap(
        Karyawan $karyawan,
        string $tanggalMulai,
        string $tanggalSelesai,
        ?int $excludeId = null      // untuk update: exclude ID pengajuan ini sendiri
    ): bool {
        $query = CutiKaryawan::where('karyawan_id', $karyawan->id)
            ->whereIn('status', [
                LeaveConstants::STATUS_PENDING,
                LeaveConstants::STATUS_APPROVED,
            ])
            // Kondisi overlap: start_A <= end_B AND end_A >= start_B
            ->where('tanggal_mulai', '<=', $tanggalSelesai)
            ->where('tanggal_selesai', '>=', $tanggalMulai);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Ambil detail pengajuan yang overlap (untuk pesan error yang informatif).
     * 
     * @return CutiKaryawan|null
     */
    public function getOverlapDetail(
        Karyawan $karyawan,
        string $tanggalMulai,
        string $tanggalSelesai,
        ?int $excludeId = null
    ): ?CutiKaryawan {
        $query = CutiKaryawan::with(['jenisCuti'])
            ->where('karyawan_id', $karyawan->id)
            ->whereIn('status', [
                LeaveConstants::STATUS_PENDING,
                LeaveConstants::STATUS_APPROVED,
            ])
            ->where('tanggal_mulai', '<=', $tanggalSelesai)
            ->where('tanggal_selesai', '>=', $tanggalMulai);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }

    // ────────────────────────────────────────────────────────────────
    // BAGIAN 3 - VALIDASI BISNIS
    // ────────────────────────────────────────────────────────────────

    /**
     * Validasi sisa cuti mencukupi.
     * 
     * @throws \Exception
     */
    public function validasiSisaCuti(
        Karyawan $karyawan, 
        JenisCuti $jenisCuti, 
        int $jumlahHari
    ): void {
        if ($jenisCuti->potong_jatah && $karyawan->sisa_cuti < $jumlahHari) {
            throw new \Exception(
                "Sisa cuti tidak mencukupi. " .
                "Anda membutuhkan {$jumlahHari} hari, " .
                "sisa cuti Anda {$karyawan->sisa_cuti} hari."
            );
        }
    }

    /**
     * Validasi tidak ada overlap dengan pengajuan aktif lainnya.
     * 
     * @throws \Exception
     */
    public function validasiTidakOverlap(
        Karyawan $karyawan,
        string $tanggalMulai,
        string $tanggalSelesai,
        ?int $excludeId = null,
    ): void {
        $overlap = $this->getOverlapDetail(
            $karyawan,
            $tanggalMulai,
            $tanggalSelesai,
            $excludeId
        );

        if ($overlap) {
            throw new \Exception(
                "Tanggal cuti bertabrakan dengan pengajuan yang sudah ada. " .
                "Pengajuan aktif: {$overlap->periode_label} " .
                "(Status: {$overlap->status})."
            );
        }
    }

    // ────────────────────────────────────────────────────────────────
    // BAGIAN 4 - FILE UPLOAD (change request #001)
    // ────────────────────────────────────────────────────────────────
    
    /**
     * Simpan file dokumen pendukung ke storage.
     * 
     * Nama file    : {karyawan_id}_{timestamp}_{random}.{ext}
     * Lokasi       : storage/app/public/dokumen-cuti/
     * 
     * @throws \Exception jika upload gagal
     */
    public function simpanDokumen(UploadedFile $file, int $karyawanId): string
    {
        $ekstensi = $file->getClientOriginalExtension();
        $namaFile = implode('_', [
            $karyawanId,
            now()->format('Ymd_His'),
            Str::random(8),
        ]) . '.' . strtolower($ekstensi);

        $path = $file->storeAs(
            LeaveConstants::DOKUMEN_STORAGE_FOLDER,
            $namaFile,
            'public'
        );

        if (!$path) {
            throw new \Exception('Gagal menyimpan dokumen. Silakan coba lagi.');
        }

        return $path;
    }

    /**
     * Hapus file dokumen dari storage.
     * Tidak throw jika file tidak ada (sudah terhapus sebelumnya)
     */
    public function hapusDokumen(?string $dokumenPath): void
    {
        if ($dokumenPath &&  Storage::disk('public')->exists($dokumenPath)) {
            Storage::disk('public')->delete($dokumenPath);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // BAGIAN 5 - KARYAWAN ACTIONS
    // ──────────────────────────────────────────────────────────────

    /**
     * Buat pengajuan cuti baru.
     *
     * Flow:
     * 1. Ambil jenis cuti dari DB
     * 2. Hitung jumlah hari (inklusif)
     * 3. Validasi sisa cuti
     * 4. Validasi tidak overlap
     * 5. Handle upload dokumen jika ada
     * 6. Simpan ke DB
     *
     * @throws \Exception
     */
    public function submit(
        Karyawan $karyawan,
        array $data,
        ?UploadedFile $dokumen = null
    ): CutiKaryawan {

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

        // Validasi sisa cuti
        $this->validasiSisaCuti($karyawan, $jenisCuti, $jumlahHari);

        // Validasi overlap
        $this->validasiTidakOverlap(
            $karyawan,
            $data['tanggal_mulai'],
            $data['tanggal_selesai']
        );

        // Upload dokumen jika ada
        $dokumenPath = null;
        if ($dokumen) {
            $dokumenPath = $this->simpanDokumen($dokumen, $karyawan->id);
        }

        $cuti = CutiKaryawan::create([
            'karyawan_id'     => $karyawan->id,
            'jenis_cuti_id'   => $jenisCuti->id,
            'tanggal_mulai'   => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'jumlah_hari'     => $jumlahHari,
            'keterangan'      => $data['keterangan'] ?? null,
            'dokumen_path'    => $dokumenPath,
            'status'          => LeaveConstants::STATUS_PENDING,
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
     * Batalkan pengajuan cuti (hanya boleh saat pending).
     * Hapus dokumen dari storage jika ada.
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

        // Hapus dokumen dari storage sebelum delete record
        $this->hapusDokumen($cuti->dokumen_path);

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
            ->orderBy('created_at', 'desc');

        if ($status && in_array($status, LeaveConstants::ALL_STATUSES)) {
            $query->status($status);
        }

        if ($tahun) {
            $query->whereYear('tanggal_mulai', $tahun);
        }

        return $query->paginate($perPage);
    }


    // ──────────────────────────────────────────────────────────────
    // BAGIAN 6 - ADMIN ACTIONS
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
                "Hanya pengajuan berstatus pending yang dapat disetujui. " .
                "Status saat ini: {$cuti->status}."
            );
        }

        DB::transaction(function () use ($cuti, $admin, $catatan) {

            // Update status pengajuan
            $cuti->update([
                'status'        => LeaveConstants::STATUS_APPROVED,
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

        $wasApproved = $cuti->isApproved();

        DB::transaction(function () use ($cuti, $admin, $catatan, $wasApproved) {
            // Update status pengajuan
            $cuti->update([
                'status'        => LeaveConstants::STATUS_REJECTED,
                'catatan_admin' => $catatan,
                'approved_by'   => $admin->id,
                'approved_at'   => now(),
            ]);

            // Jika sebelumnya sudah approved dan jenis cuti potong jatah,
            // kembalikan sisa cuti karyawan
            if ($wasApproved && $cuti->jenisCuti->potong_jatah) {
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
        ?string $dari = null,
        ?string $sampai = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = CutiKaryawan::with(['karyawan', 'jenisCuti', 'approvedBy'])
            ->orderBy('created_at', 'desc');

        if ($status && in_array($status, LeaveConstants::ALL_STATUSES)) {
            $query->status($status);
        }

        if ($departemen && in_array($departemen, LeaveConstants::DEPARTMENTS)) {
            $query->departemen($departemen);
        }

        if ($dari && $sampai) {
            $query->dalamPeriode($dari, $sampai);
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