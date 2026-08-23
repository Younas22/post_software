<?php

namespace App\Support;

use RuntimeException;

/**
 * Thrown by IdempotencyService when a client_transaction_id is reused with a
 * materially different payload than the one it was first submitted with.
 * Mapped to HTTP 409 by ApiExceptionRenderer.
 */
class IdempotencyConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This transaction id was already used with different data.');
    }
}
