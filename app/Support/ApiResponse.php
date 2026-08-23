<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * The single, consistent response envelope every /api/* endpoint returns
 * through — success and error alike — so a frontend (Next.js, Electron,
 * mobile) never has to guess the shape of a response. Nothing in this class
 * is business logic; it only shapes output.
 *
 * Success:    {"success": true,  "message": "...", "data": {...}}
 * Collection: {"success": true,  "message": "...", "data": [...], "meta": {...}}
 * Error:      {"success": false, "message": "...", "errors": {...}}
 */
class ApiResponse
{
    /**
     * A single-resource (or no-payload) success response.
     */
    public static function success(mixed $data = null, string $message = 'Request successful.', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data ?? (object) [],
        ], $status);
    }

    /**
     * A collection/list success response. Passing a LengthAwarePaginator
     * auto-populates `meta` with standard pagination fields; passing a plain
     * array/Collection uses $meta as given (or an empty object).
     */
    public static function collection(mixed $data, string $message = 'Request successful.', ?array $meta = null, int $status = 200): JsonResponse
    {
        if ($data instanceof LengthAwarePaginator) {
            $meta = [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ];
            $data = $data->items();
        }

        // collect()->values()->all() handles a Collection, a plain array, or
        // any Arrayable/Traversable the same way — re-indexed, never cast
        // via (array) which would mangle an object's properties instead of
        // its items.
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => collect($data)->values()->all(),
            'meta' => $meta ?? (object) [],
        ], $status);
    }

    /**
     * A standardized error response. $errors is typically a validation
     * error bag (field => [messages]) but may be any serializable detail —
     * it must never contain a stack trace, SQL, a file path, or an env
     * value (see ApiExceptionRenderer, which is what actually decides what
     * goes in here for thrown exceptions).
     */
    public static function error(string $message, mixed $errors = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors ?? (object) [],
        ], $status);
    }
}
