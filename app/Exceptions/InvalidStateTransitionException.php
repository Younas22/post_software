<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a status-changing action (receive, cancel, close, ...) is
 * attempted on a record that isn't in a state where that action is valid —
 * e.g. receiving an already-received purchase. Mapped to HTTP 409 by
 * ApiExceptionRenderer: this is a conflict with current state, not a
 * validation error against the request's shape.
 */
class InvalidStateTransitionException extends RuntimeException {}
