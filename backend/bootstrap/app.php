<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Daftarkan alias middleware
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'karyawan' => \App\Http\Middleware\KaryawanMiddleware::class,
        ]); 
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // 401 - Unauthenticated (token tidak ada / expired)
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi anda telah berakhir. Silakan login kembali.',
                ], 401);
            }
        });

        // 404 - Model tidak ditemukan
        $exceptions->render(function (ModelNotFoundException $e, $request) {
            if ($request->is('api/*')) {
                $model = class_basename($e->getModel());
                return response()->json([
                    'success' => false,
                    'message' => "{$model} tidak ditemukan.",
                ], 404);
            }
        });        

        // 422 - Data tidak valid
        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // 404 — Route tidak ada
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint tidak ditemukan.',
                ], 404);
            }
        });

        // 405 — Method tidak diizinkan
        $exceptions->render(function (MethodNotAllowedHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'HTTP method tidak diizinkan untuk endpoint ini.',
                ], 405);
            }
        });        

        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {

                $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException
                    ? $e->getStatusCode()
                    : 500;

                $message = $status === 500
                    ? 'Terjadi kesalahan pada server.'  // jangan expose pesan internal di prod
                    : $e->getMessage();

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], $status);
            }
        });
    })->create();
