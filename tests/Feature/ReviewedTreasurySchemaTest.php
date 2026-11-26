<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WaterCollection;
use App\Models\WaterPaymentAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewedTreasurySchemaTest extends TestCase
{
    use RefreshDatabase;

    private function migration()
    {
        return require database_path('reviewed/wallet/2026_10_19_010000_create_treasury_accounting.php');
    }

    private function setupCollection(): array
    {
        $u = User::factory()->create(['role' => 'treasurer']);
        $a = WaterPaymentAccount::create(['label' => 'Test', 'account_name' => 'Test', 'masked_account_number' => 'TEST', 'qr_image_path' => 'test.png', 'is_active' => true, 'created_by' => $u->id]);
        $c = WaterCollection::create(['title' => 'Review collection', 'period' => '2026-10', 'amount' => '10.00', 'due_date' => now()->addWeek(), 'status' => 'published', 'published_at' => now(), 'payment_account_id' => $a->id, 'created_by' => $u->id]);

        return [$u, $c];
    }

    public function test_empty_schema_rolls_back_without_touching_existing_tables(): void
    {
        config(['wallet.enabled' => false]);
        $this->migration()->up();
        $this->assertTrue(Schema::hasTable('wallet_provider_events'));
        $this->assertDatabaseCount('wallet_accounts', 0);
        $this->migration()->down();
        $this->assertFalse(Schema::hasTable('wallet_targets'));
        $this->assertTrue(Schema::hasTable('water_receipts'));
    }

    public function test_quota_update_duplicate_audit_and_stale_edit(): void
    {
        config(['wallet.enabled' => false]);
        $this->migration()->up();
        [$u,$c] = $this->setupCollection();
        $key = (string) Str::uuid();
        $payload = ['collection' => $c->id, 'amount' => '100.00', 'revision' => 0, 'operation_key' => $key];
        $this->actingAs($u)->post(route('wallet.target'), $payload)->assertSessionHasNoErrors();
        $this->post(route('wallet.target'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('wallet_targets', 1);
        $this->assertDatabaseCount('wallet_audits', 1);
        $this->post(route('wallet.target'), array_merge($payload, ['amount' => '200.00', 'operation_key' => (string) Str::uuid()]))->assertSessionHasErrors('target');
        $this->get(route('wallet.index'))->assertOk()->assertSee('100.00')->assertSee('Request withdrawal unavailable');
        $this->assertDatabaseCount('wallet_accounts', 0);
        $this->assertDatabaseCount('wallet_entries', 0);
        $this->expectException(\RuntimeException::class);
        $this->migration()->down();
    }

    public function test_non_treasurer_cannot_edit_targets_and_no_remote_processing(): void
    {
        config(['wallet.enabled' => false]);
        Http::preventStrayRequests();
        $this->migration()->up();
        [$u,$c] = $this->setupCollection();
        foreach (['admin', 'faculty', 'president'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->post(route('wallet.target'), ['collection' => $c->id, 'amount' => '100.00', 'revision' => 0, 'operation_key' => (string) Str::uuid()])->assertForbidden();
        }
        $this->assertDatabaseCount('wallet_audits', 0);
        Http::assertNothingSent();
    }

    public function test_schema_absent_target_fails_without_creating_records(): void
    {
        config(['wallet.enabled' => false]);
        [$u,$c] = $this->setupCollection();
        $this->actingAs($u)->post(route('wallet.target'), ['collection' => $c->id, 'amount' => '100.00', 'revision' => 0, 'operation_key' => (string) Str::uuid()])->assertStatus(409);
        $this->assertFalse(Schema::hasTable('wallet_targets'));
    }
}
