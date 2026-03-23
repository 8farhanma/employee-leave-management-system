<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cuti_karyawan', function (Blueprint $table) {
            // Path file dokumen relatif dari storage/app/public
            // Nullable karena tidak semua jenis cuti wajib upload
            $table->string('dokumen_path', 255)
                ->nullable()
                ->after('keterangan')
                ->comment('Path file dokumen pendukung (PDF/JPG/PNG)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuti_karyawan', function (Blueprint $table) {
            $table->dropColumn('dokumen_path');
        });
    }
};
