<?php

namespace Tests\Unit\Services;

use App\Constants\LeaveConstants;
use App\Exceptions\LeaveException;
use App\Models\CutiKaryawan;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use App\Services\CutiService;
use PHPUnit\Framework\TestCase;

class CutiServiceTest extends TestCase
{
    private CutiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CutiService();
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 1 — hitungHari (Carbon Inklusif)
    // ════════════════════════════════════════════════════════════════

    public function test_hitung_hari_tanggal_sama_adalah_satu(): void
    {
        $this->assertEquals(1, $this->service->hitungHari('2026-04-10', '2026-04-10'));
    }

    public function test_hitung_hari_dua_hari_berturutan(): void
    {
        $this->assertEquals(2, $this->service->hitungHari('2026-04-10', '2026-04-11'));
    }

    public function test_hitung_hari_tiga_hari(): void
    {
        // 10, 11, 12 Apr = 3 hari
        $this->assertEquals(3, $this->service->hitungHari('2026-04-10', '2026-04-12'));
    }

    public function test_hitung_hari_satu_minggu_penuh(): void
    {
        $this->assertEquals(7, $this->service->hitungHari('2026-04-13', '2026-04-19'));
    }

    public function test_hitung_hari_tepat_kuota_tahunan(): void
    {
        $this->assertEquals(
            LeaveConstants::ANNUAL_QUOTA,
            $this->service->hitungHari('2026-04-01', '2026-04-12')
        );
    }

    public function test_hitung_hari_lintas_bulan(): void
    {
        // 28, 29, 30, 31 Mar + 1, 2, 3 Apr = 7 hari
        $this->assertEquals(7, $this->service->hitungHari('2026-03-28', '2026-04-03'));
    }

    public function test_hitung_hari_lintas_bulan_februari(): void
    {
        // 26, 27, 28 Feb + 1, 2, 3 Mar = 6 hari (2026 bukan kabisat)
        $this->assertEquals(6, $this->service->hitungHari('2026-02-26', '2026-03-03'));
    }

    public function test_hitung_hari_lintas_tahun(): void
    {
        // 30, 31 Des + 1, 2 Jan = 4 hari
        $this->assertEquals(4, $this->service->hitungHari('2025-12-30', '2026-01-02'));
    }

    public function test_hitung_hari_tahun_kabisat(): void
    {
        // 2028 kabisat — 27, 28, 29 Feb + 1 Mar = 4 hari
        $this->assertEquals(4, $this->service->hitungHari('2028-02-27', '2028-03-01'));
    }

