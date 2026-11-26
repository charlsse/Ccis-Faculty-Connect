<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class FacultyAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_public_registration_is_unavailable(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
        $this->get(route('landing'))->assertDontSee('/register');
        $this->get(route('login'))->assertDontSee('Create one');
    }

    public function test_password_then_valid_totp_are_required_for_login(): void
    {
        $secret = app(TwoFactorService::class)->generateSecret();
        $user = User::factory()->create([
            'email' => 'faculty.member@umak.edu.ph',
            'password' => Hash::make('Faculty!Pass123'),
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post(route('login.store'), [
            'login' => $user->email,
            'password' => 'Faculty!Pass123',
        ])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        $this->post(route('two-factor.challenge.store'), [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['action' => 'login.succeeded', 'subject_user_id' => $user->id]);
    }

    public function test_invalid_otp_is_rejected_and_rate_limited(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Faculty!Pass123'),
            'two_factor_secret' => app(TwoFactorService::class)->generateSecret(),
        ]);

        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'Faculty!Pass123']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('two-factor.challenge.store'), ['code' => '000000'])->assertSessionHasErrors('code');
        }

        $response = $this->post(route('two-factor.challenge.store'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertStringContainsString('Too many attempts', $response->getSession()->get('errors')->first('code'));
        RateLimiter::clear('otp:'.$user->id.'|127.0.0.1');
    }

    public function test_recovery_code_works_only_once(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Faculty!Pass123')]);
        $code = app(TwoFactorService::class)->generateRecoveryCodes($user)[0];

        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'Faculty!Pass123']);
        $this->post(route('two-factor.challenge.store'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->post(route('logout'));

        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'Faculty!Pass123']);
        $this->post(route('two-factor.challenge.store'), ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_pending_or_unenrolled_user_cannot_access_dashboard(): void
    {
        $pending = User::factory()->create([
            'status' => User::STATUS_PENDING,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);
        $this->actingAs($pending)->get(route('dashboard'))->assertRedirect(route('onboarding.password'));

        $active = User::factory()->create(['two_factor_secret' => null, 'two_factor_confirmed_at' => null]);
        $this->actingAs($active)->get(route('dashboard'))->assertRedirect(route('two-factor.setup'));
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_DEACTIVATED,
            'password' => Hash::make('Faculty!Pass123'),
        ]);

        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'Faculty!Pass123'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }
}
