<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use App\Models\WaterCollection;
use App\Models\WaterPaymentAccount;
use App\Models\WaterNotice;
use App\Services\AuditLogger;
use App\Services\PortalNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PortalNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_announcements_respect_audience_schedule_expiry_and_changes(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty', 'created_at' => now()->subDay()]);
        $treasurer = User::factory()->create(['role' => 'treasurer', 'created_at' => now()->subDay()]);
        $all = Announcement::factory()->create(['audience' => 'All Faculty', 'published_at' => now()]);
        $restricted = Announcement::factory()->create(['audience' => 'Treasurers', 'published_at' => now()]);
        $scheduled = Announcement::factory()->create(['audience' => 'Faculty', 'published_at' => now()->addHour()]);
        Announcement::factory()->create(['audience' => 'All Faculty', 'published_at' => now()->subDay(), 'expires_at' => now()->subMinute()]);
        Announcement::factory()->create(['audience' => 'Unknown legacy label', 'published_at' => now()]);
        $service = app(PortalNotifications::class);
        $this->assertCount(1, $service->entries($faculty));
        $this->assertCount(2, $service->entries($treasurer));
        $this->actingAs($faculty)->get(route('announcements.show', $restricted))->assertNotFound();
        $this->get(route('announcements.index'))->assertDontSee($restricted->title);
        $this->get(route('dashboard'))->assertDontSee($restricted->title);
        $this->travel(61)->minutes();
        $this->assertCount(2, $service->entries($faculty));
        $this->assertCount(2, $service->entries($faculty)); // repeated refresh is idempotent
        $scheduled->update(['audience' => 'Treasurers']);
        $this->assertCount(1, $service->entries($faculty));
        $all->update(['expires_at' => now()->subSecond()]);
        $this->assertCount(0, $service->entries($faculty));
    }

    public function test_notification_read_actions_are_owned_and_links_recheck_visibility(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $service = app(PortalNotifications::class);
        $service->security($owner, 'Your account password was changed.');
        $id = $owner->notifications()->firstOrFail()->id;
        $this->actingAs($other)->post(route('notifications.read', $id), ['open' => 1])->assertNotFound();
        $this->actingAs($owner)->get(route('notifications.index'))->assertOk()->assertSee('Unread');
        $this->post(route('notifications.read', $id), ['open' => 1])->assertRedirect(route('security.show'));
        $this->assertNotNull($owner->notifications()->findOrFail($id)->read_at);
        $service->security($owner, 'A trusted device was revoked.');
        $this->post(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $owner->unreadNotifications()->count());
        $this->get(route('notifications.index', ['filter' => 'collections']))->assertOk()->assertSee('No notifications in this category.');
    }

    public function test_collection_publish_notifies_only_assigned_users_and_preserves_water_read_state(): void
    {
        config(['water.email_enabled' => false]);
        $officer = User::factory()->create(['role' => 'treasurer']);
        $assigned = User::factory()->create();
        $unassigned = User::factory()->create();
        $account = WaterPaymentAccount::create(['label' => 'Test', 'account_name' => 'Test', 'masked_account_number' => '***1234', 'qr_image_path' => 'test.png', 'is_active' => true, 'created_by' => $officer->id]);
        $this->actingAs($officer)->post(route('water.store'), ['title' => 'September contribution', 'period' => '2026-09', 'amount' => '125.00', 'due_date' => '2026-10-15', 'payment_account_id' => $account->id, 'assignment' => 'selected', 'members' => [$assigned->id]])->assertSessionHasNoErrors();
        $collection = WaterCollection::firstOrFail();
        $service = app(PortalNotifications::class);
        $this->assertCount(0, $service->entries($assigned));
        $this->patch(route('water.transition', $collection), ['status' => 'published', 'confirmed' => 1])->assertSessionHasNoErrors();
        $entry = $service->entries($assigned)->sole();
        $this->assertStringContainsString('125.00', $entry['message']);
        $this->assertStringContainsString('October 15, 2026', $entry['message']);
        $this->assertCount(0, $service->entries($unassigned));
        $this->actingAs($assigned)->post(route('notifications.read', $entry['id']), ['open' => 1])->assertRedirect(route('water.show', $collection));
        $this->assertNotNull(WaterNotice::firstOrFail()->read_at);
        $this->actingAs($unassigned)->post(route('notifications.read', $entry['id']))->assertNotFound();
        $this->get(route('water.show', $collection))->assertForbidden();
        $collection->refresh()->update(['status' => 'draft']);
        $this->actingAs($assigned)->post(route('notifications.read', $entry['id']))->assertNotFound();
    }

    public function test_password_failures_notify_at_threshold_only_without_credentials(): void
    {
        $user = User::factory()->create();
        RateLimiter::clear('login:'.hash('sha256', strtolower($user->email).'|127.0.0.1'));
        for ($i = 0; $i < 4; $i++) {
            $this->post(route('login.store'), ['login' => $user->email, 'password' => 'Wrong-secret-123'])->assertSessionHasErrors('login');
        }
        $this->assertSame(0, $user->notifications()->count());
        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'Wrong-secret-123'])->assertSessionHasErrors('login');
        $this->assertSame(1, $user->notifications()->count());
        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'Wrong-secret-123'])->assertSessionHasErrors('login');
        $this->assertSame(1, $user->notifications()->count());
        $this->assertStringNotContainsString('Wrong-secret', $user->notifications()->first()->data['message']);
        $this->assertStringContainsString('Multiple unsuccessful', $user->notifications()->first()->data['message']);
    }

    public function test_security_events_use_fixed_safe_messages_and_ignore_ordinary_failures(): void
    {
        $user = User::factory()->create();
        $audit = app(AuditLogger::class);
        $audit->record('login.failed', $user, details: ['password' => 'SECRET']);
        $this->assertSame(0, $user->notifications()->count());
        foreach (['password.changed', 'password.reset', 'trusted_device.revoked', 'two_factor.reset_by_admin'] as $event) {
            $audit->record($event, $user, details: ['token' => 'SECRET']);
        }
        $this->assertSame(4, $user->notifications()->count());
        $this->assertStringNotContainsString('SECRET', $user->notifications()->get()->toJson());
    }

    public function test_bell_caps_badge_and_repeated_security_alerts_are_limited(): void
    {
        $user = User::factory()->create();
        $service = app(PortalNotifications::class);
        for ($i = 0; $i < 12; $i++) $service->security($user, 'Security event '.$i);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('9+')->assertSee('Notifications, 12 unread');
        $service->security($user, 'Repeated verification failures.', true);
        $service->security($user, 'Repeated verification failures.', true);
        $this->assertSame(13, $user->notifications()->count());
        $this->travel(61)->minutes();
        $service->security($user, 'Repeated verification failures.', true);
        $this->assertSame(14, $user->notifications()->count());
    }

    public function test_two_factor_threshold_produces_one_neutral_alert(): void
    {
        $user = User::factory()->create();
        RateLimiter::clear('otp:'.$user->id.'|127.0.0.1');
        $this->withSession(['pending_login_user_id' => $user->id, 'pending_login_version' => $user->security_version, 'pending_login_expires' => now()->addMinutes(10)->timestamp]);
        for ($i = 0; $i < 4; $i++) $this->post(route('two-factor.challenge.store'), ['code' => 'invalid-recovery'])->assertSessionHasErrors('code');
        $this->assertSame(0, $user->notifications()->count());
        $this->post(route('two-factor.challenge.store'), ['code' => 'invalid-recovery'])->assertSessionHasErrors('code');
        $this->assertSame(1, $user->notifications()->count());
        $this->post(route('two-factor.challenge.store'), ['code' => 'invalid-recovery'])->assertSessionHasErrors('code');
        $this->assertSame(1, $user->notifications()->count());
        $this->assertStringContainsString('unsuccessful two-factor verification attempts', $user->notifications()->first()->data['message']);
    }

    public function test_all_roles_keep_account_actions_outside_sidebar_and_preserve_rbac(): void
    {
        foreach (['admin', 'secretary', 'treasurer', 'faculty', 'staff'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
            preg_match('/<aside.*?<\/aside>/s', $response->getContent(), $match);
            foreach (['My Profile', 'Security Settings', 'Logout'] as $label) $this->assertStringNotContainsString($label, $match[0]);
            $this->assertSame($role === 'admin', str_contains($match[0], 'Content Manager'));
            $this->assertSame(in_array($role, ['admin', 'secretary']), str_contains($match[0], 'Faculty Directory'));
            $response->assertSee(route('logout.forget-device'))->assertSee(route('logout'))->assertSee('Open account menu');
        }
    }
}
