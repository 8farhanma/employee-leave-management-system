<?php

namespace Tests\Feature\Api;

use App\Models\Karyawan;
use App\Models\JenisCuti;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JenisCutiControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Karyawan $karyawan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\JenisCutiSeeder::class);
        $this->karyawan = Karyawan::factory()->create();
    }

    private function asKaryawan(): static
    {
        return $this->actingAs($this->karyawan, "sanctum");
    }

    public function test_list_jenis_cuti_berhasil(): void
    {
        $this->asKaryawan()
             ->getJson("/api/jenis-cuti")
             ->assertStatus(200)
             ->assertJsonStructure([
                 "success",
                 "data" => ["*" => ["id", "nama", "potong_jatah", "keterangan"]],
                 "meta" => ["total", "potong_jatah", "tidak_potong"],
             ])
             ->assertJsonPath("meta.total", 3)
             ->assertJsonPath("meta.potong_jatah", 1)
             ->assertJsonPath("meta.tidak_potong", 2);
    }

    public function test_filter_potong_jatah_true(): void
    {
        $this->asKaryawan()
             ->getJson("/api/jenis-cuti?potong_jatah=true")
             ->assertStatus(200)
             ->assertJsonCount(1, "data")
             ->assertJsonPath("data.0.nama", "Cuti Tahunan");
    }

    public function test_filter_potong_jatah_false(): void
    {
        $this->asKaryawan()
             ->getJson("/api/jenis-cuti?potong_jatah=false")
             ->assertStatus(200)
             ->assertJsonCount(2, "data");
    }

    public function test_show_jenis_cuti_berhasil(): void
    {
        $jenisCuti = JenisCuti::first();
        $this->asKaryawan()
             ->getJson("/api/jenis-cuti/{$jenisCuti->id}")
             ->assertStatus(200)
             ->assertJsonPath("data.id", $jenisCuti->id)
             ->assertJsonPath("data.nama", $jenisCuti->nama);
    }

    public function test_show_tidak_ada_return_404(): void
    {
        $this->asKaryawan()
             ->getJson("/api/jenis-cuti/9999")
             ->assertStatus(404);
    }

    public function test_akses_tanpa_token_return_401(): void
    {
        $this->withHeaders(["Accept" => "application/json"])
             ->get("/api/jenis-cuti")
             ->assertStatus(401);
    }
}
