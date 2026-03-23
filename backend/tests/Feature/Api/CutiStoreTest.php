<?php

namespace Tests\Feature\Api;

use App\Constants\LeaveConstants;
use App\Models\CutiKaryawan;
use App\Models\Karyawan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CutiStoreTest extends TestCase
{
    use RefreshDatabase;

    private Karyawan $karyawan;
    private Karyawan $karyawanCutiHabis;
    private string   $token;
    private string   $tokenCutiHabis;

    // Simpan sebagai property — ID dari seeder bisa saja tidak selalu 1,2,3
    // jika test lain ikut seed data sebelumnya
    private int $idCutiTahunan;
    private int $idIzin;
    private int $idSakit;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(\Database\Seeders\JenisCutiSeeder::class);

        // Ambil ID dari DB — lebih aman daripada hardcode
        $this->idCutiTahunan = \App\Models\JenisCuti::where('nama', 'Cuti Tahunan')->value('id');
        $this->idIzin        = \App\Models\JenisCuti::where('nama', 'Izin')->value('id');
        $this->idSakit       = \App\Models\JenisCuti::where('nama', 'Sakit')->value('id');

        $this->karyawan = Karyawan::factory()->create([
            'role'      => LeaveConstants::ROLE_KARYAWAN,
            'sisa_cuti' => 12,
        ]);
        $this->token = $this->karyawan
            ->createToken('test', [LeaveConstants::ROLE_KARYAWAN])
            ->plainTextToken;

        $this->karyawanCutiHabis = Karyawan::factory()->cutiHabis()->create([
            'role' => LeaveConstants::ROLE_KARYAWAN,
        ]);
        $this->tokenCutiHabis = $this->karyawanCutiHabis
            ->createToken('test', [LeaveConstants::ROLE_KARYAWAN])
            ->plainTextToken;
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function tanggal(int $offsetHari = 1): string
    {
        return now('Asia/Jakarta')->addDays($offsetHari)->format('Y-m-d');
    }

    /**
     * POST /api/cuti tanpa file (application/json)
     */
    private function postCuti(array $data, ?string $token = null): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token ?? $this->token)
                    ->postJson('/api/cuti', $data);
    }

    /**
     * POST /api/cuti dengan file (multipart/form-data)
     */
    private function postCutiDenganFile(array $data, ?string $token = null): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token ?? $this->token)
                    ->post('/api/cuti', $data);
    }

    /**
     * Buat fake PDF untuk test dokumen sakit
     */
    private function fakePdf(string $nama = 'surat.pdf', int $sizeKb = 500): UploadedFile
    {
        return UploadedFile::fake()->create($nama, $sizeKb, 'application/pdf');
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 1 — Happy Path
    // Semua test di sini disesuaikan dengan CR#001:
    // - Cuti Tahunan & Izin: wajib keterangan
    // - Sakit: wajib dokumen, keterangan opsional
    // ════════════════════════════════════════════════════════════════

    public function test_cuti_tahunan_berhasil_diajukan(): void
    {
        $response = $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(3),
            'keterangan'     => 'Liburan keluarga ke kampung', // CR#001: wajib
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'id',
                         'jenis_cuti',
                         'tanggal_mulai',
                         'tanggal_selesai',
                         'jumlah_hari',
                         'periode_label',
                         'keterangan',
                         'dokumen_url',
                         'dokumen_nama',
                         'status',
                         'status_label',
                         'sisa_cuti_setelah',
                         'sisa_cuti_label',
                         'created_at',
                     ],
                 ])
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.status', LeaveConstants::STATUS_PENDING)
                 ->assertJsonPath('data.jumlah_hari', 3)
                 ->assertJsonPath('data.sisa_cuti_setelah', 12) // belum berkurang
                 ->assertJsonPath('data.dokumen_url', null);    // tidak upload dokumen

        $this->assertDatabaseHas('cuti_karyawan', [
            'karyawan_id'   => $this->karyawan->id,
            'jenis_cuti_id' => $this->idCutiTahunan,
            'jumlah_hari'   => 3,
            'status'        => LeaveConstants::STATUS_PENDING,
        ]);
    }

    public function test_hitung_hari_satu_hari_tepat(): void
    {
        $tanggal = $this->tanggal(1);

        // Cuti Tahunan: keterangan wajib
        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $tanggal,
            'tanggal_selesai'=> $tanggal,
            'keterangan'     => 'Cuti satu hari saja', // CR#001: wajib
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.jumlah_hari', 1);
    }

    public function test_izin_berhasil_dengan_keterangan(): void
    {
        // CR#001: Izin wajib keterangan — test sebelumnya "tanpa keterangan" tidak valid lagi
        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'keterangan'     => 'Mengurus keperluan keluarga',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', LeaveConstants::STATUS_PENDING);
    }

    public function test_sakit_berhasil_dengan_dokumen(): void
    {
        // CR#001: Sakit wajib dokumen
        $this->postCutiDenganFile([
            'jenis_cuti_id'  => $this->idSakit,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(2),
            'keterangan'     => 'Demam tinggi',
            'dokumen'        => $this->fakePdf(),
        ])->assertStatus(201);
    }

    public function test_sakit_berhasil_tanpa_keterangan_asal_ada_dokumen(): void
    {
        // CR#001: Sakit — keterangan opsional, dokumen wajib
        $this->postCutiDenganFile([
            'jenis_cuti_id'  => $this->idSakit,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'dokumen'        => $this->fakePdf(),
            // keterangan tidak diisi — opsional untuk sakit
        ])->assertStatus(201);
    }

    public function test_karyawan_sisa_nol_boleh_izin(): void
    {
        // BR-02: Izin tidak potong jatah — boleh meski sisa=0
        // CR#001: Izin wajib keterangan
        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'keterangan'     => 'Keperluan mendadak',
        ], $this->tokenCutiHabis)
            ->assertStatus(201);
    }

    public function test_karyawan_sisa_nol_boleh_sakit(): void
    {
        // BR-02: Sakit tidak potong jatah — boleh meski sisa=0
        // CR#001: Sakit wajib dokumen
        $this->postCutiDenganFile([
            'jenis_cuti_id'  => $this->idSakit,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'dokumen'        => $this->fakePdf(),
        ], $this->tokenCutiHabis)
            ->assertStatus(201);
    }

    public function test_status_awal_selalu_pending(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'keterangan'     => 'Cuti satu hari',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', LeaveConstants::STATUS_PENDING);

        // BR-06: sisa cuti tidak berkurang saat submit
        $this->assertDatabaseHas('karyawan', [
            'id'        => $this->karyawan->id,
            'sisa_cuti' => 12,
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 2 — Validasi Tanggal
    // Test ini tidak perlu field tambahan karena validasi tanggal
    // terjadi SEBELUM validasi keterangan/dokumen di FormRequest
    // ════════════════════════════════════════════════════════════════

    public function test_tanggal_mulai_kemarin_ditolak(): void
    {
        $kemarin = now('Asia/Jakarta')->subDay()->format('Y-m-d');

        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $kemarin,
            'tanggal_selesai'=> $kemarin,
            'keterangan'     => 'Cuti kemarin',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tanggal_mulai'])
            ->assertJsonPath('success', false);
    }

    public function test_tanggal_selesai_sebelum_mulai_ditolak(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(5),
            'tanggal_selesai'=> $this->tanggal(3),
            'keterangan'     => 'Tanggal terbalik',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tanggal_selesai']);
    }

    public function test_format_tanggal_salah_ditolak(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => '07-04-2026', // format salah
            'tanggal_selesai'=> '09-04-2026',
            'keterangan'     => 'Format salah',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tanggal_mulai']);
    }

    public function test_durasi_melebihi_kuota_tahunan_ditolak(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(13), // 13 hari > ANNUAL_QUOTA (12)
            'keterangan'     => 'Durasi terlalu panjang',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tanggal_selesai']);
    }

    public function test_durasi_tepat_kuota_maksimum_diterima(): void
    {
        // Tepat 12 hari = ANNUAL_QUOTA — harus diterima
        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(12),
            'keterangan'     => 'Cuti tepat 12 hari',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.jumlah_hari', 12);
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 3 — Validasi Sisa Cuti
    // ════════════════════════════════════════════════════════════════

    public function test_sisa_cuti_nol_cuti_tahunan_ditolak(): void
    {
        // BR-03: sisa=0 + potong_jatah=true → ditolak
        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'keterangan'     => 'Coba cuti padahal habis',
        ], $this->tokenCutiHabis)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_sisa_cuti_kurang_dari_durasi_ditolak(): void
    {
        $karyawan = Karyawan::factory()->create([
            'role'      => LeaveConstants::ROLE_KARYAWAN,
            'sisa_cuti' => 2,
        ]);
        $token = $karyawan->createToken('test', ['karyawan'])->plainTextToken;

        // Coba ajukan 5 hari Cuti Tahunan, sisa hanya 2
        $this->withToken($token)->postJson('/api/cuti', [
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(5),
            'keterangan'     => 'Kurang sisa cuti',
        ])->assertStatus(422);
    }

    public function test_sisa_cuti_tepat_sama_dengan_durasi_diterima(): void
    {
        // Sisa=3, ajukan 3 hari → pas, harus diterima
        $karyawan = Karyawan::factory()->create([
            'role'      => LeaveConstants::ROLE_KARYAWAN,
            'sisa_cuti' => 3,
        ]);
        $token = $karyawan->createToken('test', ['karyawan'])->plainTextToken;

        $this->withToken($token)->postJson('/api/cuti', [
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(3),
            'keterangan'     => 'Pas dengan sisa cuti',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.jumlah_hari', 3);
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 4 — Validasi Field Wajib & Format
    // ════════════════════════════════════════════════════════════════

    public function test_jenis_cuti_tidak_valid_ditolak(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => 9999,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'keterangan'     => 'Jenis cuti tidak ada',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['jenis_cuti_id']);
    }

    public function test_field_wajib_kosong_ditolak(): void
    {
        // Semua field kosong — minimal 3 error field wajib
        $this->postCuti([])
             ->assertStatus(422)
             ->assertJsonValidationErrors([
                 'jenis_cuti_id',
                 'tanggal_mulai',
                 'tanggal_selesai',
             ]);
    }

    public function test_keterangan_terlalu_panjang_ditolak(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'keterangan'     => str_repeat('a', 501), // 501 > max:500
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['keterangan']);
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 5 — Autentikasi
    // ════════════════════════════════════════════════════════════════

    public function test_tanpa_token_return_401(): void
    {
        $this->postJson('/api/cuti', [
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(3),
            'keterangan'     => 'Tanpa token',
        ])->assertStatus(401);
    }

    public function test_token_tidak_valid_return_401(): void
    {
        $this->withToken('token_palsu_tidak_valid')
             ->postJson('/api/cuti', [
                 'jenis_cuti_id'  => $this->idCutiTahunan,
                 'tanggal_mulai'  => $this->tanggal(1),
                 'tanggal_selesai'=> $this->tanggal(3),
                 'keterangan'     => 'Token palsu',
             ])->assertStatus(401);
    }

    // ════════════════════════════════════════════════════════════════
    // BAGIAN 6 — Integritas Data
    // ════════════════════════════════════════════════════════════════

    public function test_sisa_cuti_tidak_berkurang_saat_submit(): void
    {
        $sisaAwal = $this->karyawan->sisa_cuti; // 12

        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(3),
            'keterangan'     => 'Cuti keluarga',
        ])->assertStatus(201);

        // BR-06: sisa cuti TIDAK berkurang saat submit
        $this->assertDatabaseHas('karyawan', [
            'id'        => $this->karyawan->id,
            'sisa_cuti' => $sisaAwal,
        ]);
    }

    public function test_hanya_satu_record_masuk_ke_db(): void
    {
        $jumlahSebelum = CutiKaryawan::count();

        // Izin: wajib keterangan, tidak wajib dokumen
        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'keterangan'     => 'Izin satu hari',
        ])->assertStatus(201);

        $this->assertDatabaseCount('cuti_karyawan', $jumlahSebelum + 1);
    }

    public function test_karyawan_id_otomatis_dari_token(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'keterangan'     => 'Test karyawan id',
        ])->assertStatus(201);

        // karyawan_id harus dari token, bukan bisa di-inject dari request body
        $this->assertDatabaseHas('cuti_karyawan', [
            'karyawan_id' => $this->karyawan->id,
        ]);
    }

    public function test_keterangan_tersimpan_di_db(): void
    {
        $keterangan = 'Keterangan spesifik yang harus tersimpan';

        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'keterangan'     => $keterangan,
        ])->assertStatus(201);

        $this->assertDatabaseHas('cuti_karyawan', [
            'karyawan_id' => $this->karyawan->id,
            'keterangan'  => $keterangan,
        ]);
    }

    public function test_dokumen_path_null_jika_tidak_upload(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'keterangan'     => 'Tidak upload dokumen',
        ])->assertStatus(201);

        $cuti = CutiKaryawan::latest()->first();
        $this->assertNull($cuti->dokumen_path);
    }
}