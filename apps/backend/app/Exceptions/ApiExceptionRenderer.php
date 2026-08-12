<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use App\Support\Enums\ErrorCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Renders every /api/* exception through the standard response envelope
 * (see docs/API_SPECIFICATION.md), so clients always get a stable
 * `errors[].code` instead of an HTML error page or an inconsistent shape.
 * Stack traces are never returned to the client (see docs/SECURITY.md
 * §Error handling); unexpected errors get a correlation id logged
 * server-side instead.
 */
class ApiExceptionRenderer
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                ErrorCode::ValidationFailed,
                $e->getMessage(),
                422,
                ['fields' => $e->errors()],
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(ErrorCode::Unauthenticated, 'Authentication required.', 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(ErrorCode::Unauthorized, 'This action is unauthorized.', 403);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(ErrorCode::NotFound, 'The requested resource was not found.', 404);
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(ErrorCode::RateLimited, 'Too many requests.', 429);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') || app()->hasDebugModeEnabled()) {
                return null;
            }

            $correlationId = (string) Str::uuid();

            report($e);
            logger()->error('Unhandled API exception.', [
                'correlation_id' => $correlationId,
                'exception' => $e::class,
            ]);

            return ApiResponse::error(
                ErrorCode::InternalError,
                'An unexpected error occurred.',
                500,
                ['correlation_id' => $correlationId],
            );
        });
    }
}
