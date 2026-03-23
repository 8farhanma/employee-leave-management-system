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
        Schema::create('cuti_karyawan', function (Blueprint $table) {
            $table->id();

            // FK ke pemilik pengajuan
            $table->foreignId('karyawan_id')
                ->constrained('karyawan')
                ->cascadeOnDelete();

            // FK ke jenis cuti
            $table->foreignId('jenis_cuti_id')
                ->constrained('jenis_cuti')
                ->restrictOnDelete();

            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');

            // Dihitung otomatis oleh CutiService, bukan input user
            $table->unsignedTinyInteger('jumlah_hari');

            $table->text('keterangan')->nullable()
                ->comment('Alasan pengajuan dari karyawan');
                
            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending');

            $table->text('catatan_admin')->nullable()
                ->comment('Catatan dari admin saat approve/reject');

            // FK ke admin yang memproses (nullable, diisi saat approve/reject)
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('karyawan')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            // Index untuk query yang sering: filter per karyawan + status
            $table->index(['karyawan_id', 'status']);
            // Index untuk filter per departemen lewat join
            $table->index('tanggal_mulai');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuti_karyawan');
    }
};
