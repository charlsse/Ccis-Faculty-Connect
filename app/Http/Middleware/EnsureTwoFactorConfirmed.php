<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->status === 'pending_activation' || ! $user->two_factor_confirmed_at) {
            return redirect()->route('two-factor.setup');
        }

        if ((int) $request->session()->get('two_factor_user_id') !== $user->id) {
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
