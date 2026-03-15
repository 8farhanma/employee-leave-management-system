<?php

namespace Database\Factories;

use App\Models\CutiKaryawan;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CutiKaryawan>
 */
class CutiKaryawanFactory extends Factory
{
    protected $model = CutiKaryawan::class;

    public function definition(): array
    {
        $mulai   = $this->faker->dateTimeBetween('+1 days', '+30 days');
        $selesai = $this->faker->dateTimeBetween($mulai, '+40 days');

        $mulaiStr   = $mulai->format('Y-m-d');
        $selesaiStr = $selesai->format('Y-m-d');
        $jumlahHari = (int) (new \DateTime($mulaiStr))
                            ->diff(new \DateTime($selesaiStr))
                            ->days + 1;

        return [
            'karyawan_id'     => Karyawan::factory(),
            'jenis_cuti_id'   => JenisCuti::factory(),
            'tanggal_mulai'   => $mulaiStr,
            'tanggal_selesai' => $selesaiStr,
            'jumlah_hari'     => $jumlahHari,
            'keterangan'      => $this->faker->optional()->sentence(),
            'status'          => 'pending',
            'catatan_admin'   => null,
            'approved_by'     => null,
            'approved_at'     => null,
        ];
    }

    /**
     * State: status pending
     */
    public function pending(): static
    {
        return $this->state(fn(array $attr) => [
            'status'       => 'pending',
            'approved_by'  => null,
            'approved_at'  => null,
            'catatan_admin'=> null,
        ]);
    }

    /**
     * State: status approved
     */
    public function approved(): static
    {
        return $this->state(fn(array $attr) => [
            'status'       => 'approved',
            'approved_by'  => Karyawan::factory()->admin(),
            'approved_at'  => now(),
        ]);
    }

    /**
     * State: status rejected
     */
    public function rejected(): static
    {
        return $this->state(fn(array $attr) => [
            'status'        => 'rejected',
            'approved_by'   => Karyawan::factory()->admin(),
            'approved_at'   => now(),
            'catatan_admin' => 'Ditolak.',
        ]);
    }
}