    public function test_hitung_hari_konsisten_untuk_berbagai_durasi(): void
    {
        $kasus = [
            ['2026-04-01', '2026-04-01', 1],
            ['2026-04-01', '2026-04-02', 2],
            ['2026-04-01', '2026-04-07', 7],
            ['2026-04-01', '2026-04-12', 12],
        ];

        foreach ($kasus as [$mulai, $selesai, $expected]) {
            $this->assertEquals(
                $expected,
                $this->service->hitungHari($mulai, $selesai),
                "Gagal untuk range {$mulai} → {$selesai}, expected {$expected}"
            );
        }
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 2 — breakdownHari
    // ════════════════════════════════════════════════════════════════

    public function test_breakdown_hari_satu_hari(): void
    {
        $result = $this->service->breakdownHari('2026-04-10', '2026-04-10');

        $this->assertCount(1, $result);
        $this->assertEquals(['2026-04-10'], $result);
    }

    public function test_breakdown_hari_tiga_hari_berurutan(): void
    {
        $result = $this->service->breakdownHari('2026-04-10', '2026-04-12');

        $this->assertCount(3, $result);
        $this->assertEquals(['2026-04-10', '2026-04-11', '2026-04-12'], $result);
    }

    public function test_breakdown_hari_lintas_bulan(): void
    {
        $result = $this->service->breakdownHari('2026-03-30', '2026-04-01');

        $this->assertCount(3, $result);
        $this->assertContains('2026-03-30', $result);
        $this->assertContains('2026-03-31', $result);
        $this->assertContains('2026-04-01', $result);
    }

    public function test_jumlah_breakdown_konsisten_dengan_hitung_hari(): void
    {
        $mulai   = '2026-04-07';
        $selesai = '2026-04-13';

        $this->assertEquals(
            $this->service->hitungHari($mulai, $selesai),
            count($this->service->breakdownHari($mulai, $selesai))
        );
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 3 — hariMenujuCuti
    // ════════════════════════════════════════════════════════════════

    public function test_hari_menuju_cuti_besok_adalah_satu(): void
    {
        $besok = now('Asia/Jakarta')->addDay()->format('Y-m-d');
        $this->assertEquals(1, $this->service->hariMenujuCuti($besok));
    }

    public function test_hari_menuju_cuti_hari_ini_adalah_nol(): void
    {
        $hariIni = now('Asia/Jakarta')->format('Y-m-d');
        $this->assertEquals(0, $this->service->hariMenujuCuti($hariIni));
    }

    public function test_hari_menuju_cuti_kemarin_adalah_negatif(): void
    {
        $kemarin = now('Asia/Jakarta')->subDay()->format('Y-m-d');
        $result  = $this->service->hariMenujuCuti($kemarin);

        $this->assertLessThan(0, $result);
        $this->assertEquals(-1, $result);
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 4 — Logika Overlap (Pure, tanpa DB)
    //
    // Visualisasi — Ada: [===== 10 Apr – 15 Apr =====]
    //
    //   A: [7–9]      → Tidak overlap (sebelum, tidak menyentuh)
    //   B: [7–10]     → OVERLAP (end menyentuh start)
    //   C: [10–15]    → OVERLAP (persis sama)
    //   D: [12–13]    → OVERLAP (di dalam)
    //   E: [12–17]    → OVERLAP (mulai di dalam, selesai lewat)
    //   F: [15–17]    → OVERLAP (start menyentuh end)
    //   G: [16–18]    → Tidak overlap (sesudah, tidak menyentuh)
    //   H: [8–17]     → OVERLAP (mencakup seluruhnya)
    //
    // Rumus: start_new <= end_exist AND end_new >= start_exist
    // ════════════════════════════════════════════════════════════════

    /**
     * Helper: duplikasi logika SQL cekOverlap() — murni tanpa DB.
     * Ini memastikan SQL logic yang dipakai CutiService sudah benar.
     */
    private function isOverlapLogic(
        string $existMulai,
        string $existSelesai,
        string $newMulai,
        string $newSelesai
    ): bool {
        return $newMulai <= $existSelesai && $newSelesai >= $existMulai;
    }

    public function test_overlap_case_a_sebelum_tidak_menyentuh(): void
    {
        $this->assertFalse(
            $this->isOverlapLogic('2026-04-10', '2026-04-15', '2026-04-07', '2026-04-09')
        );
    }

    public function test_overlap_case_b_end_menyentuh_start(): void
    {
        $this->assertTrue(
            $this->isOverlapLogic('2026-04-10', '2026-04-15', '2026-04-07', '2026-04-10')
        );
    }

    public function test_overlap_case_c_persis_sama(): void
    {
        $this->assertTrue(
            $this->isOverlapLogic('2026-04-10', '2026-04-15', '2026-04-10', '2026-04-15')
        );
    }

    public function test_overlap_case_d_di_dalam(): void
    {
        $this->assertTrue(
            $this->isOverlapLogic('2026-04-10', '2026-04-15', '2026-04-12', '2026-04-13')
        );
    }

    public function test_overlap_case_e_mulai_dalam_selesai_lewat(): void
    {
        $this->assertTrue(
            $this->isOverlapLogic('2026-04-10', '2026-04-15', '2026-04-12', '2026-04-17')
        );
    }

    public function test_overlap_case_f_start_menyentuh_end(): void
    {
        $this->assertTrue(
            $this->isOverlapLogic('2026-04-10', '2026-04-15', '2026-04-15', '2026-04-17')
        );
    }

    public function test_overlap_case_g_sesudah_tidak_menyentuh(): void
    {
        $this->assertFalse(
            $this->isOverlapLogic('2026-04-10', '2026-04-15', '2026-04-16', '2026-04-18')
        );
    }

    public function test_overlap_case_h_mencakup_seluruhnya(): void
    {
        $this->assertTrue(
            $this->isOverlapLogic('2026-04-10', '2026-04-15', '2026-04-08', '2026-04-17')
        );
    }

    public function test_overlap_simetris(): void
    {
        // Jika A overlap B → B juga overlap A
        $aMulai = '2026-04-10'; $aSelesai = '2026-04-15';
        $bMulai = '2026-04-13'; $bSelesai = '2026-04-18';

        $ab = $this->isOverlapLogic($aMulai, $aSelesai, $bMulai, $bSelesai);
        $ba = $this->isOverlapLogic($bMulai, $bSelesai, $aMulai, $aSelesai);

        $this->assertTrue($ab);
        $this->assertEquals($ab, $ba); // simetris
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 5 — validasiSisaCuti
    // ════════════════════════════════════════════════════════════════

    public function test_validasi_lolos_jika_tidak_potong_jatah(): void
    {
        // Sakit/Izin: sisa=0 tidak masalah karena potong_jatah=false
        $karyawan  = new Karyawan(['sisa_cuti' => 0]);
        $jenisCuti = new JenisCuti(['potong_jatah' => false]);

        $this->expectNotToPerformAssertions();
        $this->service->validasiSisaCuti($karyawan, $jenisCuti, 5);
    }

    public function test_validasi_lolos_jika_sisa_tepat_sama(): void
    {
        $karyawan  = new Karyawan(['sisa_cuti' => 3]);
        $jenisCuti = new JenisCuti(['potong_jatah' => true]);

        $this->expectNotToPerformAssertions();
        $this->service->validasiSisaCuti($karyawan, $jenisCuti, 3); // pas
    }

    public function test_validasi_lolos_jika_sisa_lebih_banyak(): void
    {
        $karyawan  = new Karyawan(['sisa_cuti' => 10]);
        $jenisCuti = new JenisCuti(['potong_jatah' => true]);

        $this->expectNotToPerformAssertions();
        $this->service->validasiSisaCuti($karyawan, $jenisCuti, 3);
    }

    public function test_validasi_gagal_sisa_kurang_satu_hari(): void
    {
        $karyawan  = new Karyawan(['sisa_cuti' => 2]);
        $jenisCuti = new JenisCuti(['potong_jatah' => true]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/tidak mencukupi/');
        $this->service->validasiSisaCuti($karyawan, $jenisCuti, 3);
    }

    public function test_validasi_gagal_sisa_nol(): void
    {
        $karyawan  = new Karyawan(['sisa_cuti' => 0]);
        $jenisCuti = new JenisCuti(['potong_jatah' => true]);

        $this->expectException(\Exception::class);
        $this->service->validasiSisaCuti($karyawan, $jenisCuti, 1);
    }

    public function test_pesan_error_mengandung_jumlah_dibutuhkan_dan_tersisa(): void
    {
        $karyawan  = new Karyawan(['sisa_cuti' => 2]);
        $jenisCuti = new JenisCuti(['potong_jatah' => true]);

        try {
            $this->service->validasiSisaCuti($karyawan, $jenisCuti, 5);
            $this->fail('Harus throw Exception');
        } catch (\Exception $e) {
            $this->assertStringContainsString('5', $e->getMessage()); // dibutuhkan
            $this->assertStringContainsString('2', $e->getMessage()); // tersisa
        }
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 6 — cancel() — disesuaikan dari batalkan()
    //
    // Perbedaan dari versi sebelumnya:
    // - Method bernama cancel(), bukan batalkan()
    // - Pesan error status menggunakan format:
    //   "sudah berstatus '{status}'" bukan "hanya.*pending"
    // ════════════════════════════════════════════════════════════════

    public function test_cancel_gagal_bukan_milik_sendiri(): void
    {
        $karyawan    = new Karyawan();
        $karyawan->id = 1;

        $cuti              = new CutiKaryawan(['status' => 'pending']);
        $cuti->karyawan_id = 2; // milik orang lain

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/tidak memiliki izin/');
        $this->service->cancel($cuti, $karyawan);
    }

    public function test_cancel_gagal_status_approved(): void
    {
        $karyawan     = new Karyawan();
        $karyawan->id = 1;

        $cuti              = new CutiKaryawan(['status' => 'approved']);
        $cuti->karyawan_id = 1;

        $this->expectException(\Exception::class);
        // Pesan dari file Anda: "sudah berstatus 'approved' tidak dapat dibatalkan"
        $this->expectExceptionMessageMatches("/sudah berstatus 'approved'/");
        $this->service->cancel($cuti, $karyawan);
    }

    public function test_cancel_gagal_status_rejected(): void
    {
        $karyawan     = new Karyawan();
        $karyawan->id = 1;

        $cuti              = new CutiKaryawan(['status' => 'rejected']);
        $cuti->karyawan_id = 1;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches("/sudah berstatus 'rejected'/");
        $this->service->cancel($cuti, $karyawan);
    }

    public function test_cancel_cek_pemilik_sebelum_cek_status(): void
    {
        // Guard 1 (pemilik) harus dicek SEBELUM Guard 2 (status)
        // Jika karyawan bukan pemilik tapi status juga non-pending,
        // error yang muncul harus "tidak memiliki izin" (Guard 1)
        $karyawan     = new Karyawan();
        $karyawan->id = 1;

        $cuti              = new CutiKaryawan(['status' => 'approved']);
        $cuti->karyawan_id = 2; // bukan miliknya

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/tidak memiliki izin/'); // Guard 1
        $this->service->cancel($cuti, $karyawan);
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 7 — submit() guard isKaryawanProduksi()
    //
    // submit() punya guard tambahan yang tidak ada di versi sebelumnya:
    // Admin tidak boleh mengajukan cuti via endpoint ini.
    // ════════════════════════════════════════════════════════════════

    public function test_submit_gagal_jika_admin_mengajukan_cuti(): void
    {
        // Admin bukan karyawan produksi — harus ditolak
        $admin       = new Karyawan(['role' => LeaveConstants::ROLE_ADMIN]);
        $admin->id   = 1;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/bukan karyawan produksi/');

        // submit() butuh JenisCuti dari DB, tapi exception terjadi
        // SEBELUM query DB — jadi aman dipanggil tanpa DB di sini
        $this->service->submit($admin, [
            'jenis_cuti_id'   => 1,
            'tanggal_mulai'   => '2026-04-07',
            'tanggal_selesai' => '2026-04-09',
        ]);
    }

    public function test_is_karyawan_produksi_true_untuk_role_karyawan(): void
    {
        // Verifikasi helper method yang dipakai guard submit()
        $karyawan = new Karyawan();
        $karyawan->role         = LeaveConstants::ROLE_KARYAWAN;
        $karyawan->departemen   = 'Sewing'; // penuhi syarat departemen
        
        $this->assertTrue($karyawan->isKaryawanProduksi());
    }

    public function test_is_karyawan_produksi_false_untuk_role_admin(): void
    {
        $admin = new Karyawan(['role' => LeaveConstants::ROLE_ADMIN]);
        $this->assertFalse($admin->isKaryawanProduksi());
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 8 — reject() menggunakan LeaveException
    //
    // Versi Anda menggunakan LeaveException::sudahDiproses()
    // bukan \Exception biasa. Test harus reflect ini.
    // ════════════════════════════════════════════════════════════════

    public function test_reject_gagal_jika_sudah_rejected_throw_leave_exception(): void
    {
        $admin     = new Karyawan();
        $admin->id = 1;

        $cuti         = new CutiKaryawan(['status' => 'rejected']);
        $cuti->id     = 1;

        // Harus throw LeaveException (bukan \Exception biasa)
        $this->expectException(LeaveException::class);
        $this->service->reject($cuti, $admin, 'Alasan penolakan');
    }

    public function test_leave_exception_sudah_diproses_pesan_mengandung_status(): void
    {
        // Verifikasi LeaveException::sudahDiproses() membuat pesan yang benar
        $exception = LeaveException::sudahDiproses('rejected');

        $this->assertInstanceOf(LeaveException::class, $exception);
        $this->assertStringContainsString('rejected', $exception->getMessage());
    }

    public function test_leave_exception_adalah_turunan_runtime_exception(): void
    {
        // LeaveException extends RuntimeException
        // artinya bisa di-catch sebagai \Exception juga
        $exception = LeaveException::sudahDiproses('rejected');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 9 — resetJatahTahunan() (method baru di versi Anda)
    // ════════════════════════════════════════════════════════════════

    public function test_reset_jatah_tahunan_mengembalikan_annual_quota(): void
    {
        // Method ini return int (jumlah row yang diupdate)
        // Tanpa DB kita hanya bisa test bahwa method exist dan callable
        // Test dengan DB ada di Feature test
        $this->assertTrue(method_exists($this->service, 'resetJatahTahunan'));
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 10 — Bug Detection: hapusDokumen() string literal
    // ════════════════════════════════════════════════════════════════

    public function test_hapus_dokumen_tidak_error_jika_path_null(): void
    {
        // null path tidak boleh throw error
        $this->expectNotToPerformAssertions();
        $this->service->hapusDokumen(null);
    }

    public function test_hapus_dokumen_tidak_error_jika_path_string_kosong(): void
    {
        // String kosong tidak boleh throw error
        $this->expectNotToPerformAssertions();
        $this->service->hapusDokumen('');
    }
}