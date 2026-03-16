<?php

namespace Tests\Feature\Api;

use App\Constants\LeaveConstants;
use App\Models\CutiKaryawan;
use App\Models\Karyawan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CutiStoreTest extends TestCase
{
    use RefreshDatabase;

    private Karyawan $karyawan;
    private Karyawan $karyawanCutiHabis;
    private string   $token;
    private string   $tokenCutiHabis;

    // ID jenis cuti dari seeder
    private int $idCutiTahunan;
    private int $idIzin;
    private int $idSakit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\JenisCutiSeeder::class);

        $this->idCutiTahunan    = \App\Models\JenisCuti::where('nama', 'Cuti Tahunan')->value('id');
        $this->idIzin           = \App\Models\JenisCuti::where('nama', 'Izin')->value('id');
        $this->idSakit          = \App\Models\JenisCuti::where('nama', 'Sakit')->value('id');

        // Karyawan normal, sisa cuti = 12
        $this->karyawan = Karyawan::factory()->create(['sisa_cuti' => 12]);
        $this->token = $this->karyawan
            ->createToken('test', [LeaveConstants::ROLE_KARYAWAN])
            ->plainTextToken;

        // Karyawan edge case, sisa cuti = 0
        $this->karyawanCutiHabis = Karyawan::factory()->cutiHabis()->create();
        $this->tokenCutiHabis = $this->karyawanCutiHabis
            ->createToken('test', [LeaveConstants::ROLE_KARYAWAN])
            ->plainTextToken;
    }

    // ────────────────────────────────────────────────────────────────
    // HELPER
    // ────────────────────────────────────────────────────────────────
    
    /**
     * Tanggal yang valid untuk test (hari ini + offset hari)
     */
    private function tanggal(int $offsetHari = 1): string
    {
        return now('Asia/Jakarta')->addDays($offsetHari)->format('Y-m-d');
    }

    private function postCuti(array $data, ?string $token = null): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token ?? $this->token)
                    ->postJson('/api/cuti', $data);
    }

    // ────────────────────────────────────────────────────────────────
    // HAPPY PATH
    // ────────────────────────────────────────────────────────────────
    public function test_cuti_tahunan_berhasil_disubmit(): void
    {
        $response = $this->postCuti([
            'jenis_cuti_id'     => $this->idCutiTahunan,
            'tanggal_mulai'     => $this->tanggal(1),
            'tanggal_selesai'   => $this->tanggal(3),
            'keterangan'        => 'Liburan Keluarga',
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
                        'status',
                        'status_label',
                        'sisa_cuti_setelah',
                        'sisa_cuti_label',
                        'created_at',
                    ]
                ])
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.status', 'pending')
                ->assertJsonPath('data.jumlah_hari', 3)
                // Sisa cuti belum berkurang - hanya berkurang saat approve
                ->assertJsonPath('data.sisa_cuti_setelah', 12);

        // Pastikan data masuk ke database
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

        $response = $this->postCuti([
            'jenis_cuti_id'     => $this->idCutiTahunan,
            'tanggal_mulai'     => $tanggal,
            'tanggal_selesai'   => $tanggal, // tanggal sama = 1 hari
        ]);

        $response->assertStatus(201)
                ->assertJsonPath('data.jumlah_hari', 1);
    }
    
    public function test_izin_berhasil_tanpa_keterangan(): void
    {
        // Keterangan opsional untuk izin
        $response = $this->postCuti([
            'jenis_cuti_id'     => $this->idIzin,
            'tanggal_mulai'     => $this->tanggal(1),
            'tanggal_selesai'   => $this->tanggal(1), 
            // tidak ada keterangan
        ]);

        $response->assertStatus(201)
                ->assertJsonPath('data.status', 'pending');
    }

    public function test_sakit_berhasil_disubmit(): void
    {
        $response = $this->postCuti([
            'jenis_cuti_id'  => $this->idSakit,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(2),
            'keterangan'     => 'Demam tinggi',
        ]);

        $response->assertStatus(201);
    }

    public function test_karyawan_sisa_nol_boleh_izin(): void
    {
        // BR-02: Izin tidak potong jatah — boleh meski sisa=0
        $response = $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
            'keterangan'     => 'Keperluan mendadak',
        ], $this->tokenCutiHabis);

        $response->assertStatus(201);
    }

    public function test_karyawan_sisa_nol_boleh_sakit(): void
    {
        $response = $this->postCuti([
            'jenis_cuti_id'  => $this->idSakit,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
        ], $this->tokenCutiHabis);

        $response->assertStatus(201);
    }

    public function test_status_awal_selalu_pending(): void
    {
        $response = $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.status', LeaveConstants::STATUS_PENDING);

        // Sisa cuti di DB tidak berkurang saat submit
        $this->assertDatabaseHas('karyawan', [
            'id'        => $this->karyawan->id,
            'sisa_cuti' => 12, // masih 12, belum berkurang
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // VALIDASI TANGGAL
    // ────────────────────────────────────────────────────────────────
    public function test_tanggal_mulai_kemarin_ditolak(): void
    {
        $kemarin = now('Asia/Jakarta')->subDay()->format('Y-m-d');

        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $kemarin,
            'tanggal_selesai'=> $kemarin,
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['tanggal_mulai'])
          ->assertJsonPath('success', false);
    }

    public function test_tanggal_selesai_sebelum_mulai_ditolak(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(5),
            'tanggal_selesai'=> $this->tanggal(3), // lebih awal dari mulai
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['tanggal_selesai']);
    }

    public function test_format_tanggal_salah_ditolak(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => '07-04-2026', // format salah
            'tanggal_selesai'=> '09-04-2026',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['tanggal_mulai']);
    }

    public function test_durasi_melebihi_kuota_tahunan_ditolak(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            // 13 hari = lebih dari ANNUAL_QUOTA (12)
            'tanggal_selesai'=> $this->tanggal(13),
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['tanggal_selesai']);
    }

    public function test_durasi_tepat_kuota_maksimum_diterima(): void
    {
        // Tepat 12 hari = boleh
        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(12),
        ])->assertStatus(201)
          ->assertJsonPath('data.jumlah_hari', 12);
    }

    // ────────────────────────────────────────────────────────────────
    // VALIDASI SISA CUTI
    // ────────────────────────────────────────────────────────────────
    public function test_sisa_cuti_nol_cuti_tahunan_ditolak(): void
    {
        // BR-03: sisa=0 + potong_jatah=true → ditolak
        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
        ], $this->tokenCutiHabis)
          ->assertStatus(422)
          ->assertJsonPath('success', false);
    }

    public function test_sisa_cuti_kurang_dari_durasi_ditolak(): void
    {
        // Buat karyawan dengan sisa cuti = 2
        $karyawan = Karyawan::factory()->create(['sisa_cuti' => 2]);
        $token = $karyawan->createToken('test', ['karyawan'])->plainTextToken;

        // Coba ajukan 5 hari Cuti Tahunan
        $this->withToken($token)->postJson('/api/cuti', [
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(5),
        ])->assertStatus(422)
          ->assertJsonPath('success', false);
    }

    public function test_sisa_cuti_tepat_sama_dengan_durasi_diterima(): void
    {
        // Sisa=3, ajukan 3 hari → pas, harus diterima
        $karyawan = Karyawan::factory()->create(['sisa_cuti' => 3]);
        $token = $karyawan->createToken('test', ['karyawan'])->plainTextToken;

        $this->withToken($token)->postJson('/api/cuti', [
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(3),
        ])->assertStatus(201)
          ->assertJsonPath('data.jumlah_hari', 3);
    }

    // ────────────────────────────────────────────────────────────────
    // VALIDASI FIELD
    // ────────────────────────────────────────────────────────────────
    public function test_jenis_cuti_tidak_valid_ditolak(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => 9999,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['jenis_cuti_id']);
    }

    public function test_field_wajib_kosong_ditolak(): void
    {
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
            'keterangan'     => str_repeat('a', 501), // 501 karakter
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['keterangan']);
    }

    // ────────────────────────────────────────────────────────────────
    // AUTENTIKASI
    // ────────────────────────────────────────────────────────────────
    public function test_tanpa_token_return_401(): void
    {
        $this->postJson('/api/cuti', [
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(3),
        ])->assertStatus(401);
    }

    public function test_token_tidak_valid_return_401(): void
    {
        $this->withToken('token_palsu_tidak_valid')
             ->postJson('/api/cuti', [
                 'jenis_cuti_id'  => $this->idCutiTahunan,
                 'tanggal_mulai'  => $this->tanggal(1),
                 'tanggal_selesai'=> $this->tanggal(3),
             ])->assertStatus(401);
    }

    // ────────────────────────────────────────────────────────────────
    // INTEGRITAS DATA
    // ────────────────────────────────────────────────────────────────
    public function test_sisa_cuti_tidak_berkurang_saat_submit(): void
    {
        $sisaAwal = $this->karyawan->sisa_cuti; // 12

        $this->postCuti([
            'jenis_cuti_id'  => $this->idCutiTahunan,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(3),
        ])->assertStatus(201);

        // BR-06: sisa cuti TIDAK berkurang saat submit
        $this->assertDatabaseHas('karyawan', [
            'id'        => $this->karyawan->id,
            'sisa_cuti' => $sisaAwal, // masih sama
        ]);
    }

    public function test_hanya_satu_record_masuk_ke_db(): void
    {
        $jumlahSebelum = CutiKaryawan::count();

        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
        ])->assertStatus(201);

        $this->assertDatabaseCount('cuti_karyawan', $jumlahSebelum + 1);
    }

    public function test_data_karyawan_id_otomatis_dari_token(): void
    {
        $this->postCuti([
            'jenis_cuti_id'  => $this->idIzin,
            'tanggal_mulai'  => $this->tanggal(1),
            'tanggal_selesai'=> $this->tanggal(1),
        ])->assertStatus(201);

        // karyawan_id harus dari token, bukan dari request body
        $this->assertDatabaseHas('cuti_karyawan', [
            'karyawan_id' => $this->karyawan->id,
        ]);
    }
}
