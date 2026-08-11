<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Standard API response envelope used by every /api/v1 endpoint, so that
 * mobile clients can branch on stable machine-readable error codes instead
 * of parsing Arabic error text (see docs/API_SPECIFICATION.md).
 */
class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => (object) $meta,
            'errors' => null,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function error(string $code, string $message, int $status = 400, array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => null,
            'meta' => (object) $meta,
            'errors' => [
                [
                    'code' => $code,
                    'message' => $message,
                ],
            ],
        ], $status);
    }
}
