<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\TrustedDeviceService;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TrustedDeviceTest extends TestCase
{
    use RefreshDatabase;

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/140.0 Safari/537.36';

    private const OTHER_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X) AppleWebKit/605.1.15 Version/18.0 Safari/605.1.15';

    public function test_first_login_requires_2fa_and_trust_selection_creates_secure_hashed_device(): void
    {
        config(['trusted-device.secure' => true]);
        [$user, $secret] = $this->makeUser();

        $this->withHeader('User-Agent', self::USER_AGENT)
            ->post(route('login.store'), ['login' => $user->email, 'password' => 'Faculty!Pass123'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        $response = $this->post(route('two-factor.challenge.store'), [
            'code' => (new Google2FA)->getCurrentOtp($secret),
            'trust_device' => '1',
        ])->assertRedirect(route('dashboard'))
            ->assertCookie(config('trusted-device.cookie'));

        $plainCookie = $response->getCookie(config('trusted-device.cookie'))->getValue();
        $wireCookie = $response->getCookie(config('trusted-device.cookie'), false);
        [$deviceId, $rawToken] = explode('.', $plainCookie, 2);
        $device = TrustedDevice::findOrFail($deviceId);

        $this->assertTrue($wireCookie->isSecure());
        $this->assertTrue($wireCookie->isHttpOnly());
        $this->assertSame('lax', $wireCookie->getSameSite());
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame(hash_hmac('sha256', $rawToken, config('app.key')), $device->token_hash);
        $this->assertNotSame($rawToken, $device->token_hash);
        $this->assertTrue($device->expires_at->between(now()->addDays(19), now()->addDays(21)));
        $this->assertStringNotContainsString($rawToken, AuditLog::get()->toJson());
    }

    public function test_same_trusted_browser_skips_only_2fa_and_rotates_token(): void
    {
        [$user, $secret] = $this->makeUser();
        $trusted = $this->trustThroughChallenge($user, $secret);
        $device = TrustedDevice::firstOrFail();
        $oldHash = $device->token_hash;
        $this->post(route('logout'))->assertRedirect(route('landing'));
        $this->assertNull($device->fresh()->revoked_at);

        $this->withHeader('User-Agent', self::USER_AGENT)
            ->withCookie(config('trusted-device.cookie'), $trusted)
            ->post(route('login.store'), ['login' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();

        $response = $this->post(route('login.store'), [
            'login' => $user->email,
            'password' => 'Faculty!Pass123',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldHash, $device->fresh()->token_hash);
        $this->assertNotSame($trusted, $response->getCookie(config('trusted-device.cookie'))->getValue());
        $this->assertDatabaseHas('audit_logs', ['action' => 'trusted_device.used', 'subject_user_id' => $user->id]);
    }

    public function test_missing_cookie_or_another_browser_requires_2fa(): void
    {
        [$user, $secret] = $this->makeUser();
        $trusted = $this->trustThroughChallenge($user, $secret);
        $this->post(route('logout'));

        $this->withCookie(config('trusted-device.cookie'), '')
            ->post(route('login.store'), ['login' => $user->email, 'password' => 'Faculty!Pass123'])
            ->assertRedirect(route('two-factor.challenge'));

        $device = $this->issueDevice($user, self::USER_AGENT);
        $this->withHeader('User-Agent', self::OTHER_USER_AGENT)
            ->withCookie(config('trusted-device.cookie'), $device['cookie'])
            ->post(route('login.store'), ['login' => $user->email, 'password' => 'Faculty!Pass123'])
            ->assertRedirect(route('two-factor.challenge'))
            ->assertCookieExpired(config('trusted-device.cookie'));
        $this->assertNotNull($device['model']->fresh()->revoked_at);
        $this->assertNotEmpty($trusted);
    }

    public function test_expired_changed_unknown_and_revoked_tokens_require_2fa(): void
    {
        foreach (['expired', 'changed', 'unknown', 'revoked'] as $scenario) {
            [$user] = $this->makeUser();
            $issued = $this->issueDevice($user, self::USER_AGENT);
            $cookie = $issued['cookie'];

            if ($scenario === 'expired') {
                $issued['model']->update(['expires_at' => now()->subMinute()]);
            } elseif ($scenario === 'changed') {
                $cookie = $issued['model']->id.'.'.str_repeat('X', 64);
            } elseif ($scenario === 'unknown') {
                $cookie = '999999.'.str_repeat('X', 64);
            } else {
                $issued['model']->update(['revoked_at' => now()]);
            }

            $this->withHeader('User-Agent', self::USER_AGENT)
                ->withCookie(config('trusted-device.cookie'), $cookie)
                ->post(route('login.store'), ['login' => $user->email, 'password' => 'Faculty!Pass123'])
                ->assertRedirect(route('two-factor.challenge'))
                ->assertCookieExpired(config('trusted-device.cookie'));
            $this->assertGuest();
        }
    }

    public function test_trusted_token_cannot_be_used_for_another_user(): void
    {
        [$owner] = $this->makeUser();
        [$other] = $this->makeUser();
        $issued = $this->issueDevice($owner, self::USER_AGENT);

        $this->withHeader('User-Agent', self::USER_AGENT)
            ->withCookie(config('trusted-device.cookie'), $issued['cookie'])
            ->post(route('login.store'), ['login' => $other->email, 'password' => 'Faculty!Pass123'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->assertNotNull($issued['model']->fresh()->revoked_at);
    }

    public function test_expired_trust_can_be_renewed_for_twenty_days_after_fresh_2fa(): void
    {
        [$user, $secret] = $this->makeUser();
        $expired = $this->issueDevice($user, self::USER_AGENT);
        $expired['model']->update(['expires_at' => now()->subMinute()]);

        $this->withHeader('User-Agent', self::USER_AGENT)
            ->withCookie(config('trusted-device.cookie'), $expired['cookie'])
            ->post(route('login.store'), ['login' => $user->email, 'password' => 'Faculty!Pass123'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->post(route('two-factor.challenge.store'), [
            'code' => (new Google2FA)->getCurrentOtp($secret),
            'trust_device' => '1',
        ])->assertRedirect(route('dashboard'));

        $renewed = $user->trustedDevices()->latest('id')->firstOrFail();
        $this->assertNotSame($expired['model']->id, $renewed->id);
        $this->assertTrue($renewed->expires_at->between(now()->addDays(19), now()->addDays(21)));
        $this->assertDatabaseHas('audit_logs', ['action' => 'trusted_device.renewed', 'subject_user_id' => $user->id]);
    }

    public function test_normal_logout_preserves_trust_but_logout_and_forget_revokes_it(): void
    {
        [$user, $secret] = $this->makeUser();
        $cookie = $this->trustThroughChallenge($user, $secret);
        $device = TrustedDevice::firstOrFail();

        $this->post(route('logout'))->assertRedirect(route('landing'))->assertCookieMissing(config('trusted-device.cookie'));
        $this->assertNull($device->fresh()->revoked_at);

        $trustedLogin = $this->withHeader('User-Agent', self::USER_AGENT)
            ->withCookie(config('trusted-device.cookie'), $cookie)
            ->post(route('login.store'), ['login' => $user->email, 'password' => 'Faculty!Pass123']);
        $rotatedCookie = $trustedLogin->getCookie(config('trusted-device.cookie'))->getValue();

        $this->withCookie(config('trusted-device.cookie'), $rotatedCookie)
            ->post(route('logout.forget-device'))
            ->assertRedirect(route('landing'))
            ->assertCookieExpired(config('trusted-device.cookie'));
        $this->assertNotNull($device->fresh()->revoked_at);
    }

    public function test_user_can_revoke_one_or_all_other_trusted_devices(): void
    {
        [$user] = $this->makeUser();
        $current = $this->issueDevice($user, self::USER_AGENT);
        $other = $this->issueDevice($user, self::OTHER_USER_AGENT);

        $this->actingAs($user)
            ->withHeader('User-Agent', self::USER_AGENT)
            ->withCookie(config('trusted-device.cookie'), $current['cookie'])
            ->delete(route('security.trusted-devices.destroy', $other['model']), ['current_password' => 'Faculty!Pass123'])
            ->assertRedirect();
        $this->assertNotNull($other['model']->fresh()->revoked_at);
        $this->assertNull($current['model']->fresh()->revoked_at);

        $third = $this->issueDevice($user, self::OTHER_USER_AGENT);
        $this->delete(route('security.trusted-devices.destroy-others'), ['current_password' => 'Faculty!Pass123'])
            ->assertRedirect();
        $this->assertNotNull($third['model']->fresh()->revoked_at);
        $this->assertNull($current['model']->fresh()->revoked_at);
    }

    public function test_password_reset_admin_2fa_reset_and_deactivation_revoke_all_trusted_devices(): void
    {
        [$passwordResetUser] = $this->makeUser();
        $this->issueDevice($passwordResetUser, self::USER_AGENT);
        $resetToken = Password::createToken($passwordResetUser);
        $this->post(route('password.update'), [
            'token' => $resetToken,
            'email' => $passwordResetUser->email,
            'password' => 'Changed!Pass123',
            'password_confirmation' => 'Changed!Pass123',
        ])->assertRedirect(route('login'));
        $this->assertNotNull($passwordResetUser->trustedDevices()->first()->revoked_at);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'password' => Hash::make('Admin!Pass123')]);
        [$resetUser] = $this->makeUser();
        $this->issueDevice($resetUser, self::USER_AGENT);
        $this->actingAs($admin)->delete(route('members.two-factor.reset', $resetUser), [
            'current_password' => 'Admin!Pass123',
        ])->assertRedirect();
        $this->assertNotNull($resetUser->trustedDevices()->first()->revoked_at);

        [$deactivated] = $this->makeUser();
        $this->issueDevice($deactivated, self::USER_AGENT);
        $this->actingAs($admin)->patch(route('members.update', $deactivated), [...$this->accountData($deactivated, User::STATUS_DEACTIVATED), 'current_password' => 'Admin!Pass123'])->assertRedirect();
        $this->assertNotNull($deactivated->trustedDevices()->first()->revoked_at);
    }

    private function makeUser(): array
    {
        $secret = app(TwoFactorService::class)->generateSecret();
        $user = User::factory()->create([
            'password' => Hash::make('Faculty!Pass123'),
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        return [$user, $secret];
    }

    private function trustThroughChallenge(User $user, string $secret): string
    {
        $this->withHeader('User-Agent', self::USER_AGENT)
            ->post(route('login.store'), ['login' => $user->email, 'password' => 'Faculty!Pass123']);
        $response = $this->post(route('two-factor.challenge.store'), [
            'code' => (new Google2FA)->getCurrentOtp($secret),
            'trust_device' => '1',
        ]);

        return $response->getCookie(config('trusted-device.cookie'))->getValue();
    }

    private function issueDevice(User $user, string $userAgent): array
    {
        $request = Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => $userAgent,
        ]);
        $request->setUserResolver(fn (): User => $user);
        $result = app(TrustedDeviceService::class)->issue($user, $request);

        return ['model' => $result->device, 'cookie' => $result->cookie->getValue()];
    }

    private function accountData(User $user, string $status): array
    {
        return [
            'faculty_id' => $user->faculty_id,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'department' => $user->department,
            'position' => $user->position,
            'contact_number' => $user->contact_number,
            'bio' => $user->bio,
            'role' => $user->role,
            'status' => $status,
        ];
    }
}
