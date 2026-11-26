<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PaymentRateLimitTest extends TestCase
{
    public function test_notification_polling_does_not_exhaust_checkout_but_checkout_remains_limited(): void
    {
        $checkout = app('router')->getRoutes()->getByName('water.demo.start');
        $throttle = collect($checkout->middleware())->first(fn ($value) => str_starts_with($value, 'throttle:'));
        $this->assertSame('throttle:5,1,water.demo.start:', $throttle);
        Route::get('/rate-test-feed', fn () => response('ok'))->middleware('throttle:30,1');
        Route::post('/rate-test-checkout', fn () => response('ok'))->middleware($throttle);
        $user = new User;
        $user->id = 987654;
        $this->actingAs($user);
        for ($i = 0; $i < 10; $i++) {
            $this->get('/rate-test-feed')->assertOk();
        }
        for ($i = 0; $i < 5; $i++) {
            $this->post('/rate-test-checkout')->assertOk();
        }
        $this->post('/rate-test-checkout')->assertStatus(429);
    }
}
