<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by StockService when a stock decrease would take a product's
 * on-hand quantity below zero. Mapped to HTTP 409 by ApiExceptionRenderer —
 * this represents a genuine conflict with the current state of stock, not a
 * validation error against the request's shape.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly int $available, public readonly int $requested)
    {
        parent::__construct("Insufficient stock: {$available} available, {$requested} requested.");
    }
}
