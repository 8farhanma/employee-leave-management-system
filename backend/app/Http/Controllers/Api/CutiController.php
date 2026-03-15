<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Services\CutiService;

class CutiController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CutiService $cutiService
    )
    {}

    // public function store()      -> POST     /api/cuti
    // public function index()      -> GET      /api/cuti
    // public function show()       -> GET      /api/cuti/{id}
    // public function destroy()    -> DELETE   /api/cuti/{id}
}
