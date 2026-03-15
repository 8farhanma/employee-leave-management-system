<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CutiKaryawanResource extends JsonResource
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
            'karyawan'          => new KaryawanResource($this->whenLoaded('karyawan')),
            'tanggal_mulai'     => $this->tanggal_mulai->format('Y-m-d'),
            'tanggal_selesai'   => $this->tanggal_selesai->format('Y-m-d'),
            'jumlah_hari'       => $this->jumlah_hari,
            'periode_label'     => $this->periode_label, // "10 Mar - 12 Mar (3 hari)"
            'status'            => $this->status,
            'status_label'      => $this->status_label, // "Disetujui", "Ditolak", "Menunggu"
            'catatan_admin'     => $this->catatan_admin,
            'approved_by'       => $this->whenLoaded('approvedBy', function () {
                return [
                    'id'    => $this->approvedBy->id,
                    'nama'  => $this->approvedBy->nama,
                ];
            }),
            'approved_at'       => $this->approved_at?->format('Y-m-d H:i:s'),
            'created_at'        => $this->created_at->format('Y-m-d H:i:s'),
            'jenis_cuti'        => new JenisCutiResource($this->whenLoaded('jenisCuti')),
        ];
    }
}
