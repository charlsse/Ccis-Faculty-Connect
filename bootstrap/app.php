<?php

use App\Http\Middleware\EnsureAccountIsUsable;
use App\Http\Middleware\EnsureOnboarding;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureTwoFactorConfirmed;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\PrivateResponse;
use App\Http\Middleware\RestrictTestTunnel;
use App\Http\Middleware\RequireApprovedWebsiteHost;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RequireApprovedWebsiteHost::class);
        $middleware->append(RestrictTestTunnel::class);
        $middleware->validateCsrfTokens(except: ['webhooks/payments/paymongo', 'webhooks/payments/xendit', 'webhooks/payments/xendit-demo']);
        $middleware->web(append: [PrivateResponse::class]);
        $middleware->alias([
            'onboarding' => EnsureOnboarding::class,
            'permission' => EnsurePermission::class,
            'role' => EnsureUserHasRole::class,
            'account.usable' => EnsureAccountIsUsable::class,
            'two-factor.confirmed' => EnsureTwoFactorConfirmed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $exception, Request $request) {
            // Connection outages may occur before a controller, while loading sessions.
            $driverCode = (int) ($exception->errorInfo[1] ?? 0);
            if (! in_array($driverCode, [2002, 2003, 2006, 2013], true)) {
                return null;
            }

            $message = 'The website is temporarily reconnecting to its database. Please try again shortly.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 503, ['Retry-After' => '60'])
                : response()->view('errors.database-unavailable', [], 503, ['Retry-After' => '60']);
        });

        $exceptions->dontFlash([
            'password',
            'password_confirmation',
            'current_password',
            'code',
            'token', 'temporary_password',
            'contact_number',
            'bio',
            'reference_no',
            'notes',
        ]);
    })->create();
