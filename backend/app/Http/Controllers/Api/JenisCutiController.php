<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\JenisCutiResource;
use App\Models\JenisCuti;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JenisCutiController extends Controller
{
    // ────────────────────────────────────────────────────────────────
    // GET /api/jenis-cuti
    // List semua jenis cuti - bisa difilter
    // ────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = JenisCuti::query();

        // Filter opsional: ?potong_jatah=true / false
        if ($request->has('potong_jatah')) {
            $potong = filter_var($request->potong_jatah, FILTER_VALIDATE_BOOLEAN);
            $query->where('potong_jatah', $potong);
        }

        $jenisCuti = $query->orderBy('nama')->get();

        return response()->json([
            'success'   => true,
            'data'      => JenisCutiResource::collection($jenisCuti),
            'meta'      => [
                'total'         => $jenisCuti->count(),
                'potong_jatah'  => $jenisCuti->where('potong_jatah', true)->count(),
                'tidak_potong'  => $jenisCuti->where('potong_jatah', false)->count(),
            ],
        ], 200);
    }

    // ────────────────────────────────────────────────────────────────
    // GET /api/jenis-cuti/{id}
    // Detail satu jenis cuti
    // ────────────────────────────────────────────────────────────────
    public function show(JenisCuti $jenisCuti): JsonResponse
    {
        return response()->json([
            'success'   => true,
            'data'      => new JenisCutiResource($jenisCuti),
        ], 200);
    }
}
