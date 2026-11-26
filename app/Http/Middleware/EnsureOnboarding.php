<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboarding
{
    public function handle(Request $request, Closure $next): Response
    {
        $destination = $request->user()->onboardingRoute();
        $allowed = match ($destination) {
            'onboarding.password' => ['onboarding.password', 'onboarding.password.update'],
            'verification.notice' => ['verification.notice', 'verification.send', 'verification.verify'],
            default => [],
        };
        if (in_array($destination, ['onboarding.password', 'verification.notice'], true)
            && ! $request->routeIs(...$allowed)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Complete required account setup.'], 403)
                : redirect()->route($destination);
        }

        return $next($request);
    }
}
