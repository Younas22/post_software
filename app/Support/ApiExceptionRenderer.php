<?php

namespace App\Support;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStateTransitionException;
use App\Support\IdempotencyConflictException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Maps any exception thrown on an /api/* route to the standard ApiResponse
 * error envelope, and — critically — decides exactly what's safe to expose.
 * Nothing here ever surfaces a stack trace, a file path, a SQL query, or an
 * env value: known exception types get a clean, human message; anything
 * unrecognized (a genuine bug) gets a single generic message regardless of
 * what its own ->getMessage() says, regardless of APP_DEBUG. The real
 * message/trace is still logged server-side as normal — this class only
 * controls the client-facing response.
 */
class ApiExceptionRenderer
{
    public static function render(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => ApiResponse::error(
                'The given data was invalid.',
                $e->errors(),
                422,
            ),

            $e instanceof AuthenticationException => ApiResponse::error(
                'Unauthenticated.',
                null,
                401,
            ),

            $e instanceof AuthorizationException => ApiResponse::error(
                self::safeMessage($e, 'This action is unauthorized.'),
                null,
                403,
            ),

            $e instanceof InsufficientStockException => ApiResponse::error(
                $e->getMessage(),
                null,
                409,
            ),

            $e instanceof IdempotencyConflictException => ApiResponse::error(
                'This transaction id was already used with different data.',
                null,
                409,
            ),

            $e instanceof InvalidStateTransitionException => ApiResponse::error(
                $e->getMessage(),
                null,
                409,
            ),

            // Laravel's own exception handler converts ModelNotFoundException
            // to NotFoundHttpException *before* any custom render() callback
            // runs (Handler::prepareException()), wrapping the original as
            // ->getPrevious() — so that's what's actually checked here, not
            // "instanceof ModelNotFoundException" directly (which would be
            // unreachable dead code for a real request).
            $e instanceof NotFoundHttpException => ApiResponse::error(
                $e->getPrevious() instanceof ModelNotFoundException
                    ? 'The requested resource was not found.'
                    : 'The requested endpoint was not found.',
                null,
                404,
            ),

            $e instanceof MethodNotAllowedHttpException => ApiResponse::error(
                'This method is not allowed for the requested endpoint.',
                null,
                405,
            ),

            $e instanceof ThrottleRequestsException => ApiResponse::error(
                'Too many requests. Please try again later.',
                null,
                429,
            ),

            // Any other intentionally-thrown HTTP exception (BadRequestHttpException=400,
            // ConflictHttpException=409, custom abort() calls, ...) — status code is
            // preserved, message is used only if the developer actually set one.
            $e instanceof HttpExceptionInterface => ApiResponse::error(
                self::safeMessage($e, self::defaultMessageFor($e->getStatusCode())),
                null,
                $e->getStatusCode(),
            ),

            // Anything unrecognized: never expose the real message/trace.
            default => ApiResponse::error(
                'Server error. Please try again later.',
                null,
                500,
            ),
        };
    }

    /**
     * Exception messages set explicitly by our own code (e.g. abort(409,
     * '...')) are safe to show — they're authored for this purpose. An
     * empty message (the framework's own default for many HTTP exceptions)
     * falls back to a generic, status-appropriate message instead.
     */
    private static function safeMessage(Throwable $e, string $fallback): string
    {
        $message = $e->getMessage();

        return $message !== '' ? $message : $fallback;
    }

    private static function defaultMessageFor(int $status): string
    {
        return match ($status) {
            400 => 'Bad request.',
            409 => 'This request conflicts with the current state of the resource.',
            default => 'An error occurred while processing your request.',
        };
    }
}
