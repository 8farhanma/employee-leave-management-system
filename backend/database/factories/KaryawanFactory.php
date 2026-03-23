<?php

namespace Database\Factories;

use App\Models\Karyawan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class KaryawanFactory extends Factory
{
    protected $model = Karyawan::class;

    // Departemen yang valid
    private array $departemen = ['Sewing', 'Cutting', 'Finishing', 'QA'];

    public function definition(): array
    {
        $dept = $this->faker->randomElement(\App\Constants\LeaveConstants::DEPARTMENTS);
        $prefix = strtoupper(substr($dept, 0, 3));

        return [
            'nik'           => $prefix . $this->faker->unique()->numerify('###'),
            'nama'          => $this->faker->name(),
            'departemen'    => $dept,
            'role'          => 'karyawan',
            'email'         => $this->faker->unique()->safeEmail(),
            'password'      => Hash::make('karyawan123'),
            'sisa_cuti'     => 12,
        ];
    }

    /**
     * State: karyawan dengan role admin
     * Usage: karyawan::factory()->admin()->create()
     */
    public function admin(): static
    {
        return $this->state(fn(array $attributes) => [
            'nik'  => 'ADM' . $this->faker->unique()->numerify('###'),
            'role' => 'admin',
        ]);
    }
    
    /**
     * State: karyawan dengan sisa cuti habis
     * Usage: Karyawan::factory()->cutiHabis()->create()
     */
    public function cutiHabis(): static
    {
        return $this->state(fn(array $attributes) => [
            'sisa_cuti' => 0,
        ]);
    }

    /**
     * State: karyawan dari departemen tertentu
     * Usage: Karyawan::factory()->departemen('Sewing')->create()
     */
    public function departemen(string $dept): static
    {
        return $this->state(fn(array $attributes) => [
            'departemen' => $dept,
            'nik'        => strtoupper(substr($dept, 0, 3))
                            . $this->faker->unique()->numerify('###'),
        ]);
    }
}
