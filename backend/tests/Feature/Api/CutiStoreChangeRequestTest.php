<?php

namespace Tests\Feature\Api;

use App\Constants\LeaveConstants;
use App\Models\CutiKaryawan;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CutiStoreChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    private Karyawan $karyawan;
    private Karyawan $admin;
    private string   $token;
    private string   $tokenAdmin;

    // ID diambil dinamis dari DB setelah seeder — bukan hardcode integer
    // Konsisten dengan pola di CutiStoreTest
    private int $idCutiTahunan;
    private int $idIzin;
    private int $idSakit;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(\Database\Seeders\JenisCutiSeeder::class);

        // ── Ambil ID dinamis dari DB setelah seeder ──────────────────
        // Jangan hardcode 1, 2, 3 — bisa berubah tergantung urutan insert
        $this->idCutiTahunan = JenisCuti::where('nama', 'Cuti Tahunan')
                                                    ->value('id');
        $this->idIzin        = JenisCuti::where('nama', 'Izin')
                                                    ->value('id');
        $this->idSakit       = JenisCuti::where('nama', 'Sakit')
                                                    ->value('id');

        // Pastikan seeder berjalan benar
        $this->assertNotNull($this->idCutiTahunan, 'Seeder JenisCuti belum jalan');
        $this->assertNotNull($this->idIzin,        'Seeder JenisCuti belum jalan');
        $this->assertNotNull($this->idSakit,       'Seeder JenisCuti belum jalan');

        // Karyawan produksi biasa
        $this->karyawan = Karyawan::factory()->create([
            'role'      => LeaveConstants::ROLE_KARYAWAN,
            'sisa_cuti' => 12,
        ]);
        $this->token = $this->karyawan
            ->createToken('test', [LeaveConstants::ROLE_KARYAWAN])
            ->plainTextToken;

        // Admin — untuk test guard isKaryawanProduksi()
        $this->admin = Karyawan::factory()->admin()->create();
        $this->tokenAdmin = $this->admin
            ->createToken('admin', [
                LeaveConstants::ROLE_ADMIN,
                LeaveConstants::ROLE_KARYAWAN,
            ])
            ->plainTextToken;
    }

    // ── Helper ────────────────────────────────────────────────────

    private function tanggal(int $offsetHari = 1): string
    {
        return now('Asia/Jakarta')->addDays($offsetHari)->format('Y-m-d');
    }

    /**
     * POST /api/cuti via postJson — untuk request tanpa file
     */
    private function postCuti(array $data, ?string $token = null): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token ?? $this->token)
                    ->postJson('/api/cuti', $data);
    }

    /**
     * POST /api/cuti via post() multipart — untuk request dengan file
     */
    private function postCutiDenganFile(array $data, ?string $token = null): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token ?? $this->token)
                    ->post('/api/cuti', $data);
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 1 — Guard: isKaryawanProduksi()
    // ════════════════════════════════════════════════════════════════

    public function test_admin_tidak_boleh_submit_cuti(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'keterangan'     => 'Admin coba ajukan cuti',
        ], $this->tokenAdmin)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', fn($msg) =>
                str_contains($msg, 'bukan karyawan produksi')
            );
    }

    public function test_karyawan_produksi_boleh_submit_cuti(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'keterangan'     => 'Keperluan keluarga mendadak',
        ])->assertStatus(201);
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 2 — Keterangan Wajib (Cuti Tahunan & Izin)
    // ════════════════════════════════════════════════════════════════

    public function test_cuti_tahunan_tanpa_keterangan_ditolak(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(3),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['keterangan'])
            ->assertJsonPath('success', false);
    }

    public function test_izin_tanpa_keterangan_ditolak(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['keterangan']);
    }

    public function test_cuti_tahunan_keterangan_terlalu_pendek_ditolak(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(3),
            'keterangan'     => 'ab', // 2 karakter, min:3
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['keterangan']);
    }

    public function test_cuti_tahunan_dengan_keterangan_valid_diterima(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(3),
            'keterangan'     => 'Liburan ke kampung halaman',
        ])->assertStatus(201);
    }

    public function test_izin_dengan_keterangan_valid_diterima(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'keterangan'     => 'Mengurus keperluan keluarga',
        ])->assertStatus(201);
    }

    public function test_sakit_tanpa_keterangan_tetap_diterima(): void
    {
        // Keterangan opsional untuk Sakit — asal ada dokumen
        $dokumen = UploadedFile::fake()->create(
            'surat_dokter.pdf', 500, 'application/pdf'
        );

        $this->postCutiDenganFile([
            'jenis_cuti_id'  => $this->idSakit,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'dokumen'        => $dokumen,
            // keterangan tidak diisi — opsional untuk sakit
        ])->assertStatus(201);
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 3 — Dokumen Wajib (Sakit) & Opsional (Tahunan, Izin)
    // ════════════════════════════════════════════════════════════════

    public function test_sakit_tanpa_dokumen_ditolak(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idSakit,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(2),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dokumen']);
    }

    public function test_sakit_dokumen_pdf_diterima(): void
    {
        $dokumen = UploadedFile::fake()->create(
            'surat_sakit.pdf', 1024, 'application/pdf'
        );

        $this->postCutiDenganFile([
            'jenis_cuti_id'  => $this->idSakit,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'dokumen'        => $dokumen,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.dokumen_url', fn($url) => $url !== null)
            ->assertJsonPath('data.dokumen_nama', fn($nama) =>
                str_ends_with(strtolower($nama), '.pdf')
            );
    }

    public function test_sakit_dokumen_jpg_diterima(): void
    {
        $dokumen = UploadedFile::fake()->create('surat_sakit.jpg');

        $this->postCutiDenganFile([
            'jenis_cuti_id'  => $this->idSakit,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'dokumen'        => $dokumen,
        ])->assertStatus(201);
    }

    public function test_sakit_dokumen_png_diterima(): void
    {
        $dokumen = UploadedFile::fake()->create('hasil_lab.png');

        $this->postCutiDenganFile([
            'jenis_cuti_id'  => $this->idSakit,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'dokumen'        => $dokumen,
        ])->assertStatus(201);
    }

    public function test_dokumen_format_tidak_valid_ditolak(): void
    {
        $dokumen = UploadedFile::fake()->create(
            'lampiran.exe', 100, 'application/octet-stream'
        );

        $this->postCutiDenganFile([
            'jenis_cuti_id'  => $this->idSakit,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'dokumen'        => $dokumen,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dokumen']);
    }

    public function test_dokumen_melebihi_ukuran_maksimal_ditolak(): void
    {
        $dokumen = UploadedFile::fake()->create(
            'scan_besar.pdf', 3000, 'application/pdf' // 3 MB > limit 2 MB
        );

        $this->postCutiDenganFile([
            'jenis_cuti_id'  => $this->idSakit,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'dokumen'        => $dokumen,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dokumen']);
    }

    public function test_dokumen_tersimpan_di_storage_dengan_nama_unik(): void
    {
        $dokumen = UploadedFile::fake()->create(
            'surat.pdf', 500, 'application/pdf'
        );

        $this->postCutiDenganFile([
            'jenis_cuti_id'  => $this->idSakit,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'dokumen'        => $dokumen,
        ])->assertStatus(201);

        $cuti = CutiKaryawan::latest()->first();

        $this->assertNotNull($cuti->dokumen_path);
        $this->assertTrue(
            Storage::disk('public')->exists($cuti->dokumen_path),
            "File dokumen tidak ditemukan di storage: {$cuti->dokumen_path}"
        );
        $this->assertStringContainsString(
            (string) $this->karyawan->id,
            basename($cuti->dokumen_path)
        );
    }

    public function test_dua_upload_menghasilkan_nama_file_berbeda(): void
    {
        $buatDokumen = fn() => UploadedFile::fake()->create(
            'surat.pdf', 200, 'application/pdf'
        );

        $this->postCutiDenganFile([
            'jenis_cuti_id'  => $this->idSakit,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'dokumen'        => $buatDokumen(),
        ])->assertStatus(201);

        // Karyawan kedua supaya tidak trigger overlap
        $karyawan2 = Karyawan::factory()->create([
            'role'      => LeaveConstants::ROLE_KARYAWAN,
            'sisa_cuti' => 12,
        ]);
        $token2 = $karyawan2
            ->createToken('test', [LeaveConstants::ROLE_KARYAWAN])
            ->plainTextToken;

        $this->withToken($token2)->post('/api/cuti', [
            'jenis_cuti_id'  => $this->idSakit,
            'tanggal_mulai'  => $this->tanggal(3),
            'tanggal_selesai'=> $this->tanggal(3),
            'dokumen'        => $buatDokumen(),
        ])->assertStatus(201);

        $paths = CutiKaryawan::latest()->take(2)->pluck('dokumen_path');
        $this->assertNotEquals($paths[0], $paths[1]);
    }

    public function test_cuti_tahunan_tanpa_dokumen_tetap_diterima(): void
    {
        // Dokumen opsional untuk Cuti Tahunan
        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(3),
            'keterangan'     => 'Liburan tanpa lampiran',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.dokumen_url', null)
            ->assertJsonPath('data.dokumen_nama', null);
    }

    public function test_cuti_tahunan_dengan_dokumen_opsional_diterima(): void
    {
        $dokumen = UploadedFile::fake()->create(
            'lampiran.pdf', 200, 'application/pdf'
        );

        $this->postCutiDenganFile([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(3),
            'keterangan'     => 'Liburan dengan lampiran surat',
            'dokumen'        => $dokumen,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.dokumen_url', fn($url) => $url !== null);
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 4 — Overlap Detection
    // ════════════════════════════════════════════════════════════════

    public function test_overlap_dengan_pengajuan_pending_ditolak(): void
    {
        CutiKaryawan::factory()->create([
            'karyawan_id'     => $this->karyawan->id,
            'jenis_cuti_id'   => $this->idIzin,
            'tanggal_mulai'   => $this->tanggal(5),
            'tanggal_selesai' => $this->tanggal(10),
            'jumlah_hari'     => 6,
            'status'          => LeaveConstants::STATUS_PENDING,
        ]);

        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(7),
            'tanggal_selesai'=> $this->tanggal(8),
            'keterangan'     => 'Overlap di tengah',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['tanggal_mulai'])
            ->assertJsonPath('errors.tanggal_mulai.0', fn($msg) =>
                str_contains($msg, 'bertabrakan')
            );
    }

    public function test_overlap_dengan_pengajuan_approved_ditolak(): void
    {
        CutiKaryawan::factory()->create([
            'karyawan_id'     => $this->karyawan->id,
            'jenis_cuti_id'   => $this->idIzin,
            'tanggal_mulai'   => $this->tanggal(5),
            'tanggal_selesai' => $this->tanggal(10),
            'jumlah_hari'     => 6,
            'status'          => LeaveConstants::STATUS_APPROVED,
        ]);

        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(8),
            'tanggal_selesai'=> $this->tanggal(12),
            'keterangan'     => 'Overlap dengan yang approved',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tanggal_mulai']);
    }

    public function test_overlap_dengan_rejected_tidak_diblokir(): void
    {
        CutiKaryawan::factory()->rejected()->create([
            'karyawan_id'     => $this->karyawan->id,
            'jenis_cuti_id'   => $this->idIzin,
            'tanggal_mulai'   => $this->tanggal(5),
            'tanggal_selesai' => $this->tanggal(7),
            'jumlah_hari'     => 3,
        ]);

        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(5),
            'tanggal_selesai'=> $this->tanggal(7),
            'keterangan'     => 'Ajukan ulang setelah rejected',
        ])
            ->assertStatus(201);
    }

    public function test_overlap_tepat_di_batas_akhir_diblokir(): void
    {
        // Ada: 5–10, Baru: mulai 10 → menyentuh batas akhir = overlap
        CutiKaryawan::factory()->create([
            'karyawan_id'     => $this->karyawan->id,
            'jenis_cuti_id'   => $this->idIzin,
            'tanggal_mulai'   => $this->tanggal(5),
            'tanggal_selesai' => $this->tanggal(10),
            'jumlah_hari'     => 6,
            'status'          => LeaveConstants::STATUS_PENDING,
        ]);

        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(10),
            'tanggal_selesai'=> $this->tanggal(12),
            'keterangan'     => 'Mulai di batas akhir yang ada',
        ])->assertStatus(422);
    }

    public function test_overlap_tepat_setelah_batas_akhir_diizinkan(): void
    {
        // Ada: 5–10, Baru: mulai 11 → sehari setelah batas = tidak overlap
        CutiKaryawan::factory()->create([
            'karyawan_id'     => $this->karyawan->id,
            'jenis_cuti_id'   => $this->idIzin,
            'tanggal_mulai'   => $this->tanggal(5),
            'tanggal_selesai' => $this->tanggal(10),
            'jumlah_hari'     => 6,
            'status'          => LeaveConstants::STATUS_PENDING,
        ]);

        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(11),
            'tanggal_selesai'=> $this->tanggal(13),
            'keterangan'     => 'Setelah cuti pertama selesai',
        ])->assertStatus(201);
    }

    public function test_karyawan_berbeda_boleh_cuti_di_tanggal_sama(): void
    {
        $karyawanLain = Karyawan::factory()->create([
            'role'      => LeaveConstants::ROLE_KARYAWAN,
            'sisa_cuti' => 12,
        ]);

        CutiKaryawan::factory()->create([
            'karyawan_id'     => $karyawanLain->id,
            'jenis_cuti_id'   => $this->idIzin,
            'tanggal_mulai'   => $this->tanggal(5),
            'tanggal_selesai' => $this->tanggal(7),
            'jumlah_hari'     => 3,
            'status'          => LeaveConstants::STATUS_APPROVED,
        ]);

        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(5),
            'tanggal_selesai'=> $this->tanggal(7),
            'keterangan'     => 'Karyawan berbeda, tidak ada masalah',
        ])->assertStatus(201);
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 5 — Logging
    // ════════════════════════════════════════════════════════════════

    public function test_submit_berhasil_mencatat_log(): void
    {
        \Illuminate\Support\Facades\Log::spy();

        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'keterangan'     => 'Test log dicatat',
        ])->assertStatus(201);

        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn($message) => str_contains($message, 'Cuti diajukan'));
    }
}