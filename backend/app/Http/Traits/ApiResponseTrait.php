<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Response sukses standar
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Berhasil.',
        int $statusCode = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Response error standar
     */
    protected function errorResponse(
        string $message = 'Terjadi kesalahan.',
        int $statusCode = 400,
        array $errors = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Response 404 Not Found
     */
    protected function notFoundResponse(string $entity = 'Data'): JsonResponse
    {
        return $this->errorResponse("{$entity} tidak ditemukan.", 404);
    }

    /**
     * Response 403 Forbidden
     */
    protected function forbiddenResponse(string $message = 'Akses ditolak.'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Response 201 Created
     */
    protected function createdResponse(mixed $data, string $message = 'Data berhasil dibuat.'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }
}