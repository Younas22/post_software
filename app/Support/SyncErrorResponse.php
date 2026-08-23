<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * The structured failure envelope specific to Electron offline-transaction
 * sync endpoints (see SyncController::sales()) — distinct from the generic
 * ApiResponse::error() envelope used everywhere else, because Electron
 * specifically needs to mechanically decide what to do next: retry later,
 * stop retrying, or surface a conflict to the operator. Adding this as a
 * one-off `error_type` field scoped to these endpoints (rather than
 * changing the global envelope) avoids touching behavior every other
 * already-shipped endpoint depends on.
 *
 * - RETRYABLE: the transaction may succeed later with the SAME payload
 *   unchanged (e.g. stock currently insufficient — it might not be after a
 *   restock or once a conflicting sale is itself reversed).
 * - NON_RETRYABLE: the transaction can never succeed as submitted — a
 *   validation failure, a reference to another tenant's data, a
 *   deactivated cashier. Retrying without changing the payload is pointless.
 * - CONFLICT: this exact `client_transaction_id` was already used with
 *   different data. Retrying is actively wrong — Electron must resolve the
 *   discrepancy (typically: the id was reused by mistake) rather than retry.
 */
class SyncErrorResponse
{
    public const RETRYABLE = 'retryable';

    public const NON_RETRYABLE = 'non_retryable';

    public const CONFLICT = 'conflict';

    public static function make(string $message, string $type, int $status, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_type' => $type,
            'errors' => $errors ?? (object) [],
        ], $status);
    }
}
