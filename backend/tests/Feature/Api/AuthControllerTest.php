<?php

namespace Tests\Feature\Api;

use App\Models\Karyawan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Karyawan $admin;
    protected Karyawan $karyawan;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed jenis cuti dulu (data master)
        $this->seed(\Database\Seeders\JenisCutiSeeder::class);

        // Buat admin dan karyawan untuk test
        $this->admin = Karyawan::factory()->admin()->create();
        $this->karyawan = Karyawan::factory()->create();
    }

    // ── LOGIN ────────────────────────────────────────────────────

    public function test_login_dengan_email_berhasil(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'login'    => $this->karyawan->email,
            'password' => 'karyawan123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'token',
                         'token_type',
                         'karyawan' => [
                             'id', 'nik', 'nama', 'email',
                             'departemen', 'role', 'sisa_cuti',
                         ],
                     ],
                 ])
                 ->assertJson([
                     'success' => true,
                     'data'    => [
                         'token_type' => 'Bearer',
                     ],
                 ]);
    }

    public function test_login_dengan_nik_berhasil(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'login'    => $this->karyawan->nik,
            'password' => 'karyawan123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);
    }

    public function test_login_password_salah_gagal(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'login'    => $this->karyawan->email,
            'password' => 'passwordsalah',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('success', false)
                 ->assertJsonValidationErrors(['login']);
    }

    public function test_login_tanpa_field_gagal(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['login', 'password']);
    }

    // ── LOGOUT ───────────────────────────────────────────────────

    public function test_logout_berhasil(): void
    {
        $token = $this->karyawan->createToken('test')->plainTextToken;

        $this->withToken($token)
         ->postJson('/api/auth/logout')
         ->assertStatus(200)
         ->assertJsonPath('success', true);

        // Refresh application agar guard tidak pakai cached user
        $this->refreshApplication();

        // Token tidak bisa dipakai lagi
        $this->withToken($token)
             ->getJson('/api/me')
             ->assertStatus(401);
    }

    public function test_logout_tanpa_token_gagal(): void
    {
        $this->postJson('/api/auth/logout')
             ->assertStatus(401);
    }

    // ── ME ───────────────────────────────────────────────────────

    public function test_me_return_profil_dan_statistik(): void
    {
        $token = $this->karyawan->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/me');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'profil' => [
                             'id', 'nik', 'nama', 'email',
                             'departemen', 'sisa_cuti',
                         ],
                         'statistik' => [
                             'total_pengajuan',
                             'total_pending',
                             'total_approved',
                             'total_rejected',
                             'sisa_cuti',
                             'jatah_tahunan',
                             'cuti_terpakai',
                         ],
                     ],
                 ]);
    }

    public function test_me_tanpa_token_return_401(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    // ── REGISTER ─────────────────────────────────────────────────

    public function test_admin_bisa_register_karyawan_baru(): void
    {
        $token = $this->admin->createToken('admin', ['admin', 'karyawan'])
                             ->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/admin/karyawan/register', [
            'nik'                  => 'SEW099',
            'nama'                 => 'Karyawan Baru',
            'departemen'           => 'Sewing',
            'email'                => 'baru@uwujump.id',
            'password'             => 'Password123',
            'password_confirmation'=> 'Password123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.nik', 'SEW099')
                 ->assertJsonPath('data.sisa_cuti', 12);

        $this->assertDatabaseHas('karyawan', [
            'nik'   => 'SEW099',
            'email' => 'baru@uwujump.id',
            'role'  => 'karyawan',
        ]);
    }

    public function test_karyawan_biasa_tidak_bisa_register(): void
    {
        $token = $this->karyawan->createToken('test', ['karyawan'])
                                ->plainTextToken;

        $this->withToken($token)->postJson('/api/admin/karyawan/register', [
            'nik'                  => 'SEW099',
            'nama'                 => 'Coba Daftar',
            'departemen'           => 'Sewing',
            'email'                => 'coba@uwujump.id',
            'password'             => 'Password123',
            'password_confirmation'=> 'Password123',
        ])->assertStatus(403);
    }

    public function test_register_nik_duplikat_gagal(): void
    {
        $token = $this->admin->createToken('admin', ['admin', 'karyawan'])
                             ->plainTextToken;

        $this->withToken($token)->postJson('/api/admin/karyawan/register', [
            'nik'                  => $this->karyawan->nik, // NIK sudah ada
            'nama'                 => 'Duplikat',
            'departemen'           => 'QA',
            'email'                => 'duplikat@uwujump.id',
            'password'             => 'Password123',
            'password_confirmation'=> 'Password123',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['nik']);
    }
}