<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_complete_mandatory_two_factor_setup_and_receives_recovery_codes(): void
    {
        $secret = app(TwoFactorService::class)->generateSecret();
        $user = User::factory()->create([
            'status' => User::STATUS_PENDING,
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'password_replaced_at' => now(),
        ]);

        $this->actingAs($user)->get(route('two-factor.setup'))
            ->assertOk()
            ->assertSee('Manual setup key')
            ->assertSee($secret);

        $this->post(route('two-factor.confirm'), [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertRedirect(route('two-factor.recovery-codes'));

        $user->refresh();
        $this->assertSame(User::STATUS_ACTIVE, $user->status);
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertCount(8, $user->two_factor_recovery_codes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'two_factor.enabled', 'subject_user_id' => $user->id]);
    }

    public function test_recovery_code_regeneration_requires_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Faculty!Pass123')]);

        $this->actingAs($user)->post(route('security.recovery-codes.regenerate'), [
            'current_password' => 'wrong',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->post(route('security.recovery-codes.regenerate'), [
            'current_password' => 'Faculty!Pass123',
        ])->assertRedirect(route('two-factor.recovery-codes'));
        $this->assertCount(8, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_password_change_requires_current_password_and_revokes_other_sessions(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Faculty!Pass123')]);
        DB::table('sessions')->insert([
            'id' => 'another-session', 'user_id' => $user->id, 'ip_address' => null,
            'user_agent' => null, 'payload' => 'test', 'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)->put(route('security.password.update'), [
            'current_password' => 'wrong',
            'password' => 'Changed!Pass123',
            'password_confirmation' => 'Changed!Pass123',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put(route('security.password.update'), [
            'current_password' => 'Faculty!Pass123',
            'password' => 'Changed!Pass123',
            'password_confirmation' => 'Changed!Pass123',
        ])->assertRedirect();
        $this->assertTrue(Hash::check('Changed!Pass123', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'another-session']);
    }

    public function test_only_admin_with_password_confirmation_can_reset_another_users_two_factor(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'password' => Hash::make('Admin!Pass123')]);
        $faculty = User::factory()->create();

        $this->actingAs($faculty)->delete(route('members.two-factor.reset', $admin), [
            'current_password' => 'password',
        ])->assertForbidden();

        $this->actingAs($admin)->delete(route('members.two-factor.reset', $faculty), [
            'current_password' => 'wrong',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($admin)->delete(route('members.two-factor.reset', $faculty), [
            'current_password' => 'Admin!Pass123',
        ])->assertRedirect();
        $faculty->refresh();
        $this->assertNull($faculty->two_factor_secret);
        $this->assertNull($faculty->two_factor_confirmed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'two_factor.reset_by_admin', 'subject_user_id' => $faculty->id]);
    }

    public function test_audit_logger_discards_sensitive_detail_values(): void
    {
        $user = User::factory()->create();
        app(AuditLogger::class)->record('security.test', $user, $user, [
            'safe' => 'kept',
            'password' => 'never-store-this',
            'otp_value' => '123456',
            'nested' => ['recovery_codes' => ['secret-code']],
            'contact_number' => '09171234567',
            'payment_reference' => 'PRIVATE-REFERENCE',
            'confidential_notes' => 'Do not log this.',
        ]);

        $details = AuditLog::firstOrFail()->details;
        $this->assertSame(['safe' => 'kept', 'nested' => []], $details);
        $this->assertStringNotContainsString('never-store-this', json_encode($details));
        $this->assertStringNotContainsString('123456', json_encode($details));
        $this->assertStringNotContainsString('09171234567', json_encode($details));
        $this->assertStringNotContainsString('PRIVATE-REFERENCE', json_encode($details));
        $this->assertStringNotContainsString('Do not log this.', json_encode($details));
    }
}
