<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\AccountInvitation;
use App\Services\AdministratorService;
use App\Services\InvitationService;
use App\Services\SessionManager;
use App\Services\TrustedDeviceService;
use App\Services\TwoFactorService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AccountWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function pending(): User
    {
        return User::factory()->create([
            'status' => User::STATUS_PENDING, 'activated_at' => null, 'email_verified_at' => null,
            'two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null,
        ]);
    }

    private function invite(User $user): string
    {
        app(InvitationService::class)->send($user);
        $notification = Notification::sent($user, AccountInvitation::class)->last();

        return $notification->toMail($user)->viewData['temporaryPassword'];
    }

    private function identity(array $overrides = []): array
    {
        return [...[
            'first_name' => 'Test', 'last_name' => 'President', 'faculty_id' => 'TEST-PRESIDENT',
            'email' => 'president@example.test', 'department' => 'CCIS', 'position' => 'President',
        ], ...$overrides];
    }

    public function test_creation_ignores_tampered_status_and_accepts_omitted_status(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['active', 'deactivated', null] as $index => $status) {
            $data = [...$this->identity(['faculty_id' => 'NEW-'.$index, 'email' => 'new'.$index.'@example.test']), 'role' => 'faculty'];
            if ($status !== null) {
                $data['status'] = $status;
            }
            $this->actingAs($admin)->post(route('members.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('users', ['faculty_id' => 'NEW-'.$index, 'status' => User::STATUS_PENDING]);
        }
        Notification::assertNothingSent();
    }

    public function test_reactivation_preserves_existing_password_and_still_requires_email_verification(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'AdminPassword!123']);
        $member = User::factory()->create(['status' => 'deactivated', 'email_verified_at' => null]);
        $data = $member->only(['faculty_id', 'first_name', 'last_name', 'email', 'department', 'position', 'role']);
        $this->actingAs($admin)->patch(route('members.update', $member), [...$data, 'status' => 'active', 'current_password' => 'AdminPassword!123'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(User::STATUS_ACTIVE, $member->fresh()->status);
        $this->actingAs($member->fresh())->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_invitation_template_converts_expiry_instead_of_relabeling_it(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        $data = ['name' => 'Synthetic Member', 'role' => 'Faculty Member', 'username' => 'test@example.test',
            'temporaryPassword' => 'Dummy!Password123', 'expiresAt' => 'September 23, 2026 1:00 AM UTC',
            'loginUrl' => 'http://localhost/login', 'support' => null];
        foreach (['mail.invitation', 'mail.invitation-text'] as $template) {
            $this->assertStringContainsString('September 23, 2026 9:00 AM Philippine Time (UTC+8)', view($template, $data)->render());
        }
    }

    public function test_reactivation_does_not_skip_temporary_password_or_two_factor_setup(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'AdminPassword!123']);
        foreach ([
            ['must_change_password' => true, 'temporary_password_expires_at' => now()->addDay(), 'expected' => 'onboarding.password', 'status_after' => 'pending_activation'],
            ['two_factor_confirmed_at' => null, 'expected' => 'two-factor.setup', 'status_after' => 'active'],
        ] as $scenario) {
            $expected = $scenario['expected'];
            $statusAfter = $scenario['status_after'];
            unset($scenario['expected'], $scenario['status_after']);
            $member = User::factory()->create(['status' => 'deactivated', ...$scenario]);
            $data = $member->only(['faculty_id', 'first_name', 'last_name', 'email', 'department', 'position', 'role']);
            $this->actingAs($admin)->patch(route('members.update', $member), [...$data, 'status' => 'active', 'current_password' => 'AdminPassword!123'])
                ->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame($statusAfter, $member->fresh()->status);
            $this->actingAs($member->fresh())->get(route('dashboard'))->assertRedirect(route($expected));
        }
    }

    public function test_bootstrap_command_creates_first_admin_without_guessing_or_verifying_email(): void
    {
        $this->artisan('facultyconnect:setup-admin')
            ->expectsQuestion('Actual first name', 'Test')->expectsQuestion('Actual last name', 'President')
            ->expectsQuestion('Official email address', 'president@example.test')
            ->expectsQuestion('Employee or faculty ID', 'TEST-PRESIDENT')
            ->expectsQuestion('Department', 'CCIS')->expectsQuestion('Position or designation', 'President')
            ->expectsQuestion('Initial password (hidden)', 'President!Pass123')
            ->expectsQuestion('Confirm password (hidden)', 'President!Pass123')
            ->expectsConfirmation('Create this first administrator for Sir Olivo?', 'yes')
            ->assertSuccessful();
        $user = User::where('faculty_id', 'TEST-PRESIDENT')->firstOrFail();
        $this->assertTrue($user->isAdmin());
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertTrue(Hash::check('President!Pass123', $user->password));
        $this->artisan('facultyconnect:setup-admin')->assertFailed();
        $this->assertSame(1, User::count());
    }

    public function test_deliberate_promotion_preserves_password_totp_and_related_records(): void
    {
        $user = User::factory()->create($this->identity());
        $payment = Payment::factory()->create(['user_id' => $user->id]);
        $device = app(TrustedDeviceService::class)->issue($user, Request::create('/'));
        $password = $user->password;
        $secret = $user->getRawOriginal('two_factor_secret');
        $this->artisan('facultyconnect:setup-admin')
            ->expectsQuestion('Actual first name', 'Test')->expectsQuestion('Actual last name', 'President')
            ->expectsQuestion('Official email address', 'president@example.test')
            ->expectsQuestion('Employee or faculty ID', 'TEST-PRESIDENT')
            ->expectsQuestion('Department', 'CCIS')->expectsQuestion('Position or designation', 'President')
            ->expectsConfirmation('Promote this exactly matched existing account belonging to Sir Olivo?', 'yes')
            ->assertSuccessful();
        $this->assertTrue($user->fresh()->isAdmin());
        $this->assertSame($password, $user->fresh()->password);
        $this->assertSame($secret, $user->fresh()->getRawOriginal('two_factor_secret'));
        $this->assertSame($user->id, $payment->fresh()->user_id);
        $this->assertNotNull($device->device->fresh()->revoked_at);
        $this->assertSame(1, $user->fresh()->security_version);
    }

    public function test_bootstrap_rejects_conflicting_identifiers_and_unconfirmed_promotion(): void
    {
        User::factory()->create($this->identity());
        User::factory()->create(['faculty_id' => 'OTHER-ID']);
        try {
            app(AdministratorService::class)->bootstrap($this->identity(['faculty_id' => 'OTHER-ID']), null, true);
            $this->fail('Conflicting identifiers must be rejected.');
        } catch (ValidationException) {
            $this->assertSame(0, User::where('role', 'admin')->count());
        }
        $this->expectException(ValidationException::class);
        app(AdministratorService::class)->bootstrap($this->identity(), null, false);
    }

    public function test_temporary_invitation_contains_login_url_and_hashes_expiring_credential(): void
    {
        $this->freezeTime();
        Notification::fake();
        $user = $this->pending();
        $temporary = $this->invite($user);
        $fresh = $user->fresh();
        $mail = Notification::sent($user, AccountInvitation::class)->last()->toMail($user);
        $this->assertTrue(Hash::check($temporary, $fresh->password));
        $this->assertTrue($fresh->must_change_password);
        $this->assertTrue($fresh->temporary_password_expires_at->equalTo(now()->addHours(48)->startOfSecond()));
        $this->assertSame(rtrim(config('app.url'), '/').'/login', $mail->viewData['loginUrl']);
        $html = view('mail.invitation', $mail->viewData)->render();
        $plain = view('mail.invitation-text', $mail->viewData)->render();
        foreach ([$user->email, $temporary, 'Sir Olivo', 'Google Authenticator'] as $text) {
            $this->assertStringContainsString(e($text), $html);
            $this->assertStringContainsString($text, $plain);
        }
        $this->assertStringNotContainsString('/activate/', $html);
        $this->assertStringNotContainsString($temporary, json_encode($fresh));
        $this->assertStringNotContainsString($temporary, json_encode(AuditLog::all()));
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_complete_onboarding_requires_replacement_verified_email_and_totp(): void
    {
        Notification::fake();
        $user = $this->pending();
        $temporary = $this->invite($user);
        $this->post(route('login.store'), ['login' => $user->email, 'password' => $temporary])
            ->assertRedirect(route('onboarding.password'));
        $this->get(route('dashboard'))->assertRedirect(route('onboarding.password'));
        $this->get(route('two-factor.setup'))->assertRedirect(route('onboarding.password'));
        $this->getJson(route('payments.index'))->assertForbidden();
        $this->post(route('onboarding.password.update'), ['password' => $temporary, 'password_confirmation' => $temporary])
            ->assertSessionHasErrors('password');
        $this->post(route('onboarding.password.update'), ['password' => 'Permanent!Pass123', 'password_confirmation' => 'Permanent!Pass123'])
            ->assertRedirect(route('verification.notice'));
        $this->assertFalse(Hash::check($temporary, $user->fresh()->password));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertNull($user->fresh()->temporary_password_expires_at);
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
        $this->post(route('verification.send'))->assertRedirect();
        Notification::assertSentTo($user, VerifyEmail::class);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect(route('two-factor.setup'));
        $this->get(route('two-factor.setup'))->assertOk()->assertSee('Trust this device')->assertSee('name="trust_device"', false);
        $secret = $user->fresh()->two_factor_secret;
        $this->post(route('two-factor.confirm'), ['code' => (new Google2FA)->getCurrentOtp($secret), 'trust_device' => true])
            ->assertRedirect(route('two-factor.recovery-codes'))->assertCookie(config('trusted-device.cookie'));
        $this->get(route('two-factor.recovery-codes'))->assertOk();
        $this->get(route('dashboard'))->assertOk();
        $this->assertSame(User::STATUS_ACTIVE, $user->fresh()->status);
        $this->assertNotNull($user->fresh()->onboarding_completed_at);
        $this->assertDatabaseCount('trusted_devices', 1);
        $this->get(route('two-factor.setup'))->assertRedirect(route('security.show'));
        $this->post(route('two-factor.confirm'), ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertForbidden();
    }

    public function test_resend_invalidates_temporary_password_and_onboarding_sessions(): void
    {
        Notification::fake();
        $user = $this->pending();
        $first = $this->invite($user);
        $this->post(route('login.store'), ['login' => $user->email, 'password' => $first]);
        $version = $user->fresh()->security_version;
        $second = $this->invite($user);
        $this->assertNotSame($first, $second);
        $this->assertFalse(Hash::check($first, $user->fresh()->password));
        $this->assertGreaterThan($version, $user->fresh()->security_version);
        $this->get(route('onboarding.password'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['login' => $user->email, 'password' => $first])->assertSessionHasErrors('login');
        $this->post(route('login.store'), ['login' => $user->email, 'password' => $second])->assertRedirect(route('onboarding.password'));
    }

    public function test_expired_or_unsent_credentials_cannot_login(): void
    {
        Notification::fake();
        $user = $this->pending();
        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password'])->assertSessionHasErrors('login');
        $temporary = $this->invite($user);
        $this->travel(49)->hours();
        $this->post(route('login.store'), ['login' => $user->email, 'password' => $temporary])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_delivery_failure_leaves_retryable_pending_account_without_valid_temporary_password(): void
    {
        $user = $this->pending();
        Notification::shouldReceive('sendNow')->once()->andThrow(new \RuntimeException('Sensitive transport body'));
        $this->assertFalse(app(InvitationService::class)->send($user));
        $this->assertSame('failed', $user->fresh()->invitation_delivery_status);
        $this->assertNull($user->fresh()->temporary_password_expires_at);
        $this->assertSame(User::STATUS_PENDING, $user->fresh()->status);
        $this->assertStringNotContainsString('Sensitive transport body', json_encode(AuditLog::all()));
        Notification::fake();
        $this->invite($user);
        $this->assertSame('accepted', $user->fresh()->invitation_delivery_status);
    }

    public function test_create_without_send_keeps_account_unusable_and_does_not_email(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->post(route('members.store'), [...$this->identity(), 'role' => 'secretary', 'status' => 'pending_activation'])
            ->assertRedirect();
        $member = User::where('faculty_id', 'TEST-PRESIDENT')->firstOrFail();
        $this->assertNull($member->email_verified_at);
        $this->assertNull($member->invitation_sent_at);
        Notification::assertNothingSent();
    }

    public function test_officers_have_scoped_permissions_and_cannot_manage_accounts(): void
    {
        $secretary = User::factory()->create(['role' => User::ROLE_SECRETARY]);
        $treasurer = User::factory()->create(['role' => User::ROLE_TREASURER]);
        $faculty = User::factory()->create(['bio' => 'Private faculty note', 'contact_number' => '09171119999']);
        $payment = Payment::factory()->create(['user_id' => $faculty->id, 'notes' => 'Private financial note']);
        $this->actingAs($secretary)->get(route('directory.index'))->assertOk()
            ->assertDontSee('09171119999')->assertDontSee('Private faculty note');
        $this->get(route('members.show', $faculty))->assertForbidden();
        $this->patch(route('payments.update', $payment), ['status' => 'paid'])->assertForbidden();
        $this->get(route('payments.index'))->assertDontSee('Private financial note');
        $this->actingAs($treasurer)->get(route('payments.index'))->assertOk()->assertSee('Private financial note');
        $this->patch(route('payments.update', $payment), ['status' => 'paid'])->assertRedirect();
        $this->get(route('members.index'))->assertForbidden();
        $this->post(route('members.store'), $this->identity())->assertForbidden();
        $this->get(route('directory.index'))->assertForbidden();
    }

    public function test_last_usable_administrator_cannot_be_demoted_or_deactivated(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        foreach ([['role' => 'faculty'], ['status' => 'deactivated']] as $change) {
            try {
                app(AdministratorService::class)->update($admin, $change, $admin);
                $this->fail('Last administrator change must fail.');
            } catch (ValidationException) {
                $this->assertTrue($admin->fresh()->isAdmin());
                $this->assertTrue($admin->fresh()->isActive());
            }
        }
        $other = User::factory()->create(['role' => User::ROLE_ADMIN]);
        app(AdministratorService::class)->update($admin, ['role' => 'faculty'], $other);
        $this->assertFalse($admin->fresh()->isAdmin());
    }

    public function test_recovery_code_login_cannot_issue_browser_trust(): void
    {
        $user = User::factory()->create();
        $code = app(TwoFactorService::class)->generateRecoveryCodes($user)[0];
        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.challenge.store'), ['code' => $code, 'trust_device' => true])
            ->assertRedirect(route('dashboard'))->assertCookieMissing(config('trusted-device.cookie'));
        $this->assertDatabaseCount('trusted_devices', 0);
    }

    public function test_pending_totp_session_expires_and_cannot_survive_security_invalidation(): void
    {
        $user = User::factory()->create();
        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password']);
        app(SessionManager::class)->invalidate($user);
        $this->post(route('two-factor.challenge.store'), ['code' => (new Google2FA)->getCurrentOtp($user->two_factor_secret)])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_operator_recovery_requires_confirmation_and_resets_two_factor(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->artisan('facultyconnect:recover-admin')
            ->expectsQuestion('Administrator official email', $admin->email)
            ->expectsQuestion('Administrator employee ID', $admin->faculty_id)
            ->expectsChoice('Recovery action', 'reset-2fa', ['password-reset', 'reset-2fa', 'resend-invitation'])
            ->expectsConfirmation('Perform reset-2fa for administrator record #'.$admin->id.'?', 'yes')
            ->assertSuccessful();
        $this->assertNull($admin->fresh()->two_factor_secret);
        $this->assertNull($admin->fresh()->two_factor_recovery_codes);
        $this->assertGreaterThan(0, $admin->fresh()->security_version);
        $this->assertDatabaseHas('audit_logs', ['action' => 'administrator.recovery_by_operator']);
    }

    public function test_secretary_can_manage_announcements_but_not_roles(): void
    {
        $secretary = User::factory()->create(['role' => User::ROLE_SECRETARY]);
        $this->actingAs($secretary)->post(route('announcements.store'), [
            'title' => 'Club minutes', 'category' => 'Meeting', 'audience' => 'All Faculty',
            'priority' => 'normal', 'body' => 'Secretary publishes approved club minutes.',
            'published_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('announcements', ['title' => 'Club minutes']);
        $this->getJson(route('members.index'))->assertForbidden();
    }

    public function test_stale_instances_cannot_replay_totp_or_recovery_codes(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorService::class);
        $codes = $service->generateRecoveryCodes($user);
        $stale = $user->fresh();
        $code = (new Google2FA)->getCurrentOtp($user->two_factor_secret);
        $this->assertTrue($service->verifyTotp($user, $code));
        $this->assertFalse($service->verifyTotp($stale, $code));
        $stale = $user->fresh();
        $this->assertTrue($service->useRecoveryCode($user, $codes[0]));
        $this->assertFalse($service->useRecoveryCode($stale, $codes[0]));
    }

    public function test_browser_trust_has_fixed_expiry_and_remember_flag_does_not_skip_password(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/', server: ['HTTP_USER_AGENT' => 'TestBrowser']);
        $issued = app(TrustedDeviceService::class)->issue($user, $request);
        $expiry = $issued->device->expires_at;
        $this->travel(19)->days();
        $response = $this->withHeader('User-Agent', 'TestBrowser')->withCookie(config('trusted-device.cookie'), $issued->cookie->getValue())
            ->post(route('login.store'), ['login' => $user->email, 'password' => 'password', 'remember' => true])
            ->assertRedirect(route('dashboard'));
        $this->assertTrue($issued->device->fresh()->expires_at->equalTo($expiry));
        $cookie = $response->getCookie(config('trusted-device.cookie'))->getValue();
        $this->post(route('logout'));
        $this->travel(2)->days();
        $this->withCookie(config('trusted-device.cookie'), $cookie)
            ->post(route('login.store'), ['login' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_role_changes_require_password_confirmation_and_last_admin_is_protected_over_http(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $data = ['first_name' => $admin->first_name, 'last_name' => $admin->last_name,
            'email' => $admin->email, 'faculty_id' => $admin->faculty_id, 'department' => $admin->department,
            'position' => $admin->position, 'status' => 'active', 'role' => 'faculty'];
        $this->actingAs($admin)->patch(route('members.update', $admin), $data)->assertSessionHasErrors('current_password');
        $this->patch(route('members.update', $admin), [...$data, 'current_password' => 'password'])->assertSessionHasErrors('status');
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_conversion_refuses_unreviewed_plaintext_and_damaged_envelopes(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['bio' => 'Legacy value for review']);
        $this->artisan('facultyconnect:protect-data')->assertFailed();
        $this->assertSame('Legacy value for review', DB::table('users')->where('id', $user->id)->value('bio'));
        DB::table('users')->where('id', $user->id)->update(['bio' => 'eyJpdiI6BROKEN']);
        $this->artisan('facultyconnect:protect-data', ['--allow-plaintext' => true])->assertFailed();
        $this->assertSame('eyJpdiI6BROKEN', DB::table('users')->where('id', $user->id)->value('bio'));
    }

    public function test_existing_accounts_keep_credentials_but_require_missing_email_verification(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $oldPassword = $user->password;
        $oldSecret = $user->getRawOriginal('two_factor_secret');
        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('verification.notice'));
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
        $this->assertSame($oldPassword, $user->fresh()->password);
        $this->assertSame($oldSecret, $user->fresh()->getRawOriginal('two_factor_secret'));
    }
}
