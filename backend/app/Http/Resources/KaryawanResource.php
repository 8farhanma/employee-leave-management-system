<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KaryawanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'nik'               => $this->nik,
            'nama'              => $this->nama,
            'departemen'        => $this->departemen,
            'label_departemen'  => $this->label_departemen, // accessor
            'role'              => $this->role,
            'email'             => $this->email,
            'sisa_cuti'         => $this->sisa_cuti,
            'sisa_cuti_label'   => $this->sisa_cuti_label, // accessor
        ];
        // password tidak ditampilkan di sini karena $hidden
    }
}
