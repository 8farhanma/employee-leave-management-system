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
        Schema::create('karyawan', function (Blueprint $table) {
            $table->id();

            $table->string('nik', 20)->unique()
                ->comment('Nomor Induk Karyawan');

            $table->string('nama', 100);

            $table->enum('departemen', [
                'Sewing',
                'Cutting',
                'Finishing',
                'QA',
            ])->nullable();

            $table->enum('role', ['karyawan', 'admin'])
                ->default('karyawan');

            $table->string('email', 100)->unique();
            $table->string('password');

            //Default 12 hari, tidak boleh negatif, maks 12
            $table->unsignedTinyInteger('sisa_cuti')->default(12);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('karyawan');
    }
};
