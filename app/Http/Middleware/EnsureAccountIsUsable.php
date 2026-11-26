<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()?->fresh();
        if ($user) {
            Auth::setUser($user);
            $request->setUserResolver(fn () => $user);
        }

        if (! $user || $user->status === 'deactivated') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => 'This account is not available. Contact an administrator.']);
        }

        $sessionVersion = $request->session()->get('security_version');
        if ($sessionVersion === null || (int) $sessionVersion !== $user->security_version || Auth::viaRemember()
            || ($user->must_change_password && ! $user->temporary_password_expires_at?->isFuture())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => 'Your session expired after a security change. Please sign in again.']);
        }

        return $next($request);
    }
}
