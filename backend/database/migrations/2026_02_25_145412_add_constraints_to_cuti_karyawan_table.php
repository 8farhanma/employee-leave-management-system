<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Pastikan tanggal_selesai >= tanggal_mulai di level DB
        // (sebagai safety net di luar validasi laravel)
        DB::statement('
            ALTER TABLE cuti_karyawan
            ADD CONSTRAINT chk_tanggal
            CHECK (tanggal_selesai >= tanggal_mulai)
        ');

        // Pastikan jumlah_hari minimal 1
        DB::statement('
            ALTER TABLE cuti_karyawan
            ADD CONSTRAINT chk_jumlah_hari
            CHECK (jumlah_hari >= 1)
        ');

        // Pastikan sisa_cuti tidak pernah negatif
        DB::statement('
            ALTER TABLE karyawan
            ADD CONSTRAINT chk_sisa_cuti
            CHECK (sisa_cuti >= 0)
            ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE cuti_karyawan DROP CONSTRAINT chk_tanggal');
        DB::statement('ALTER TABLE cuti_karyawan DROP CONSTRAINT chk_jumlah_hari');
        DB::statement('ALTER TABLE karyawan DROP CONSTRAINT chk_sisa_cuti');
    }
};
