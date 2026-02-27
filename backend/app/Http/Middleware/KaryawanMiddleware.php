<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KaryawanMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return response()->json([
                'success' => false,  
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.',
            ], 401);
        }
    
        return $next($request);
    }
}
