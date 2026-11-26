<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Schema};
use Tests\TestCase;
class NormalTreasuryOverviewTest extends TestCase {
    use RefreshDatabase;
    public function test_normal_overview_needs_no_wallet_schema_and_preserves_permissions(): void {
        config(['wallet.enabled'=>false,'payment_gateway.enabled'=>false]);Http::preventStrayRequests();
        $this->assertFalse(Schema::hasTable('wallet_accounts'));
        foreach(['treasurer','admin','faculty'] as $role){
            $u=User::factory()->create(['role'=>$role]);
            $this->actingAs($u)->get(route('water.index'))->assertOk();
            $this->get(route('payments.index'))->assertOk();
            $this->get(route('wallet.index'))->assertStatus($role==='faculty'?403:200);
            $this->get(route('water.accounts'))->assertStatus($role==='treasurer'?200:403);
            $this->post(route('wallet.review'),['collection'=>1,'destination'=>1,'amount'=>'10.00'])->assertNotFound();
        }
        Http::assertNothingSent();$this->assertFalse(Schema::hasTable('wallet_accounts'));
    }
}
