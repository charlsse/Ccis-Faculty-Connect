<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\SiteContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    private function icon(string $name = 'icon.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, file_get_contents(public_path('images/site-icon.png')));
    }

    public function test_default_icon_and_shared_theme_assets_are_public(): void
    {
        $this->get(route('site.icon'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('landing'))->assertOk()->assertSee('facultyconnect.theme')->assertSee('data-theme-toggle', false)->assertSee('data-fc-carousel', false);
        $this->get(route('login'))->assertOk()->assertSee('appearance.js')->assertSee('site-icon.png');
    }

    public function test_admin_upload_is_versioned_and_landing_edits_do_not_reset_it(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('admin.content.branding'), ['site_icon' => $this->icon()])->assertRedirect()->assertSessionHasNoErrors();
        $first = SiteContent::branding();
        Storage::disk('public')->assertExists($first['icon_path']);
        $this->assertMatchesRegularExpression('/^site-branding\/[a-f0-9-]+\.png$/', $first['icon_path']);
        $this->get(route('site.icon', ['v' => $first['version']]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        SiteContent::saveLanding(SiteContent::defaults());
        $this->assertSame($first, SiteContent::branding());
        $this->post(route('admin.content.branding'), ['site_icon' => $this->icon('replacement.png')])->assertSessionHasNoErrors();
        $this->assertNotSame($first['version'], SiteContent::branding()['version']);
        $this->get(route('admin.content.edit'))->assertOk()->assertSee('Website icon')->assertSee('site_icon');
    }

    public function test_restore_uses_default_icon_and_retains_previous_upload(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('admin.content.branding'), ['site_icon' => $this->icon()])->assertSessionHasNoErrors();
        $previous = SiteContent::branding();
        $this->post(route('admin.content.branding'), ['restore_icon' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull(SiteContent::branding()['icon_path']);
        $this->assertNotSame($previous['version'], SiteContent::branding()['version']);
        Storage::disk('public')->assertExists($previous['icon_path']);
        $this->get(route('site.icon'))->assertOk();
    }

    public function test_invalid_icon_uploads_are_rejected_without_changing_branding(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $original = SiteContent::branding();
        foreach ([
            UploadedFile::fake()->createWithContent('script.png', '<?php echo "not an image";'),
            $this->icon('icon.php'),
            UploadedFile::fake()->createWithContent('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->createWithContent('small.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')),
            UploadedFile::fake()->create('large.png', 513, 'image/png'),
        ] as $file) {
            $this->post(route('admin.content.branding'), ['site_icon' => $file])->assertSessionHasErrors('site_icon');
            $this->assertSame($original, SiteContent::branding());
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_non_admin_roles_cannot_change_branding(): void
    {
        foreach (['faculty', 'secretary', 'treasurer', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->post(route('admin.content.branding'), ['restore_icon' => '1'])->assertForbidden();
        }
        $this->assertDatabaseMissing('site_contents', ['key' => 'site.branding']);
    }

    public function test_dashboard_preview_excludes_drafts_future_and_expired_announcements(): void
    {
        $user = User::factory()->create();
        Announcement::factory()->create(['title' => 'Draft hidden', 'published_at' => null]);
        Announcement::factory()->create(['title' => 'Future hidden', 'published_at' => now()->addDay()]);
        Announcement::factory()->create(['title' => 'Expired hidden', 'expires_at' => now()->subMinute()]);
        $pinned = Announcement::factory()->create(['title' => 'Pinned visible', 'is_pinned' => true]);
        Announcement::factory()->count(4)->create(['published_at' => now()->subMinute()]);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertDontSee('Draft hidden')->assertDontSee('Future hidden')->assertDontSee('Expired hidden')
            ->assertSee('Pinned visible')->assertViewHas('announcements', fn ($items) => $items->count() === 3 && $items->first()->id === $pinned->id);
    }

    public function test_dashboard_empty_and_single_announcement_states(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->assertSee('No new announcements.')->assertDontSee('data-fc-pause', false);
        Announcement::factory()->create(['title' => str_repeat('Long title ', 14), 'body' => str_repeat('Long content. ', 250)]);
        $this->get(route('dashboard'))->assertOk()->assertSee('View announcement')->assertDontSee('data-fc-pause', false);
    }
}

