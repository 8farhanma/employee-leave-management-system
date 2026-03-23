<?php

namespace Tests\Unit\Models;

use App\Models\CutiKaryawan;
use PHPUnit\Framework\TestCase;

class CutiKaryawanTest extends TestCase
{
    public function test_is_pending_returns_true(): void
    {
        $cuti = new CutiKaryawan(['status' => 'pending']);
        $this->assertTrue($cuti->isPending());
    }

    public function test_is_pending_returns_false_for_approved(): void
    {
        $cuti = new CutiKaryawan(['status' => 'approved']);
        $this->assertFalse($cuti->isPending());
    }

    public function test_status_label_pending(): void
    {
        $cuti = new CutiKaryawan(['status' => 'pending']);
        $this->assertStringContainsString('Menunggu', $cuti->status_label);
    }

    public function test_status_label_approved(): void
    {
        $cuti = new CutiKaryawan(['status' => 'approved']);
        $this->assertStringContainsString('Disetujui', $cuti->status_label);
    }

    public function test_status_label_rejected(): void
    {
        $cuti = new CutiKaryawan(['status' => 'rejected']);
        $this->assertStringContainsString('Ditolak', $cuti->status_label);
    }
}
