<?php

namespace Database\Factories;

use App\Models\JenisCuti;
use Illuminate\Database\Eloquent\Factories\Factory;

class JenisCutiFactory extends Factory
{
    protected $model = JenisCuti::class;

    public function definition(): array
    {
        return [
            'nama'         => $this->faker->unique()->word(),
            'potong_jatah' => false,
            'keterangan'   => $this->faker->sentence(),
        ];
    }

    public function potongJatah(): static
    {
        return $this->state(fn(array $attr) => [
            'potong_jatah' => true,
        ]);
    }
}
