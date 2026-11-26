<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Auth;
use App\Auth\NoRecallerSessionGuard;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Contracts\PaymentGatewayInterface::class, fn ($app) =>
            config('payment_gateway.provider') === 'xendit'
                ? new \App\Services\Payments\XenditGateway
                : new \App\Services\Payments\PayMongoGateway);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::extend('session-no-recaller', function ($app, $name, array $config) {
            $guard = new NoRecallerSessionGuard(
                $name,
                $app['auth']->createUserProvider($config['provider'] ?? null),
                $app['session.store'],
                rehashOnLogin: $app['config']->get('hashing.rehash_on_login', true),
                timeboxDuration: $app['config']->get('auth.timebox_duration', 200000),
                hashKey: $app['config']->get('app.key'),
            );
            $guard->setCookieJar($app['cookie']);
            $guard->setDispatcher($app['events']);
            $guard->setRequest($app->refresh('request', $guard, 'setRequest'));

            return $guard;
        });

        \App\Models\Announcement::saved(function ($announcement) {
            if ($announcement->published_at?->lte(now()) && (! $announcement->expires_at || $announcement->expires_at->gte(now()))) {
                \App\Models\User::where('status', 'active')->each(fn ($user) => app(\App\Services\PortalNotifications::class)->syncAnnouncements($user, $announcement));
            }
        });
        RateLimiter::for('security-form', fn (Request $request) => Limit::perMinute(5)->by($request->route()?->getName().'|'.($request->user()?->id ?? $request->ip()))
        );
    }
}
