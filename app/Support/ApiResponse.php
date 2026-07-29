<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Shared foundation for the uniform API response envelope.
 *
 * Every endpoint (via controllers) and every handled failure (via the
 * exception renderer) produces its output through this helper so the API
 * contract stays consistent:
 *
 *   success: { "success": true,  "message": string, "data": mixed }
 *   error:   { "success": false, "message": string, "errors": object|null }
 *
 * This class holds no business logic and is depended on by any module.
 */
final class ApiResponse
{
    /**
     * Build a successful JSON response.
     */
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Build an error JSON response.
     *
     * @param  array<string, array<int, string>>|null  $errors
     */
    public static function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
