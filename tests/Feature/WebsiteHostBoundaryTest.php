<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebsiteHostBoundaryTest extends TestCase
{
    public function test_approved_public_origin_uses_https_and_secure_cookies_without_changing_local_policy(): void
    {
        config(['presentation.origin' => 'https://republic-formerly-arch-wayne.trycloudflare.com',
            'app.debug' => false, 'session.secure' => false, 'trusted-device.secure' => false]);
        \Illuminate\Support\Facades\Route::middleware('web')->get('/presentation-boundary', fn () => response()->json([
            'secure' => request()->isSecure(), 'session_secure' => config('session.secure'),
            'device_secure' => config('trusted-device.secure'), 'login' => route('login'), 'ip' => request()->ip(),
        ]));
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'CF-Connecting-IP' => '203.0.113.9', 'X-Forwarded-For' => '198.51.100.8'])
            ->get('https://republic-formerly-arch-wayne.trycloudflare.com/presentation-boundary')
            ->assertOk()->assertJson(['secure' => true, 'session_secure' => true, 'device_secure' => true,
                'login' => 'https://republic-formerly-arch-wayne.trycloudflare.com/login', 'ip' => '203.0.113.9']);
        $this->assertFalse(config('session.secure'));
        $this->assertFalse(config('trusted-device.secure'));
    }

    public function test_public_origin_rejects_non_loopback_proxy_and_forwarded_host_injection(): void
    {
        config(['presentation.origin' => 'https://republic-formerly-arch-wayne.trycloudflare.com', 'app.debug' => false]);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'CF-Connecting-IP' => '203.0.113.9'])
            ->get('https://republic-formerly-arch-wayne.trycloudflare.com/login')->assertStatus(400);
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Host' => 'attacker.example'])
            ->get('https://republic-formerly-arch-wayne.trycloudflare.com/login')->assertStatus(400);
    }

    public function test_unapproved_public_host_is_rejected_before_sessions_or_controllers(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['Host' => 'unapproved.trycloudflare.com', 'X-Forwarded-Proto' => 'https'])
            ->get('https://unapproved.trycloudflare.com/login')->assertStatus(400)->assertCookieMissing(config('session.cookie'));
    }

    public function test_forwarded_public_host_cannot_make_loopback_redirect_to_an_attacker(): void
    {
        $this->withHeaders(['Host' => '127.0.0.1:8000', 'X-Forwarded-Host' => 'unapproved.example'])
            ->get('http://127.0.0.1:8000/dashboard')->assertRedirect('http://127.0.0.1:8000/login');
    }
}
