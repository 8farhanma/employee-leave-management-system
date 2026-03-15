<?php

namespace Tests\Unit\Models;

use App\Models\Karyawan;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KaryawanTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_admin_returns_true_for_admin_role(): void
    {
        $karyawan = new Karyawan();
        $karyawan->role = 'admin';
        $this->assertTrue($karyawan->is_admin);
    }

    public function test_is_admin_returns_false_for_karyawan_role(): void
    {
        $karyawan = new Karyawan(['role' => 'karyawan']);
        $this->assertFalse($karyawan->is_admin);
    }

    public function test_bisa_submit_cuti_returns_false_when_not_enough(): void
    {
        $karyawan = new Karyawan(['sisa_cuti' => 2]);
        $this->assertFalse($karyawan->bisaSubmitCuti(3));
    }

    public function test_bisa_submit_cuti_returns_false_when_sisa_nol(): void
    {
        $karyawan = new Karyawan(['sisa_cuti' => 0]);
        $this->assertFalse($karyawan->bisaSubmitCuti(1));
    }

    public function test_sisa_cuti_label_when_habis(): void
    {
        $karyawan = new Karyawan(['sisa_cuti' => 0]);
        $this->assertEquals('Jatah cuti habis', $karyawan->sisa_cuti_label);
    }

    public function test_sisa_cuti_label_when_ada(): void
    {
        $karyawan = new Karyawan(['sisa_cuti' => 7]);
        $this->assertEquals('7 hari tersisa', $karyawan->sisa_cuti_label);
    }

    public function test_label_departemen_accessor(): void
    {
        $karyawan = new Karyawan(['departemen' => 'QA']);
        $this->assertEquals('Dept. Quality Assurance', $karyawan->label_departemen);

        $karyawan2 = new Karyawan(['departemen' => 'Sewing']);
        $this->assertEquals('Dept. Sewing', $karyawan2->label_departemen);
    }
}
