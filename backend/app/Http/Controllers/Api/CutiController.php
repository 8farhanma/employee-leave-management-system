<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveRequest;
use App\Http\Resources\CutiKaryawanResource;
use App\Http\Traits\ApiResponseTrait;
use App\Services\CutiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CutiController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CutiService $cutiService
    )
    {}

    // ────────────────────────────────────────────────────────────────
    // POST /api/cuti
    // Ajukan cuti baru
    // ────────────────────────────────────────────────────────────────
    public function store(StoreLeaveRequest $request): JsonResponse
    {
        try {
            $karyawan = $request->user();

            // Buat pengajuan cuti melalui service
            // Service akan: hitung hari, validasi sisa cuti, simpan ke DB
            $cuti = $this->cutiService->submit($karyawan, $request->validated());

            // Load relasi untuk response
            $cuti->load(['jenisCuti']);

            // Refresh data karyawan untuk dapat sisa_cuti terkini
            $karyawan->refresh();

            $resource = (new CutiKaryawanResource($cuti))
                ->withAdditional([
                    'sisa_cuti_setelah' => $karyawan->sisa_cuti,
                    'sisa_cuti_label'   => $karyawan->sisa_cuti_label,
                ]);

            return $this->createdResponse(
                $resource,
                'Pengajuan cuti berhasil disubmit. Menunggu persetujuan admin.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    // public function index()      -> GET      /api/cuti
    // public function show()       -> GET      /api/cuti/{id}
    // public function destroy()    -> DELETE   /api/cuti/{id}
}
