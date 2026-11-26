<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RestrictTestTunnel
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('tunnel.enabled')) return $next($request);
        // ngrok runs locally. Never trust forwarded hosts or arbitrary remote proxies.
        Request::setTrustedProxies(['127.0.0.1', '::1'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
        $host = config('tunnel.host');
        $configured = is_string($host) && preg_match('/^[a-z0-9][a-z0-9.-]+\.[a-z]{2,}$/D', $host)
            && config('app.debug') === false && config('session.secure') === true
            && config('session.http_only') === true && config('session.same_site') === 'lax'
            && config('session.domain') === null
            && str_starts_with((string) config('session.cookie'), 'facultyconnect_xendit_test_')
            && config('app.url') === 'https://'.$host
            && config('payment_gateway.provider') === 'xendit'
            && app(\App\Services\Payments\XenditGateway::class)->available();
        abort_unless($configured, 503, 'Test tunnel configuration is incomplete.');
        abort_unless(in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true), 400);
        abort_unless(in_array($request->getHost(), ['localhost', '127.0.0.1', $host], true), 400);
        if ($request->getHost() !== $host) {
            // A rewritten Host must not turn a forwarded public request into local access.
            abort_if($request->headers->has('x-forwarded-for') || $request->headers->has('x-forwarded-proto')
                || $request->headers->has('x-forwarded-host') || $request->headers->has('forwarded'), 400);
        }
        if ($request->getHost() === $host) {
            abort_unless($request->isSecure(), 400);
            // Do not expose login, administration, diagnostics, files, or financial reports.
            abort_unless(($request->isMethod('POST') && $request->path() === 'webhooks/payments/xendit')
                || ($request->isMethod('GET') && $request->path() === 'payments/xendit/return'), 404);
        }
        return $next($request);
    }
}
