<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PrivateResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if ($request->user() || $request->is('two-factor*', 'onboarding/*', 'verify-email*', 'reset-password*')) {
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('Referrer-Policy', 'same-origin');
        }

        return $response;
    }
}
