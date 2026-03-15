<?php

namespace Tests\Unit\Services;

use App\Exceptions\LeaveException;
use App\Models\CutiKaryawan;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use App\Services\CutiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CutiServiceTest extends TestCase
{
    use RefreshDatabase;

    private CutiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CutiService();
        $this->seed(\Database\Seeders\JenisCutiSeeder::class);
    }

    // ================================================================
    // hitungHari
    // ================================================================

    public function test_hitung_hari_tanggal_sama_adalah_satu_hari(): void
    {
        $result = $this->service->hitungHari('2026-03-10', '2026-03-10');
        $this->assertSame(1, $result);
    }

    public function test_hitung_hari_tiga_hari_inklusif(): void
    {
        // 10 Mar → 12 Mar = 3 hari (10, 11, 12)
        $result = $this->service->hitungHari('2026-03-10', '2026-03-12');
        $this->assertSame(3, $result);
    }

    public function test_hitung_hari_satu_minggu(): void
    {
        $result = $this->service->hitungHari('2026-03-10', '2026-03-16');
        $this->assertSame(7, $result);
    }

    public function test_hitung_hari_lintas_bulan(): void
    {
        // 28 Mar → 3 Apr = 7 hari
        $result = $this->service->hitungHari('2026-03-28', '2026-04-03');
        $this->assertSame(7, $result);
    }

    public function test_hitung_hari_lintas_tahun(): void
    {
        // 30 Des → 2 Jan = 4 hari (30, 31, 1, 2)
        $result = $this->service->hitungHari('2025-12-30', '2026-01-02');
        $this->assertSame(4, $result);
    }

    public function test_hitung_hari_dua_belas_hari(): void
    {
        $result = $this->service->hitungHari('2026-01-01', '2026-01-12');
        $this->assertSame(12, $result);
    }

    // ================================================================
    // validasiSisaCuti
    // ================================================================

    public function test_validasi_lolos_jika_jenis_cuti_tidak_potong_jatah(): void
    {
        // sisa_cuti = 0, tapi jenis cuti tidak potong jatah → harus lolos
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 0]);
        $jenisCuti = new JenisCuti(['potong_jatah' => false]);

        $this->expectNotToPerformAssertions();
        $this->service->validasiSisaCuti($karyawan, $jenisCuti, 5);
    }

    public function test_validasi_lolos_jika_sisa_cuti_pas_sama_dengan_kebutuhan(): void
    {
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 5]);
        $jenisCuti = new JenisCuti(['potong_jatah' => true]);

        $this->expectNotToPerformAssertions();
        $this->service->validasiSisaCuti($karyawan, $jenisCuti, 5);
    }

    public function test_validasi_lolos_jika_sisa_cuti_lebih_dari_cukup(): void
    {
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 12]);
        $jenisCuti = new JenisCuti(['potong_jatah' => true]);

        $this->expectNotToPerformAssertions();
        $this->service->validasiSisaCuti($karyawan, $jenisCuti, 3);
    }

    public function test_validasi_gagal_jika_sisa_cuti_kurang(): void
    {
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 2]);
        $jenisCuti = new JenisCuti(['potong_jatah' => true]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/tidak mencukupi/');
        $this->service->validasiSisaCuti($karyawan, $jenisCuti, 3);
    }

    public function test_validasi_gagal_jika_sisa_cuti_nol(): void
    {
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 0]);
        $jenisCuti = new JenisCuti(['potong_jatah' => true]);

        $this->expectException(\Exception::class);
        $this->service->validasiSisaCuti($karyawan, $jenisCuti, 1);
    }

    public function test_validasi_pesan_error_menyebut_sisa_dan_kebutuhan(): void
    {
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 3]);
        $jenisCuti = new JenisCuti(['potong_jatah' => true]);

        try {
            $this->service->validasiSisaCuti($karyawan, $jenisCuti, 7);
            $this->fail('Exception seharusnya dilempar.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('3', $e->getMessage()); // sisa
            $this->assertStringContainsString('7', $e->getMessage()); // kebutuhan
        }
    }

    // ================================================================
    // submit
    // ================================================================

    public function test_submit_berhasil_cuti_tahunan(): void
    {
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 12]);
        $jenisCuti = JenisCuti::where('potong_jatah', true)->first();

        Log::shouldReceive('info')->once()->with('Cuti diajukan', \Mockery::any());

        $cuti = $this->service->submit($karyawan, [
            'jenis_cuti_id'  => $jenisCuti->id,
            'tanggal_mulai'  => '2026-04-01',
            'tanggal_selesai'=> '2026-04-03',
            'keterangan'     => 'Liburan keluarga',
        ]);

        $this->assertInstanceOf(CutiKaryawan::class, $cuti);
        $this->assertSame('pending', $cuti->status);
        $this->assertSame(3, $cuti->jumlah_hari);
        $this->assertSame($karyawan->id, $cuti->karyawan_id);
        $this->assertSame($jenisCuti->id, $cuti->jenis_cuti_id);
        $this->assertDatabaseHas('cuti_karyawan', [
            'karyawan_id'   => $karyawan->id,
            'status'        => 'pending',
            'jumlah_hari'   => 3,
        ]);
    }

    public function test_submit_berhasil_izin_meski_sisa_cuti_nol(): void
    {
        // Jenis cuti Izin tidak potong jatah — boleh meski sisa=0
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 0]);
        $jenisCuti = JenisCuti::where('nama', 'Izin')->first();

        Log::shouldReceive('info')->once();

        $cuti = $this->service->submit($karyawan, [
            'jenis_cuti_id'  => $jenisCuti->id,
            'tanggal_mulai'  => '2026-04-10',
            'tanggal_selesai'=> '2026-04-10',
        ]);

        $this->assertSame('pending', $cuti->status);
        $this->assertSame(1, $cuti->jumlah_hari);
    }

    public function test_submit_berhasil_tanpa_keterangan(): void
    {
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 12]);
        $jenisCuti = JenisCuti::first();

        Log::shouldReceive('info')->once();

        $cuti = $this->service->submit($karyawan, [
            'jenis_cuti_id'  => $jenisCuti->id,
            'tanggal_mulai'  => '2026-05-01',
            'tanggal_selesai'=> '2026-05-01',
        ]);

        $this->assertNull($cuti->keterangan);
    }

    public function test_submit_gagal_jika_bukan_karyawan_produksi(): void
    {
        // Karyawan tanpa departemen (atau bukan produksi) tidak bisa submit
        $karyawan  = Karyawan::factory()->admin()->create();
        $jenisCuti = JenisCuti::first();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/bukan karyawan produksi/');

        $this->service->submit($karyawan, [
            'jenis_cuti_id'  => $jenisCuti->id,
            'tanggal_mulai'  => '2026-04-01',
            'tanggal_selesai'=> '2026-04-03',
        ]);
    }

    public function test_submit_gagal_jika_sisa_cuti_tidak_cukup(): void
    {
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 2]);
        $jenisCuti = JenisCuti::where('potong_jatah', true)->first();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/tidak mencukupi/');

        $this->service->submit($karyawan, [
            'jenis_cuti_id'  => $jenisCuti->id,
            'tanggal_mulai'  => '2026-04-01',
            'tanggal_selesai'=> '2026-04-05', // 5 hari, sisa 2
        ]);
    }

    public function test_submit_gagal_jika_tanggal_tumpang_tindih_dengan_pending(): void
    {
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 12]);
        $jenisCuti = JenisCuti::first();

        // Buat pengajuan pending yang sudah ada
        CutiKaryawan::factory()->create([
            'karyawan_id'    => $karyawan->id,
            'jenis_cuti_id'  => $jenisCuti->id,
            'tanggal_mulai'  => '2026-04-05',
            'tanggal_selesai'=> '2026-04-10',
            'jumlah_hari'    => 6,
            'status'         => 'pending',
        ]);

        // Pengajuan baru dengan tanggal overlap
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/bertabrakan/');

        $this->service->submit($karyawan, [
            'jenis_cuti_id'  => $jenisCuti->id,
            'tanggal_mulai'  => '2026-04-08',  // overlap dengan 5–10
            'tanggal_selesai'=> '2026-04-12',
        ]);
    }

    public function test_submit_gagal_jika_tanggal_tumpang_tindih_dengan_approved(): void
    {
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 12]);
        $jenisCuti = JenisCuti::first();

        CutiKaryawan::factory()->create([
            'karyawan_id'    => $karyawan->id,
            'jenis_cuti_id'  => $jenisCuti->id,
            'tanggal_mulai'  => '2026-04-05',
            'tanggal_selesai'=> '2026-04-10',
            'jumlah_hari'    => 6,
            'status'         => 'approved',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/bertabrakan/');

        $this->service->submit($karyawan, [
            'jenis_cuti_id'  => $jenisCuti->id,
            'tanggal_mulai'  => '2026-04-01',
            'tanggal_selesai'=> '2026-04-07',  // overlap di awal
        ]);
    }

    public function test_submit_berhasil_jika_tumpang_tindih_hanya_dengan_rejected(): void
    {
        // Pengajuan rejected TIDAK menghalangi pengajuan baru
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 12]);
        $jenisCuti = JenisCuti::first();

        CutiKaryawan::factory()->create([
            'karyawan_id'    => $karyawan->id,
            'jenis_cuti_id'  => $jenisCuti->id,
            'tanggal_mulai'  => '2026-04-05',
            'tanggal_selesai'=> '2026-04-10',
            'jumlah_hari'    => 6,
            'status'         => 'rejected',
        ]);

        Log::shouldReceive('info')->once();

        // Harus berhasil — rejected tidak dihitung sebagai konflik
        $cuti = $this->service->submit($karyawan, [
            'jenis_cuti_id'  => $jenisCuti->id,
            'tanggal_mulai'  => '2026-04-07',
            'tanggal_selesai'=> '2026-04-09',
        ]);

        $this->assertSame('pending', $cuti->status);
    }

    public function test_submit_tidak_mengurangi_sisa_cuti_saat_pengajuan(): void
    {
        // BR-06: sisa cuti baru berkurang saat APPROVE, bukan saat submit
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 12]);
        $jenisCuti = JenisCuti::where('potong_jatah', true)->first();

        Log::shouldReceive('info')->once();

        $this->service->submit($karyawan, [
            'jenis_cuti_id'  => $jenisCuti->id,
            'tanggal_mulai'  => '2026-04-01',
            'tanggal_selesai'=> '2026-04-03',
        ]);

        $karyawan->refresh();
        $this->assertSame(12, $karyawan->sisa_cuti); // belum berkurang
    }

    // ================================================================
    // cancel
    // ================================================================

    public function test_cancel_berhasil_saat_status_pending(): void
    {
        $karyawan = Karyawan::factory()->create();
        $cuti     = CutiKaryawan::factory()->create([
            'karyawan_id' => $karyawan->id,
            'status'      => 'pending',
        ]);

        $this->service->cancel($cuti, $karyawan);

        $this->assertNull(CutiKaryawan::withTrashed()->find($cuti->id));
    }

    public function test_cancel_gagal_jika_bukan_milik_karyawan_sendiri(): void
    {
        $karyawan1 = Karyawan::factory()->create();
        $karyawan2 = Karyawan::factory()->create();

        $cuti = CutiKaryawan::factory()->create([
            'karyawan_id' => $karyawan1->id,
            'status'      => 'pending',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/tidak memiliki izin/');

        $this->service->cancel($cuti, $karyawan2);
    }

    public function test_cancel_gagal_jika_status_approved(): void
    {
        $karyawan = Karyawan::factory()->create();
        $cuti     = CutiKaryawan::factory()->create([
            'karyawan_id' => $karyawan->id,
            'status'      => 'approved',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/approved/');

        $this->service->cancel($cuti, $karyawan);
    }

    public function test_cancel_gagal_jika_status_rejected(): void
    {
        $karyawan = Karyawan::factory()->create();
        $cuti     = CutiKaryawan::factory()->create([
            'karyawan_id' => $karyawan->id,
            'status'      => 'rejected',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/rejected/');

        $this->service->cancel($cuti, $karyawan);
    }

    public function test_cancel_tidak_mengembalikan_sisa_cuti(): void
    {
        // Cancel hanya boleh saat pending — dan pending belum potong jatah
        // Jadi sisa_cuti tidak perlu dikembalikan
        $karyawan = Karyawan::factory()->create(['sisa_cuti' => 10]);
        $cuti     = CutiKaryawan::factory()->create([
            'karyawan_id' => $karyawan->id,
            'jumlah_hari' => 3,
            'status'      => 'pending',
        ]);

        $this->service->cancel($cuti, $karyawan);

        $karyawan->refresh();
        $this->assertSame(10, $karyawan->sisa_cuti); // tidak berubah
    }

    // ================================================================
    // listByKaryawan
    // ================================================================

    public function test_list_by_karyawan_return_hanya_milik_sendiri(): void
    {
        $karyawan1 = Karyawan::factory()->create();
        $karyawan2 = Karyawan::factory()->create();

        CutiKaryawan::factory()->count(3)->create(['karyawan_id' => $karyawan1->id]);
        CutiKaryawan::factory()->count(2)->create(['karyawan_id' => $karyawan2->id]);

        $result = $this->service->listByKaryawan($karyawan1);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(3, $result->total());
        $result->each(fn($c) => $this->assertSame($karyawan1->id, $c->karyawan_id));
    }

    public function test_list_by_karyawan_filter_by_status(): void
    {
        $karyawan = Karyawan::factory()->create();
        CutiKaryawan::factory()->count(2)->create(['karyawan_id' => $karyawan->id, 'status' => 'pending']);
        CutiKaryawan::factory()->count(3)->create(['karyawan_id' => $karyawan->id, 'status' => 'approved']);

        $result = $this->service->listByKaryawan($karyawan, status: 'approved');
        $this->assertSame(3, $result->total());
    }

    public function test_list_by_karyawan_filter_by_tahun(): void
    {
        $karyawan  = Karyawan::factory()->create();
        $jenisCuti = JenisCuti::first();

        CutiKaryawan::factory()->count(2)->create([
            'karyawan_id'    => $karyawan->id,
            'jenis_cuti_id'  => $jenisCuti->id,
            'tanggal_mulai'  => '2026-03-01',
            'tanggal_selesai'=> '2026-03-02',
        ]);
        CutiKaryawan::factory()->count(1)->create([
            'karyawan_id'    => $karyawan->id,
            'jenis_cuti_id'  => $jenisCuti->id,
            'tanggal_mulai'  => '2025-05-01',
            'tanggal_selesai'=> '2025-05-02',
        ]);

        $result = $this->service->listByKaryawan($karyawan, tahun: 2026);
        $this->assertSame(2, $result->total());
    }

    public function test_list_by_karyawan_return_kosong_jika_belum_pernah_ajukan(): void
    {
        $karyawan = Karyawan::factory()->create();

        $result = $this->service->listByKaryawan($karyawan);
        $this->assertSame(0, $result->total());
    }

    // ================================================================
    // approve
    // ================================================================

    public function test_approve_berhasil_ubah_status_ke_approved(): void
    {
        $admin    = Karyawan::factory()->admin()->create();
        $karyawan = Karyawan::factory()->create(['sisa_cuti' => 12]);
        $cuti     = CutiKaryawan::factory()->pending()->create([
            'karyawan_id' => $karyawan->id,
            'jumlah_hari' => 3,
        ]);

        Log::shouldReceive('info')->once()->with('Cuti disetujui', \Mockery::any());

        $result = $this->service->approve($cuti, $admin, 'Disetujui.');

        $this->assertSame('approved', $result->status);
        $this->assertSame($admin->id, $result->approved_by);
        $this->assertNotNull($result->approved_at);
    }

    public function test_approve_memotong_sisa_cuti_jika_potong_jatah(): void
    {
        $admin     = Karyawan::factory()->admin()->create();
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 12]);
        $jenisCuti = JenisCuti::where('potong_jatah', true)->first(); // Cuti Tahunan

        $cuti = CutiKaryawan::factory()->pending()->create([
            'karyawan_id'   => $karyawan->id,
            'jenis_cuti_id' => $jenisCuti->id,
            'jumlah_hari'   => 3,
        ]);

        Log::shouldReceive('info')->once();

        $this->service->approve($cuti, $admin);

        $karyawan->refresh();
        $this->assertSame(9, $karyawan->sisa_cuti); // 12 - 3 = 9
    }

    public function test_approve_tidak_memotong_sisa_cuti_jika_tidak_potong_jatah(): void
    {
        $admin     = Karyawan::factory()->admin()->create();
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 12]);
        $jenisCuti = JenisCuti::where('potong_jatah', false)->first(); // Izin / Sakit

        $cuti = CutiKaryawan::factory()->pending()->create([
            'karyawan_id'   => $karyawan->id,
            'jenis_cuti_id' => $jenisCuti->id,
            'jumlah_hari'   => 3,
        ]);

        Log::shouldReceive('info')->once();

        $this->service->approve($cuti, $admin);

        $karyawan->refresh();
        $this->assertSame(12, $karyawan->sisa_cuti); // tidak berubah
    }

    public function test_approve_gagal_jika_status_sudah_approved(): void
    {
        $admin = Karyawan::factory()->admin()->create();
        $cuti  = CutiKaryawan::factory()->create(['status' => 'approved']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/tidak dapat diproses ulang/');

        $this->service->approve($cuti, $admin);
    }

    public function test_approve_gagal_jika_status_sudah_rejected(): void
    {
        $admin = Karyawan::factory()->admin()->create();
        $cuti  = CutiKaryawan::factory()->create(['status' => 'rejected']);

        $this->expectException(\Exception::class);
        $this->service->approve($cuti, $admin);
    }

    public function test_approve_gagal_jika_sisa_cuti_tidak_cukup_saat_approve(): void
    {
        // Edge case: sisa cuti berkurang antara waktu submit dan approve
        $admin     = Karyawan::factory()->admin()->create();
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 2]);
        $jenisCuti = JenisCuti::where('potong_jatah', true)->first();

        $cuti = CutiKaryawan::factory()->pending()->create([
            'karyawan_id'   => $karyawan->id,
            'jenis_cuti_id' => $jenisCuti->id,
            'jumlah_hari'   => 5, // butuh 5, sisa hanya 2
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/tidak lagi mencukupi/');

        $this->service->approve($cuti, $admin);
    }

    public function test_approve_return_instance_cuti_karyawan(): void
    {
        $admin    = Karyawan::factory()->admin()->create();
        $karyawan = Karyawan::factory()->create(['sisa_cuti' => 12]);
        $cuti     = CutiKaryawan::factory()->pending()->create([
            'karyawan_id' => $karyawan->id,
            'jumlah_hari' => 1,
        ]);

        Log::shouldReceive('info')->once();

        $result = $this->service->approve($cuti, $admin);
        $this->assertInstanceOf(CutiKaryawan::class, $result);
    }

    public function test_approve_menyimpan_catatan_admin(): void
    {
        $admin    = Karyawan::factory()->admin()->create();
        $karyawan = Karyawan::factory()->create(['sisa_cuti' => 12]);
        $cuti     = CutiKaryawan::factory()->pending()->create([
            'karyawan_id' => $karyawan->id,
            'jumlah_hari' => 1,
        ]);

        Log::shouldReceive('info')->once();

        $result = $this->service->approve($cuti, $admin, 'Silakan.');
        $this->assertSame('Silakan.', $result->catatan_admin);
    }

    // ================================================================
    // reject
    // ================================================================

    public function test_reject_berhasil_dari_status_pending(): void
    {
        $admin    = Karyawan::factory()->admin()->create();
        $karyawan = Karyawan::factory()->create(['sisa_cuti' => 12]);
        $cuti     = CutiKaryawan::factory()->pending()->create([
            'karyawan_id' => $karyawan->id,
        ]);

        Log::shouldReceive('info')->once()->with('Cuti ditolak', \Mockery::any());

        $result = $this->service->reject($cuti, $admin, 'Stok SDM tidak mencukupi.');

        $this->assertSame('rejected', $result->status);
        $this->assertSame('Stok SDM tidak mencukupi.', $result->catatan_admin);
        $this->assertSame($admin->id, $result->approved_by);
    }

    public function test_reject_dari_approved_mengembalikan_sisa_cuti(): void
    {
        // BR-07: jika cuti sudah approved lalu di-reject, jatah dikembalikan
        $admin     = Karyawan::factory()->admin()->create();
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 9]);
        $jenisCuti = JenisCuti::where('potong_jatah', true)->first();

        $cuti = CutiKaryawan::factory()->create([
            'karyawan_id'   => $karyawan->id,
            'jenis_cuti_id' => $jenisCuti->id,
            'jumlah_hari'   => 3,
            'status'        => 'approved', // sebelumnya sudah dipotong
        ]);

        Log::shouldReceive('info')->once();

        $this->service->reject($cuti, $admin, 'Kesalahan approve, dibatalkan.');

        $karyawan->refresh();
        $this->assertSame(12, $karyawan->sisa_cuti); // 9 + 3 = 12, kembali
    }

    public function test_reject_dari_approved_tidak_mengembalikan_jika_tidak_potong_jatah(): void
    {
        $admin     = Karyawan::factory()->admin()->create();
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 12]);
        $jenisCuti = JenisCuti::where('potong_jatah', false)->first(); // Izin/Sakit

        $cuti = CutiKaryawan::factory()->create([
            'karyawan_id'   => $karyawan->id,
            'jenis_cuti_id' => $jenisCuti->id,
            'jumlah_hari'   => 3,
            'status'        => 'approved',
        ]);

        Log::shouldReceive('info')->once();

        $this->service->reject($cuti, $admin, 'Dibatalkan.');

        $karyawan->refresh();
        $this->assertSame(12, $karyawan->sisa_cuti); // tidak berubah
    }

    public function test_reject_tidak_melebihi_batas_maksimal_sisa_cuti(): void
    {
        // Edge case: sisa_cuti tidak boleh > 12 setelah dikembalikan
        $admin     = Karyawan::factory()->admin()->create();
        $karyawan  = Karyawan::factory()->create(['sisa_cuti' => 11]);
        $jenisCuti = JenisCuti::where('potong_jatah', true)->first();

        $cuti = CutiKaryawan::factory()->create([
            'karyawan_id'   => $karyawan->id,
            'jenis_cuti_id' => $jenisCuti->id,
            'jumlah_hari'   => 5,
            'status'        => 'approved',
        ]);

        Log::shouldReceive('info')->once();

        $this->service->reject($cuti, $admin, 'Dibatalkan.');

        $karyawan->refresh();
        $this->assertSame(12, $karyawan->sisa_cuti); // di-cap di 12
    }

    public function test_reject_gagal_jika_sudah_rejected(): void
    {
        $admin = Karyawan::factory()->admin()->create();
        $cuti  = CutiKaryawan::factory()->create(['status' => 'rejected']);

        $this->expectException(LeaveException::class);

        $this->service->reject($cuti, $admin, 'Coba reject ulang.');
    }

    public function test_reject_return_instance_cuti_karyawan(): void
    {
        $admin    = Karyawan::factory()->admin()->create();
        $karyawan = Karyawan::factory()->create();
        $cuti     = CutiKaryawan::factory()->pending()->create([
            'karyawan_id' => $karyawan->id,
        ]);

        Log::shouldReceive('info')->once();

        $result = $this->service->reject($cuti, $admin, 'Ditolak karena alasan X.');
        $this->assertInstanceOf(CutiKaryawan::class, $result);
    }

    // ================================================================
    // listForAdmin
    // ================================================================

    public function test_list_for_admin_return_semua_pengajuan(): void
    {
        Karyawan::factory()->count(3)->create()->each(function ($k) {
            CutiKaryawan::factory()->count(2)->create(['karyawan_id' => $k->id]);
        });

        $result = $this->service->listForAdmin();

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(6, $result->total());
    }

    public function test_list_for_admin_filter_by_status(): void
    {
        $karyawan = Karyawan::factory()->create();
        CutiKaryawan::factory()->count(3)->create(['karyawan_id' => $karyawan->id, 'status' => 'pending']);
        CutiKaryawan::factory()->count(2)->create(['karyawan_id' => $karyawan->id, 'status' => 'approved']);

        $result = $this->service->listForAdmin(status: 'pending');
        $this->assertSame(3, $result->total());
    }

    public function test_list_for_admin_filter_by_departemen(): void
    {
        Karyawan::factory()->departemen('Sewing')->count(2)->create()->each(fn($k) =>
            CutiKaryawan::factory()->create(['karyawan_id' => $k->id])
        );
        Karyawan::factory()->departemen('Cutting')->count(1)->create()->each(fn($k) =>
            CutiKaryawan::factory()->create(['karyawan_id' => $k->id])
        );

        $result = $this->service->listForAdmin(departemen: 'Sewing');
        $this->assertSame(2, $result->total());
    }

    public function test_list_for_admin_filter_by_tahun(): void
    {
        $karyawan  = Karyawan::factory()->create();
        $jenisCuti = JenisCuti::first();

        CutiKaryawan::factory()->count(2)->create([
            'karyawan_id'    => $karyawan->id,
            'jenis_cuti_id'  => $jenisCuti->id,
            'tanggal_mulai'  => '2026-04-01',
            'tanggal_selesai'=> '2026-04-02',
        ]);
        CutiKaryawan::factory()->count(1)->create([
            'karyawan_id'    => $karyawan->id,
            'jenis_cuti_id'  => $jenisCuti->id,
            'tanggal_mulai'  => '2025-06-01',
            'tanggal_selesai'=> '2025-06-02',
        ]);

        $result = $this->service->listForAdmin(tahun: 2026);
        $this->assertSame(2, $result->total());
    }

    public function test_list_for_admin_default_per_page_15(): void
    {
        $karyawan = Karyawan::factory()->create();
        CutiKaryawan::factory()->count(20)->create(['karyawan_id' => $karyawan->id]);

        $result = $this->service->listForAdmin();
        $this->assertSame(15, $result->perPage());
        $this->assertSame(20, $result->total());
    }

    // ================================================================
    // rekapPerDepartemen
    // ================================================================

    public function test_rekap_per_departemen_return_semua_departemen(): void
    {
        $result = $this->service->rekapPerDepartemen();

        $this->assertArrayHasKey('Sewing', $result);
        $this->assertArrayHasKey('Cutting', $result);
        $this->assertArrayHasKey('Finishing', $result);
        $this->assertArrayHasKey('QA', $result);
    }

    public function test_rekap_per_departemen_struktur_data_benar(): void
    {
        $result = $this->service->rekapPerDepartemen();

        foreach ($result as $dept => $data) {
            $this->assertArrayHasKey('total_karyawan', $data);
            $this->assertArrayHasKey('pending', $data);
            $this->assertArrayHasKey('approved', $data);
            $this->assertArrayHasKey('rejected', $data);
        }
    }

    public function test_rekap_per_departemen_hitung_karyawan_dengan_benar(): void
    {
        Karyawan::factory()->departemen('Sewing')->count(3)->create();
        Karyawan::factory()->departemen('Cutting')->count(1)->create();

        $result = $this->service->rekapPerDepartemen();

        $this->assertSame(3, $result['Sewing']['total_karyawan']);
        $this->assertSame(1, $result['Cutting']['total_karyawan']);
    }

    public function test_rekap_per_departemen_tidak_menghitung_admin(): void
    {
        // Admin tidak boleh masuk hitungan total_karyawan departemen
        Karyawan::factory()->admin()->create(); // admin punya dept QA via factory
        Karyawan::factory()->departemen('QA')->count(2)->create();

        $result = $this->service->rekapPerDepartemen();

        // Hanya 2 karyawan produksi QA — admin tidak dihitung
        $this->assertSame(2, $result['QA']['total_karyawan']);
    }

    public function test_rekap_per_departemen_hitung_status_cuti_dengan_benar(): void
    {
        $karyawan  = Karyawan::factory()->departemen('Finishing')->create();
        $jenisCuti = JenisCuti::first();

        CutiKaryawan::factory()->count(2)->create([
            'karyawan_id'    => $karyawan->id,
            'jenis_cuti_id'  => $jenisCuti->id,
            'status'         => 'pending',
            'tanggal_mulai'  => now()->format('Y-m-d'),
            'tanggal_selesai'=> now()->format('Y-m-d'),
        ]);
        CutiKaryawan::factory()->count(1)->create([
            'karyawan_id'    => $karyawan->id,
            'jenis_cuti_id'  => $jenisCuti->id,
            'status'         => 'approved',
            'tanggal_mulai'  => now()->format('Y-m-d'),
            'tanggal_selesai'=> now()->format('Y-m-d'),
        ]);

        $result = $this->service->rekapPerDepartemen();

        $this->assertSame(2, $result['Finishing']['pending']);
        $this->assertSame(1, $result['Finishing']['approved']);
        $this->assertSame(0, $result['Finishing']['rejected']);
    }

    // ================================================================
    // resetJatahTahunan
    // ================================================================

    public function test_reset_jatah_tahunan_mengembalikan_semua_ke_12(): void
    {
        Karyawan::factory()->create(['sisa_cuti' => 0]);
        Karyawan::factory()->create(['sisa_cuti' => 5]);
        Karyawan::factory()->create(['sisa_cuti' => 8]);

        $jumlahDiupdate = $this->service->resetJatahTahunan();

        $this->assertSame(3, $jumlahDiupdate);
        Karyawan::whereNotNull('departemen')->each(function ($k) {
            $this->assertSame(12, $k->fresh()->sisa_cuti);
        });
    }

    public function test_reset_jatah_tahunan_return_jumlah_baris_diupdate(): void
    {
        Karyawan::factory()->count(5)->create(['sisa_cuti' => 5]);

        $result = $this->service->resetJatahTahunan();
        $this->assertSame(5, $result);
    }

    public function test_reset_jatah_tahunan_tidak_mempengaruhi_admin_tanpa_departemen(): void
    {
        $admin = Karyawan::factory()->admin()->create(['sisa_cuti'  => 0]);
        Karyawan::factory()->count(2)->create(['sisa_cuti' => 5]);

        $this->service->resetJatahTahunan();

        $this->assertSame(0, $admin->fresh()->sisa_cuti); // admin tidak tersentuh
    }
}