<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a return would take a sale item's cumulative returned
 * quantity above what was originally sold. Mapped to HTTP 409 by
 * ApiExceptionRenderer — a conflict with the sale's current state, not a
 * request-shape validation error.
 */
class ExcessiveReturnException extends RuntimeException
{
    public function __construct(int $saleItemId, int $remaining, int $requested)
    {
        parent::__construct("Cannot return {$requested} unit(s) of sale item #{$saleItemId}: only {$remaining} remain returnable.");
    }
}
