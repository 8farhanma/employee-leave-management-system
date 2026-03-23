<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $karyawan = $request->user();

        if (! $karyawan || ! $karyawan->is_admin) {
            return response()->json([
            'success' => false,  
            'message' => 'Akses ditolak. Hanya Admin mengakses endpoint ini.',
            ], 403);
        }
    
        return $next($request);
    }
}
