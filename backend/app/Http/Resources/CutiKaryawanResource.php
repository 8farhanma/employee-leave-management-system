<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CutiKaryawanResource extends JsonResource
{
    /**
     * Data tambahan yang bisa di-inject dari controller.
     * Contoh: sisa_cuti karyawan setelah transaksi.
     */
    public array $additional_data = [];

    public function withAdditional(array $data): static
    {
        $this->additional_data = $data;
        return $this;
    }

    public function toArray(Request $request): array
    {
        $base = [
            'id'                => $this->id,
            'karyawan'          => new KaryawanResource($this->whenLoaded('karyawan')),
            'jenis_cuti'        => new JenisCutiResource($this->whenLoaded('jenisCuti')),
            'tanggal_mulai'     => $this->tanggal_mulai->format('Y-m-d'),
            'tanggal_selesai'   => $this->tanggal_selesai->format('Y-m-d'),
            'jumlah_hari'       => $this->jumlah_hari,
            'periode_label'     => $this->periode_label,
            'keterangan'        => $this->keterangan,

            // -- Change Request #001 -------------------------------------------------------
            'dokumen_url'       => $this->dokumen_url,  // null jika tidak ada
            'dokumen_nama'      => $this->dokumen_nama, // null jika tidak ada

            'status'            => $this->status,
            'status_label'      => $this->status_label, 
            'catatan_admin'     => $this->catatan_admin,
            'approved_by'       => $this->whenLoaded('approvedBy', fn() => [
                'id'    => $this->approvedBy->id,
                'nama'  => $this->approvedBy->nama,
            ]),
            'approved_at'       => $this->approved_at?->format('Y-m-d H:i:s'),
            'created_at'        => $this->created_at->format('Y-m-d H:i:s'),
        ];

        // Merge data tambahan jika ada (misal: sisa_cuti_terkini)
        return array_merge($base, $this->additional_data);
    }
}
