<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Fail closed until a reviewed public HTTPS origin and session policy are approved. */
class RequireApprovedWebsiteHost
{
    public function handle(Request $request, Closure $next)
    {
        // The separate payment relay forwards authenticated callbacks using loopback Host.
        // Never accept a public Host merely because a tunnel connects from loopback.
        $loopback = in_array($request->getHost(), ['localhost', '127.0.0.1', '::1', '[::1]'], true);
        // Preserve the older explicitly enabled narrow webhook-only tunnel policy.
        // RestrictTestTunnel still validates its HTTPS/session settings and allowed paths.
        $restrictedPaymentHost = config('tunnel.enabled') && is_string(config('tunnel.host'))
            && config('tunnel.host') !== '' && $request->getHost() === config('tunnel.host');
        $origin = config('presentation.origin', '');
        $public = $origin !== '' && $request->getHost() === parse_url($origin, PHP_URL_HOST);
        abort_unless($loopback || $restrictedPaymentHost || $public, 400);

        if ($public) {
            abort_unless(in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true), 400);
            abort_unless($request->header('x-forwarded-proto') === 'https', 400);
            abort_if($request->headers->has('x-forwarded-host') || $request->headers->has('forwarded'), 400);
            $client = $request->header('cf-connecting-ip');
            abort_unless(is_string($client) && filter_var($client, FILTER_VALIDATE_IP), 400);
            // Only the loopback Cloudflare connector supplies the public client identity.
            $request->headers->set('x-forwarded-for', $client);
            Request::setTrustedProxies(['127.0.0.1', '::1'], Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_FOR);
            abort_unless($request->isSecure() && config('app.debug') === false, 400);
            $before = [
                'session.secure' => config('session.secure'),
                'trusted-device.secure' => config('trusted-device.secure'),
            ];
            config(['session.secure' => true, 'trusted-device.secure' => true]);
            try {
                return $next($request);
            } finally {
                config($before);
                Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
            }
        }

        return $next($request);
    }
}
