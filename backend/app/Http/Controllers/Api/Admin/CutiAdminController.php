<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Services\CutiService;

class CutiAdminController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CutiService $cutiService
    )
    {}

    // public function approve()    -> PATCH    /api/admin/cuti/{id}/approve
    // public function reject()     -> PATCH    /api/admin/cuti/{id}/reject
    // public function index()      -> GET      /api/admin/cuti
}
