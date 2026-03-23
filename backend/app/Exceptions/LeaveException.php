<?php

namespace App\Exceptions;

use RuntimeException;

class LeaveException extends RuntimeException
{
    public static function sudahDiproses(string $status): self
    {
        return new self(
            "Pengajuan ini tidak dapat diproses karena statusnya '{$status}'.",
            422
        );
    }

    public static function bukanMilikSendiri(): self
    {
        return new self('Anda tidak memiliki izin atas pengajuan ini.', 403);
    }

    public static function sisaCutiKurang(int $butuh, int $sisa): self
    {
        return new self(
            "Sisa cuti tidak mencukupi. Butuh {$butuh} hari, sisa {$sisa} hari.",
            422
        );
    }
}