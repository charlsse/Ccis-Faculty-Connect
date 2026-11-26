<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResponsivePresentationTest extends TestCase
{
    use RefreshDatabase;

    private function document(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new DOMXPath($dom);
    }

    private function assertSharedResponsiveLayer(string $html): void
    {
        $xpath = $this->document($html);
        $styles = $xpath->query('//head/link[@rel="stylesheet"]');
        $this->assertStringContainsString('/css/responsive.css?v=', $styles->item($styles->length - 1)->getAttribute('href'));
        $this->assertSame(1, $xpath->query('//script[contains(@src,"/js/responsive.js?v=")]')->length);
        $viewport = $xpath->query('//meta[@name="viewport"]')->item(0)->getAttribute('content');
        $this->assertStringContainsString('viewport-fit=cover', $viewport);
        $this->assertStringNotContainsString('user-scalable=no', $viewport);
    }

    public function test_public_pages_load_responsive_overrides_after_the_desktop_theme(): void
    {
        foreach (['landing', 'login'] as $route) {
            $this->assertSharedResponsiveLayer($this->get(route($route))->assertOk()->getContent());
        }
        $xpath = $this->document($this->get(route('landing'))->getContent());
        $icons = $xpath->query('//*[local-name()="svg" and @class="fc-ui-icon"]');
        $this->assertGreaterThan(10, $icons->length);
        foreach ($icons as $icon) {
            $this->assertSame('20', $icon->getAttribute('width'));
            $this->assertSame('20', $icon->getAttribute('height'));
            $this->assertSame('true', $icon->getAttribute('aria-hidden'));
            $this->assertSame('false', $icon->getAttribute('focusable'));
        }
    }

    public function test_role_workspaces_share_the_same_mobile_layer_without_changing_navigation_permissions(): void
    {
        Http::preventStrayRequests();
        foreach (['faculty', 'treasurer', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['dashboard', 'water.index', 'notifications.index', 'profile.show', 'security.show', 'announcements.index'] as $route) {
                $this->assertSharedResponsiveLayer($this->get(route($route))->assertOk()->getContent());
            }
            $html = $this->get(route('dashboard'))->getContent();
            $xpath = $this->document($html);
            foreach ($xpath->query('//*[local-name()="svg" and @class="workspace-nav-icon"]') as $icon) {
                $this->assertSame('20', $icon->getAttribute('width'));
                $this->assertSame('20', $icon->getAttribute('height'));
            }
            if ($role === 'faculty') $this->get(route('members.index'))->assertForbidden();
        }
    }
}
