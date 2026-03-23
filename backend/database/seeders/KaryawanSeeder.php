<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Karyawan;

class KaryawanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $karyawanList = [
            // ── ADMIN ─────────────────────────────────────────────        
            [
                'nik'        => 'HRD001',
                'nama'       => 'Budi Santoso',
                'departemen' => null,  // ← admin murni, bukan departemen produksi
                'role'       => 'admin',
                'email'      => 'admin@company.id',
                'password'   => Hash::make('admin123'),
                'sisa_cuti'  => 0,  // admin tidak memiliki cuti
            ],

            // ── DEPARTEMEN SEWING ─────────────────────────────────────────
            [
                'nik'        => 'SEW001',
                'nama'       => 'Siti Aminah',
                'departemen' => 'Sewing',
                'role'       => 'karyawan',
                'email'      => 'siti@company.id',
                'password'   => Hash::make('karyawan123'),
                'sisa_cuti'  => 12,
            ],
            [
                'nik'        => 'SEW002',
                'nama'       => 'Dewi Rahayu',
                'departemen' => 'Sewing',
                'role'       => 'karyawan',
                'email'      => 'dewi@company.id',
                'password'   => Hash::make('karyawan123'),
                'sisa_cuti'  => 8,  // simulasi sudah pakai 4 hari
            ],

            // ── DEPARTEMEN CUTTING ────────────────────────────────
            [
                'nik'        => 'CUT001',
                'nama'       => 'Ahmad Fauzi',
                'departemen' => 'Cutting',
                'role'       => 'karyawan',
                'email'      => 'ahmad@company.id',
                'password'   => Hash::make('karyawan123'),
                'sisa_cuti'  => 12,
            ],
            [
                'nik'        => 'CUT002',
                'nama'       => 'Rudi Hartono',
                'departemen' => 'Cutting',
                'role'       => 'karyawan',
                'email'      => 'rudi@company.id',
                'password'   => Hash::make('karyawan123'),
                'sisa_cuti'  => 0,  // simulasi sisa cuti habis
            ],

            // ── DEPARTEMEN FINISHING ──────────────────────────────
            [
                'nik'        => 'FIN001',
                'nama'       => 'Rina Wulandari',
                'departemen' => 'Finishing',
                'role'       => 'karyawan',
                'email'      => 'rina@company.id',
                'password'   => Hash::make('karyawan123'),
                'sisa_cuti'  => 5,
            ],

            // ── DEPARTEMEN QA ─────────────────────────────────────
            [
                'nik'        => 'QA001',
                'nama'       => 'Hendra Gunawan',
                'departemen' => 'QA',
                'role'       => 'karyawan',
                'email'      => 'hendra@company.id',
                'password'   => Hash::make('karyawan123'),
                'sisa_cuti'  => 12,
            ],
        ];

        foreach ($karyawanList as $data) {
            Karyawan::updateOrCreate(
                ['nik' => $data['nik']],
                $data
            );
        }

        $this->command->info('✅ Karyawan selesai di-seed: ' . count($karyawanList) . ' data.');
        $this->command->table(
            ['NIK', 'Nama', 'Departemen', 'Role', 'Email', 'Sisa Cuti'],
            collect($karyawanList)->map(fn($k) => [
                $k['nik'], $k['nama'], $k['departemen'],
                $k['role'], $k['email'], $k['sisa_cuti']
            ])->toArray()
        );
    }
}
