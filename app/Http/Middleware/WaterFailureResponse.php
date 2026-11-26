<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class WaterFailureResponse
{
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (\Throwable $exception) {
            if ($exception instanceof ValidationException
                || $exception instanceof AuthorizationException
                || $exception instanceof AuthenticationException
                || $exception instanceof HttpExceptionInterface
                || $exception instanceof ModelNotFoundException) {
                throw $exception;
            }
            // Never log SQL bindings, file paths, decrypted fields, request bodies or exception messages.
            Log::error('Water module operation failed', ['exception_type' => get_class($exception), 'route' => $request->route()?->getName()]);

            return response()->view('water.failure', [], 503);
        }
    }
}
