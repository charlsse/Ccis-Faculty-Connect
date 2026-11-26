<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_regular_users_cannot_manage_accounts(): void
    {
        $faculty = User::factory()->create();
        $target = User::factory()->create();

        $this->get(route('members.index'))->assertRedirect(route('login'));
        $this->actingAs($faculty)->get(route('members.index'))->assertForbidden();
        $this->actingAs($faculty)->post(route('members.store'), $this->validAccountData())->assertForbidden();
        $this->actingAs($faculty)->patch(route('members.update', $target), $this->validAccountData())->assertForbidden();
    }

    public function test_admin_can_create_account_and_invitation_is_sent(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post(route('members.store'), $this->validAccountData(['send_invitation' => true]))
            ->assertRedirect();

        $user = User::where('email', 'new.faculty@umak.edu.ph')->firstOrFail();
        $this->assertSame(User::STATUS_PENDING, $user->status);
        $this->assertTrue($user->must_change_password);
        $this->assertNotNull($user->temporary_password_expires_at);
        Notification::assertSentTo($user, AccountInvitation::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.created', 'subject_user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invitation.sent', 'subject_user_id' => $user->id]);
    }

    public function test_duplicate_email_and_employee_id_are_rejected(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        User::factory()->create(['faculty_id' => 'UMAK-NEW-100', 'email' => 'new.faculty@umak.edu.ph']);

        $this->actingAs($admin)->post(route('members.store'), $this->validAccountData())
            ->assertSessionHasErrors(['faculty_id', 'email']);
    }

    public function test_retired_activation_links_are_unavailable(): void
    {
        $this->get('/activate/1?token=obsolete')->assertNotFound();
        $this->post('/activate/1', ['token' => 'obsolete'])->assertNotFound();
    }

    public function test_deactivation_invalidates_existing_sessions(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'member-session',
            'user_id' => $member->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test browser',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        $data = $this->validAccountData([
            'faculty_id' => $member->faculty_id,
            'email' => $member->email,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'role' => $member->role,
            'status' => User::STATUS_DEACTIVATED,
            'current_password' => 'password',
        ]);
        $this->actingAs($admin)->patch(route('members.update', $member), $data)->assertRedirect();

        $this->assertSame(User::STATUS_DEACTIVATED, $member->fresh()->status);
        $this->assertDatabaseMissing('sessions', ['id' => 'member-session']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.deactivated', 'subject_user_id' => $member->id]);
    }

    public function test_user_can_edit_permitted_profile_fields_but_not_role_status_or_identifier(): void
    {
        $faculty = User::factory()->create([
            'faculty_id' => 'UMAK-ORIGINAL',
            'role' => User::ROLE_FACULTY,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($faculty)->patch(route('profile.update'), [
            'first_name' => 'Updated',
            'middle_name' => 'Profile',
            'last_name' => 'Member',
            'email' => $faculty->email,
            'department' => 'Information Technology',
            'position' => 'Instructor',
            'contact_number' => '09175550123',
            'bio' => 'Updated profile.',
            'faculty_id' => 'CHANGED',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_DEACTIVATED,
        ])->assertRedirect();

        $faculty->refresh();
        $this->assertSame('Updated', $faculty->first_name);
        $this->assertSame('UMAK-ORIGINAL', $faculty->faculty_id);
        $this->assertSame(User::ROLE_FACULTY, $faculty->role);
        $this->assertSame(User::STATUS_ACTIVE, $faculty->status);
    }

    public function test_email_change_requires_password_and_verification(): void
    {
        Notification::fake();
        $faculty = User::factory()->create(['email' => 'old@umak.edu.ph', 'password' => Hash::make('Faculty!Pass123')]);
        $data = $this->profileData($faculty, ['email' => 'new@umak.edu.ph']);

        $this->actingAs($faculty)->patch(route('profile.update'), $data)->assertSessionHasErrors('current_password');
        $this->actingAs($faculty)->patch(route('profile.update'), [...$data, 'current_password' => 'Faculty!Pass123'])->assertRedirect();

        $faculty->refresh();
        $this->assertSame('old@umak.edu.ph', $faculty->email);
        $this->assertSame('new@umak.edu.ph', $faculty->pending_email);
    }

    public function test_profile_photo_validation_rejects_executable_uploads(): void
    {
        Storage::fake('public');
        $faculty = User::factory()->create();

        $this->actingAs($faculty)->patch(route('profile.update'), [
            ...$this->profileData($faculty),
            'profile_photo' => UploadedFile::fake()->create('payload.php', 2, 'application/x-php'),
        ])->assertSessionHasErrors('profile_photo');
    }

    private function validAccountData(array $overrides = []): array
    {
        return array_replace([
            'faculty_id' => 'UMAK-NEW-100',
            'first_name' => 'New',
            'middle_name' => '',
            'last_name' => 'Faculty',
            'email' => 'new.faculty@umak.edu.ph',
            'department' => 'Computer Science',
            'position' => 'Instructor',
            'contact_number' => '09171234567',
            'bio' => '',
            'role' => User::ROLE_FACULTY,
            'status' => User::STATUS_PENDING,
        ], $overrides);
    }

    private function profileData(User $user, array $overrides = []): array
    {
        return array_replace([
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'department' => $user->department,
            'position' => $user->position,
            'contact_number' => $user->contact_number,
            'bio' => $user->bio,
        ], $overrides);
    }
}
