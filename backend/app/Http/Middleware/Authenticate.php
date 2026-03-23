<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Kembalikan null agar tidak redirect ke route 'login'
     * — Laravel akan throw AuthenticationException yang
     *   kemudian ditangani oleh withExceptions di bootstrap/app.php
     */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}