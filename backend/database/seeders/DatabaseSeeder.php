<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Urutan penting! Jenis_cuti dulu sebelum karyawan
        // (kalau ada FK ke jenis_cuti di seeder lain)
        $this->call([
            JenisCutiSeeder::class,
            KaryawanSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('Semua seeder telah dijalankan!');
    }
}
