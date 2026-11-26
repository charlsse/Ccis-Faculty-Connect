<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_user_can_view_published_announcements(): void
    {
        $faculty = User::factory()->create([
            'role' => User::ROLE_FACULTY,
        ]);

        $published = Announcement::factory()->create([
            'title' => 'Published CCIS Advisory',
            'published_at' => now()->subHour(),
        ]);

        Announcement::factory()->create([
            'title' => 'Draft Officer Note',
            'published_at' => null,
        ]);

        $this->actingAs($faculty)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertSee($published->title)
            ->assertDontSee('Draft Officer Note');
    }

    public function test_admin_can_create_update_and_delete_announcement(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAs($admin);

        $this->post(route('announcements.store'), [
            'title' => 'CCIS General Assembly',
            'category' => 'Meeting',
            'audience' => 'All Faculty',
            'priority' => 'important',
            'body' => 'Faculty members are invited to attend the CCIS Faculty Club general assembly.',
            'published_at' => now()->format('Y-m-d H:i:s'),
            'is_pinned' => '1',
        ])->assertRedirect();

        $announcement = Announcement::where('title', 'CCIS General Assembly')->firstOrFail();
        $this->assertTrue($announcement->is_pinned);

        $this->patch(route('announcements.update', $announcement), [
            'title' => 'CCIS Faculty Club General Assembly',
            'category' => 'Meeting',
            'audience' => 'All Faculty',
            'priority' => 'urgent',
            'body' => 'Updated assembly details for CCIS faculty members.',
            'published_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'CCIS Faculty Club General Assembly',
            'priority' => 'urgent',
            'is_pinned' => false,
        ]);

        $this->delete(route('announcements.destroy', $announcement))->assertRedirect(route('announcements.index'));
        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
    }

    public function test_faculty_user_cannot_manage_announcements(): void
    {
        $faculty = User::factory()->create([
            'role' => User::ROLE_FACULTY,
        ]);

        $announcement = Announcement::factory()->create([
            'published_at' => now()->subHour(),
        ]);

        $this->actingAs($faculty)
            ->post(route('announcements.store'), [
                'title' => 'Unauthorized',
                'category' => 'General',
                'audience' => 'All Faculty',
                'priority' => 'normal',
                'body' => 'This should not be created.',
            ])
            ->assertForbidden();

        $this->actingAs($faculty)
            ->delete(route('announcements.destroy', $announcement))
            ->assertForbidden();
    }
}
