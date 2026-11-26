<?php

namespace Tests\Feature;

use App\Models\SiteContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContentManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_users_cannot_access_content_manager(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => User::ROLE_FACULTY,
        ]));

        $this->get(route('admin.content.edit'))->assertForbidden();
        $this->post(route('admin.content.update'))->assertForbidden();
    }

    public function test_admin_can_update_landing_page_content(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]));

        $response = $this->post(route('admin.content.update'), [
            'carousel_slides' => [
                [
                    'eyebrow' => 'Admin Update',
                    'title' => 'Editable CCIS landing content',
                    'body' => 'The landing page now comes from the content manager.',
                    'existing_image_path' => '',
                ],
            ],
            'portal_preview' => [
                'metrics' => [
                    ['title' => 'Managed', 'body' => 'Content blocks'],
                ],
                'bars' => [75],
            ],
            'about' => [
                'eyebrow' => 'Managed About',
                'title' => 'A managed portal introduction.',
                'body' => 'Admins can edit this section without touching Blade files.',
                'strip' => ['Managed badge'],
            ],
            'features' => [
                ['icon' => 'CM', 'title' => 'Content Manager', 'status' => 'Available'],
            ],
            'benefits' => [
                ['title' => 'Editable', 'body' => 'Landing copy can be managed by admins.'],
            ],
            'cta' => [
                'title' => 'Managed call to action',
                'login_label' => 'Sign in',
                'register_label' => 'Join now',
            ],
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('status', 'Landing page content has been updated.');

        $this->assertDatabaseHas('site_contents', [
            'key' => 'landing.carousel_slides',
        ]);

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('Editable CCIS landing content')
            ->assertSee('Managed call to action')
            ->assertSee('Content Manager');

        $this->assertSame('Available', SiteContent::landing()['features'][0]['status']);
    }
}
