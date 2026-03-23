<?php

namespace Database\Factories;

use App\Constants\LeaveConstants;
use App\Models\CutiKaryawan;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CutiKaryawan>
 */
class CutiKaryawanFactory extends Factory
{
    protected $model = CutiKaryawan::class;

    public function definition(): array
    {
        $mulai      = Carbon::instance($this->faker->dateTimeBetween('+1 days', '+30 days'));
        $selesai    = $mulai->copy()->addDays($this->faker->numberBetween(0, 5));

        $mulaiStr   = $mulai->format('Y-m-d');
        $selesaiStr = $selesai->format('Y-m-d');

        // Hitung inklusif — konsisten dengan CutiService::hitungHari()
        $jumlahHari = $mulai->diffInDays($selesai) + 1;

        return [
            'karyawan_id'     => Karyawan::factory(),
            'jenis_cuti_id'   => JenisCuti::factory(),
            'tanggal_mulai'   => $mulaiStr,
            'tanggal_selesai' => $selesaiStr,
            'jumlah_hari'     => $jumlahHari,
            'keterangan'      => $this->faker->optional()->sentence(),
            'dokumen_path'    => null,   // ← tambah kolom CR#001
            'status'          => LeaveConstants::STATUS_PENDING,
            'catatan_admin'   => null,
            'approved_by'     => null,
            'approved_at'     => null,
        ];
    }

    // ── STATES ────────────────────────────────────────────────────

    /**
     * State: status pending (eksplisit)
     */
    public function pending(): static
    {
        return $this->state(fn(array $attr) => [
            'status'        => LeaveConstants::STATUS_PENDING,
            'catatan_admin' => null,
            'approved_by'   => null,
            'approved_at'   => null,
        ]);
    }

    /**
     * State: status approved
     */
    public function approved(): static
    {
        return $this->state(fn(array $attr) => [
            'status'        => LeaveConstants::STATUS_APPROVED,
            'approved_by'   => Karyawan::factory()->admin(),
            'approved_at'   => now(),
            'catatan_admin' => null,
        ]);
    }

    /**
     * State: status rejected
     */
    public function rejected(): static
    {
        return $this->state(fn(array $attr) => [
            'status'        => LeaveConstants::STATUS_REJECTED,
            'approved_by'   => Karyawan::factory()->admin(),
            'approved_at'   => now(),
            'catatan_admin' => $this->faker->sentence(),
        ]);
    }

    /**
     * State: punya dokumen pendukung (CR#001)
     * Pakai path dummy — untuk test yang butuh dokumen_path terisi
     * tanpa benar-benar upload file.
     *
     * Usage: CutiKaryawan::factory()->denganDokumen()->create()
     */
    public function denganDokumen(string $namaFile = 'surat.pdf'): static
    {
        return $this->state(fn(array $attr) => [
            'dokumen_path' => LeaveConstants::DOKUMEN_STORAGE_FOLDER
                             . '/' . $attr['karyawan_id']
                             . '_dummy_' . $namaFile,
        ]);
    }

    /**
     * State: jenis cuti Cuti Tahunan (potong_jatah = true)
     * Berguna untuk test yang butuh pastikan sisa cuti berkurang.
     *
     * Usage: CutiKaryawan::factory()->cutiTahunan()->create()
     */
    public function cutiTahunan(): static
    {
        return $this->state(fn(array $attr) => [
            'jenis_cuti_id' => 1, // ID tetap dari JenisCutiSeeder
        ]);
    }

    /**
     * State: jenis cuti Izin (potong_jatah = false)
     *
     * Usage: CutiKaryawan::factory()->izin()->create()
     */
    public function izin(): static
    {
        return $this->state(fn(array $attr) => [
            'jenis_cuti_id' => 2,
        ]);
    }

    /**
     * State: jenis cuti Sakit (potong_jatah = false, wajib dokumen)
     *
     * Usage: CutiKaryawan::factory()->sakit()->create()
     * Atau  : CutiKaryawan::factory()->sakit()->denganDokumen('surat_sakit.pdf')->create()
     */
    public function sakit(): static
    {
        return $this->state(fn(array $attr) => [
            'jenis_cuti_id' => 3,
        ]);
    }

    /**
     * State: durasi cuti tetap N hari, tanggal mulai dari sekarang + offset.
     * Berguna untuk test overlap agar tanggal bisa dikontrol persis.
     *
     * Usage: CutiKaryawan::factory()->durasi(3, offsetMulai: 5)->create()
     *        → mulai hari ini+5, selesai hari ini+7
     */
    public function durasi(int $hari, int $offsetMulai = 1): static
    {
        $mulai   = now('Asia/Jakarta')->addDays($offsetMulai)->format('Y-m-d');
        $selesai = now('Asia/Jakarta')->addDays($offsetMulai + $hari - 1)->format('Y-m-d');

        return $this->state(fn(array $attr) => [
            'tanggal_mulai'   => $mulai,
            'tanggal_selesai' => $selesai,
            'jumlah_hari'     => $hari,
        ]);
    }

    /**
     * State: milik karyawan tertentu (tanpa buat karyawan baru)
     * Berguna agar factory tidak spawn karyawan baru yang tidak diinginkan.
     *
     * Usage: CutiKaryawan::factory()->untukKaryawan($karyawan)->create()
     */
    public function untukKaryawan(Karyawan $karyawan): static
    {
        return $this->state(fn(array $attr) => [
            'karyawan_id' => $karyawan->id,
        ]);
    }
}