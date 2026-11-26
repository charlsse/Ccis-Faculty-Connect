<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AcademicRosterProvisioner;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AcademicRosterProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private function manifest(): array
    {
        return ['source_sheet' => 'FACULTY ROSTER', 'source_sha256' => str_repeat('a', 64),
            'records' => array_map(fn ($i) => ['roster_key' => sprintf('CCIS-AY2627-%03d', $i),
                'first_name' => 'Synthetic'.$i, 'middle_name' => 'Q.', 'last_name' => 'Faculty',
                'source_name' => 'Synthetic'.$i.' Q. Faculty', 'department' => 'Computer Science Program',
                'position' => 'Faculty Member', 'employment_classification' => $i <= 30 ? 'FULL-TIME' : 'PART-TIME'], range(1, 41))];
    }

    private function import(?User $admin = null): array
    {
        $credentials = [];
        app(AcademicRosterProvisioner::class)->provision($this->manifest(), $admin ?? User::factory()->create(['role' => 'admin']),
            function ($rows) use (&$credentials) { $credentials = $rows; });
        return $credentials;
    }

    public function test_bulk_import_preserves_existing_accounts_and_is_idempotent_without_messages_or_financial_obligations(): void
    {
        Notification::fake(); Mail::fake();
        $existing = User::factory()->create(['first_name' => 'Synthetic1', 'middle_name' => 'Quinn', 'last_name' => 'Faculty', 'role' => 'treasurer']);
        $before = $existing->fresh()->getRawOriginal();
        $admin = User::factory()->create(['role' => 'admin']);
        $credentials = $this->import($admin);
        $this->assertCount(40, $credentials);
        $this->assertCount(40, array_unique(array_column($credentials, 'temporary_password')));
        $this->assertSame($before, $existing->fresh()->getRawOriginal());
        foreach ($credentials as $row) {
            $user = User::where('faculty_id', $row['login_id'])->firstOrFail();
            $this->assertTrue(Hash::check($row['temporary_password'], $user->password));
            $this->assertTrue($user->isAcademicRosterDemo());
            $this->assertNull($user->email);
            $this->assertNull($user->email_verified_at);
            $this->assertNull($user->two_factor_confirmed_at);
            $this->assertSame('faculty', $user->role);
        }
        // Audit/snapshot tooling can request stringified PDO results; IDs still identify the same rows.
        $pdo = DB::connection()->getPdo();
        // SQLite supports setting this attribute but not reading it. Restore Laravel's default.
        $stringifyBefore = config('database.connections.sqlite.options', [])[\PDO::ATTR_STRINGIFY_FETCHES] ?? false;
        try {
            $pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, true);
            $this->assertSame([], $this->import($admin));
        } finally {
            $pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, $stringifyBefore);
        }
        $this->assertDatabaseCount('academic_roster_accounts', 40);
        $this->assertDatabaseCount('water_members', 0);
        Notification::assertNothingSent(); Mail::assertNothingSent();
    }

    public function test_roster_login_requires_password_replacement_and_real_totp_enrollment_then_totp_on_next_login(): void
    {
        Notification::fake(); Mail::fake();
        $credential = $this->import()[0];
        $user = User::where('faculty_id', $credential['login_id'])->firstOrFail();
        $this->post(route('login.store'), ['login' => $credential['login_id'], 'password' => $credential['temporary_password']])
            ->assertRedirect(route('onboarding.password'));
        $this->get(route('dashboard'))->assertRedirect(route('onboarding.password'));
        $newPassword = 'Disposable!Permanent12345';
        $data = ['password' => $newPassword, 'password_confirmation' => $newPassword, 'privacy_acknowledged' => 1, 'terms_accepted' => 1];
        foreach (app(\App\Services\PrivacyDocumentation::class)->acknowledgmentPolicies() ?? [] as $kind => $policy) {
            $data[$kind.'_version'] = $policy['version']; $data[$kind.'_sha256'] = $policy['sha256'];
        }
        $this->post(route('onboarding.password.update'), $data)->assertRedirect(route('two-factor.setup'));
        $this->get(route('dashboard'))->assertRedirect(route('two-factor.setup'));
        $this->get(route('two-factor.setup'))->assertOk();
        $user->refresh();
        $uri = app(TwoFactorService::class)->provisioningUri($user);
        $this->assertStringContainsString($user->faculty_id, urldecode($uri));
        $this->post(route('two-factor.confirm'), ['code' => 'invalid'])->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->post(route('two-factor.confirm'), ['code' => (new Google2FA)->getCurrentOtp($user->two_factor_secret)])
            ->assertRedirect(route('two-factor.recovery-codes'));
        $this->get(route('dashboard'))->assertOk();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->post(route('logout'));
        $this->post(route('login.store'), ['login' => $credential['login_id'], 'password' => $newPassword])
            ->assertRedirect(route('two-factor.challenge'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        Notification::assertNothingSent(); Mail::assertNothingSent();
    }

    public function test_no_self_service_or_standard_account_email_exception_and_non_admin_import_rolls_back(): void
    {
        $faculty = User::factory()->unverified()->create();
        $this->assertSame('verification.notice', $faculty->onboardingRoute());
        $this->post('/register', ['email' => null, 'academic_roster' => true])->assertNotFound();
        $this->actingAs(User::factory()->create())->post(route('members.store'), ['email' => null, 'academic_roster' => true])->assertForbidden();
        $before = User::count();
        try { $this->import($faculty); $this->fail('Faculty import must be denied'); }
        catch (\Illuminate\Validation\ValidationException) { $this->assertSame($before, User::count()); }
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('members.store'), ['email' => null, 'academic_roster' => true])->assertSessionHasErrors('email');
        $faculty->forceFill(['faculty_id' => 'CCIS-DEMO-2627-999'])->save();
        $this->assertFalse($faculty->isAcademicRosterDemo());
        $this->assertSame('verification.notice', $faculty->onboardingRoute());
    }

    public function test_report_failure_rolls_back_accounts_and_audit_and_conflicts_stop_import(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        try {
            app(AcademicRosterProvisioner::class)->provision($this->manifest(), $admin, fn () => throw new \RuntimeException('report unavailable'));
            $this->fail('Expected report failure');
        } catch (\RuntimeException $e) { $this->assertSame('report unavailable', $e->getMessage()); }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('academic_roster_accounts', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        User::factory()->create(['first_name' => 'Synthetic1', 'last_name' => 'Faculty']);
        User::factory()->create(['first_name' => 'Synthetic1', 'last_name' => 'Faculty']);
        $this->assertSame('conflict', app(AcademicRosterProvisioner::class)->review($this->manifest())[0]['outcome']);
    }

    public function test_enrolled_roster_faculty_cannot_manage_accounts_or_treasury_or_see_other_private_records(): void
    {
        $credentials = $this->import();
        $faculty = User::where('faculty_id', $credentials[0]['login_id'])->firstOrFail();
        // Fixture represents completed enrollment; actual enrollment is tested above.
        $faculty->forceFill(['must_change_password' => false, 'password_replaced_at' => now(),
            'two_factor_secret' => app(TwoFactorService::class)->generateSecret(), 'two_factor_confirmed_at' => now()])->save();
        $other = User::where('faculty_id', $credentials[1]['login_id'])->firstOrFail();
        \App\Models\Payment::factory()->create(['user_id' => $other->id, 'description' => 'Other private payment sentinel']);
        $other->notify(new \App\Notifications\PortalNotification(['category' => 'security', 'message' => 'Other private notification sentinel']));
        $this->actingAs($faculty)->get(route('members.index'))->assertForbidden();
        $this->get(route('members.show', $other))->assertForbidden();
        $this->get(route('wallet.index'))->assertForbidden();
        $this->get(route('payments.index'))->assertOk()->assertDontSee('Other private payment sentinel');
        $this->get(route('notifications.feed'))->assertOk()->assertDontSee('Other private notification sentinel');
        $this->get(route('profile.show'))->assertOk()->assertSee('Generated roster login ID');
        $this->get(route('directory.index'))->assertForbidden();
        $this->post(route('verification.send'))->assertStatus(422);
        $admin = User::where('role', 'admin')->firstOrFail();
        $this->actingAs($admin)->get(route('directory.index', ['search' => $faculty->first_name]))->assertOk()->assertSee($faculty->full_name);
    }
}
