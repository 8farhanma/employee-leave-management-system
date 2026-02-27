<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JenisCuti;

class JenisCutiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jenisCuti = [
            [
                'nama' => 'Cuti Tahunan',
                'potong_jatah' => true,
                'keterangan' => 'Cuti rutin tahunan karyawan. Memotong jatah 12 hari per tahun.',
            ],
            [
                'nama' => 'Izin',
                'potong_jatah' => false,
                'keterangan' => 'Izin keperluan mendadak atau pribadi. Tidak memotong jatah cuti tahunan.',
            ],
            [
                'nama' => 'Sakit',
                'potong_jatah' => false,
                'keterangan' => 'Cuti karena sakit. Tidak memotong jatah cuti tahunan, tetapi memerlukan surat dokter.',
            ],
        ];

        foreach ($jenisCuti as $item) {
            // updateOrCreate agar aman dijalankan berulang kali
            JenisCuti::updateOrCreate(
                ['nama' => $item['nama']],
                $item
            );
        }

        $this->command->info('✅ Jenis cuti selesai di-seed: ' . count($jenisCuti) . ' data.');
    }
}
