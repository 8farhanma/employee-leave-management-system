<?php

namespace App\Constants;

final class LeaveConstants
{
    /**
     * Jatah cuti tahunan per karyawan per tahun
     */
    public const ANNUAL_QUOTA = 12;

    /**
     * Nama jenis cuti yang memotong jatah
     * (dipakai untuk validasi & label di frontend)
     */
    public const ANNUAL_LEAVE_NAME = 'Cuti Tahunan';

    /**
     * Status pengajuan cuti
     */
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const ALL_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    /**
     * Departemen yang valid
     */
    public const DEPARTMENTS = [
        'Sewing',
        'Cutting',
        'Finishing',
        'QA',
    ];

    /**
     * Role karyawan
     */
    public const ROLE_ADMIN    = 'admin';
    public const ROLE_KARYAWAN = 'karyawan';

    public const ALL_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_KARYAWAN,
    ];
}