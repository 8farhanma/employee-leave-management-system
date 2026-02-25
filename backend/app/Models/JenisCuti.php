<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisCuti extends Model
{
    protected $table = 'jenis_cuti';

    protected $fillable = [
        'nama',
        'potong_jatah',
        'keterangan',
    ];

    protected $cast = [
        'potong_jatah' => 'boolean',
    ];
    
    public function cutiKaryawan(): HasMany
    {
        return $this->hasMany(CutiKaryawan::class);
    }
}