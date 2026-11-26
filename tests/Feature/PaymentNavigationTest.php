<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WaterCollection;
use App\Models\WaterPaymentAccount;
use App\Models\WaterReceipt;
use App\Models\WaterSubmission;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function navigation(string $html, string $class = 'payment-primary'): array
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($document);
        $links = $xpath->query('//nav[@class="'.$class.'"]/a');
        $items = [];
        foreach ($links as $link) {
            $items[] = ['label' => trim($link->textContent), 'href' => $link->getAttribute('href'),
                'active' => $link->getAttribute('aria-current') === 'page'];
            $this->assertSame(1, $xpath->query('./*[local-name()="svg"]', $link)->length);
        }

        return $items;
    }

    private function assertCurrent(string $html, string $label): void
    {
        $active = array_values(array_filter($this->navigation($html), fn ($item) => $item['active']));
        $this->assertCount(1, $active);
        $this->assertSame($label, $active[0]['label']);
    }

    public function test_faculty_destinations_are_accessible_and_management_stays_forbidden(): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create());
        $response = $this->get(route('water.index'))->assertOk();
        $items = $this->navigation($response->getContent());
        $this->assertSame(['My Payments', 'Transaction History', 'Receipts'], array_column($items, 'label'));
        foreach ($items as $item) {
            $page = $this->get($item['href'])->assertOk();
            $this->assertCurrent($page->getContent(), $item['label']);
        }
        foreach (['water.create', 'water.accounts', 'water.queue', 'water.reports', 'water.audit', 'wallet.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $history = $this->get(route('water.demo.index'))->assertOk();
        foreach ($this->navigation($history->getContent(), 'payment-secondary') as $link) {
            $this->get($link['href'])->assertOk();
        }
        Http::assertNothingSent();
    }

    public function test_treasurer_has_six_destinations_and_every_section_link_resolves(): void
    {
        Http::preventStrayRequests();
        config(['wallet.enabled' => false]);
        $this->actingAs(User::factory()->create(['role' => 'treasurer']));
        $home = $this->get(route('water.index'))->assertOk();
        $items = $this->navigation($home->getContent());
        $this->assertSame(['Overview', 'Collections', 'Transactions', 'Reports', 'Treasurer Wallet', 'Payment Settings'], array_column($items, 'label'));
        foreach ($items as $item) {
            $page = $this->get($item['href'])->assertOk();
            $this->assertCurrent($page->getContent(), $item['label']);
            foreach ($this->navigation($page->getContent(), 'payment-secondary') as $link) {
                $this->get($link['href'])->assertOk();
            }
        }
        $this->get(route('water.audit'))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_administrator_president_overview_does_not_grant_mutation_rights(): void
    {
        config(['wallet.enabled' => false]);
        // The configured admin role is labelled Administrator / President.
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $home = $this->get(route('water.index'))->assertOk()->assertSee('Financial Overview');
        $items = $this->navigation($home->getContent());
        $this->assertSame(['Financial Overview', 'Collections', 'Transactions', 'Reports', 'Treasurer Wallet'], array_column($items, 'label'));
        foreach ($items as $item) {
            $page = $this->get($item['href'])->assertOk();
            $this->assertCurrent($page->getContent(), $item['label']);
            foreach ($this->navigation($page->getContent(), 'payment-secondary') as $link) {
                $this->assertNotContains($link['label'], ['Create Collection', 'Pending Verification', 'Treasurer QR Accounts']);
                $this->get($link['href'])->assertOk();
            }
        }
        foreach (['water.create', 'water.accounts', 'water.queue'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->post(route('water.store'), [])->assertForbidden();
        $this->post(route('payments.store'), [])->assertForbidden();
    }

    public function test_legacy_president_visibility_follows_actual_permissions(): void
    {
        config(['wallet.enabled' => false]);
        $this->actingAs(User::factory()->create(['role' => 'president']));
        $home = $this->get(route('water.index'))->assertOk();
        $this->assertSame(['My Payments', 'Transaction History', 'Receipts'], array_column($this->navigation($home->getContent()), 'label'));
        $this->get(route('water.reports'))->assertForbidden();
        $this->get(route('wallet.index'))->assertForbidden();
    }

    public function test_old_category_bookmarks_and_collection_details_keep_correct_parent_active(): void
    {
        $treasurer = User::factory()->create(['role' => 'treasurer']);
        $account = WaterPaymentAccount::create(['label' => 'Test', 'account_name' => 'Test', 'masked_account_number' => '***1234',
            'qr_image_path' => 'fixture.png', 'created_by' => $treasurer->id, 'is_active' => true]);
        $collection = WaterCollection::create(['title' => 'Navigation fixture', 'category' => 'water', 'period' => '2026-11',
            'amount' => '100.00', 'due_date' => now()->addWeek(), 'status' => 'draft', 'payment_account_id' => $account->id, 'created_by' => $treasurer->id]);
        $this->actingAs($treasurer);
        foreach ([route('water.index', ['category' => 'water']), route('water.show', $collection), route('water.edit', $collection)] as $url) {
            $page = $this->get($url)->assertOk();
            $this->assertCurrent($page->getContent(), 'Collections');
        }
        $page = $this->get(route('water.index', ['section' => 'collections', 'category' => 'water', 'status' => 'draft']))
            ->assertOk()->assertSee('name="category" value="water"', false);
        $this->assertCurrent($page->getContent(), 'Collections');
    }

    public function test_receipts_view_filters_before_pagination_and_preserves_owner_scope(): void
    {
        $treasurer = User::factory()->create(['role' => 'treasurer']);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = WaterPaymentAccount::create(['label' => 'Test', 'account_name' => 'Test', 'masked_account_number' => '***1234',
            'qr_image_path' => 'fixture.png', 'created_by' => $treasurer->id, 'is_active' => true]);
        foreach ([$owner, $other] as $user) {
            $collection = WaterCollection::create(['title' => 'Receipt fixture '.$user->id, 'period' => '2026-11', 'amount' => '100.00',
                'due_date' => now()->addWeek(), 'status' => 'published', 'payment_account_id' => $account->id, 'created_by' => $treasurer->id]);
            $member = $collection->members()->create(['user_id' => $user->id, 'assigned_amount' => '100.00', 'assigned_at' => now(), 'status' => 'paid']);
            $submission = WaterSubmission::create(['water_member_id' => $member->id, 'attempt_number' => 1, 'payment_method' => 'cash',
                'submitted_amount' => '100.00', 'paid_at' => now(), 'submitted_at' => now(), 'status' => 'approved', 'verified_by' => $treasurer->id]);
            WaterReceipt::create(['water_member_id' => $member->id, 'water_submission_id' => $submission->id, 'receipt_number' => 'NAV-'.$user->id,
                'amount' => '100.00', 'payment_method' => 'cash', 'payment_date' => now(), 'verified_by' => $treasurer->id,
                'verified_at' => now(), 'generated_at' => now(), 'snapshot' => []]);
        }
        $this->actingAs($owner)->get(route('water.demo.index', ['view' => 'receipts']))->assertOk()
            ->assertViewHas('transactions', fn ($rows) => $rows->total() === 1 && $rows->first()['receipt'] !== null)
            ->assertSee('name="view" value="receipts"', false)->assertDontSee('Receipt fixture '.$other->id);
        $this->actingAs($treasurer)->get(route('water.demo.index', ['view' => 'receipts']))->assertOk()
            ->assertViewHas('transactions', fn ($rows) => $rows->total() === 2);
    }
}
