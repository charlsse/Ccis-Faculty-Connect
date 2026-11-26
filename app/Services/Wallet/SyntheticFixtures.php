<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\WaterCollection;
use App\Models\WaterPaymentAccount;
use App\Models\WaterReceipt;
use App\Models\WaterSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Synthetic-only fixtures; refuses an environment containing any user. */
final class SyntheticFixtures
{
    public static function seed(string $password): array
    {
        SimulationBoundary::check();
        if (User::exists()) {
            throw new \RuntimeException('Fresh synthetic schema required.');
        }
        $users = [];
        foreach (['treasurer', 'admin', 'faculty'] as $role) {
            $users[$role] = User::factory()->create(['first_name' => 'Synthetic', 'last_name' => ucfirst($role), 'email' => $role.'@wallet.invalid', 'role' => $role, 'password' => $password]);
        }
        $u = $users['treasurer'];
        $account = DB::table('wallet_accounts')->insertGetId(['currency' => 'PHP', 'mode' => 'simulated', 'created_at' => now(), 'updated_at' => now()]);
        $destination = DB::table('wallet_destinations')->insertGetId(['account_id' => $account, 'label' => 'Synthetic Treasurer bank', 'masked_number' => 'TEST ending 0000', 'version' => (string) Str::uuid(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $qr = WaterPaymentAccount::create(['label' => 'Synthetic manual QR', 'account_name' => 'Synthetic Treasurer', 'masked_account_number' => 'TEST ONLY', 'qr_image_path' => 'synthetic/no-real-qr.png', 'is_active' => true, 'created_by' => $u->id]);
        $service = new WalletService;
        $collections = [];
        foreach ([['Faculty activity fund', 'xendit_test', '100.00', true], ['Next billing period', 'xendit_test', '80.00', false], ['Manual collections', 'cash', '50.00', false]] as [$title,$method,$amount,$settled]) {
            $c = WaterCollection::create(['title' => $title, 'period' => now()->format('Y-m'), 'amount' => $amount, 'due_date' => now()->addWeek(), 'payment_account_id' => $qr->id, 'status' => 'published', 'published_at' => now(), 'created_by' => $u->id]);
            $m = $c->members()->create(['user_id' => $users['faculty']->id, 'status' => 'paid', 'assigned_amount' => $amount, 'assigned_at' => now(), 'paid_at' => now()]);
            $sub = WaterSubmission::create(['water_member_id' => $m->id, 'attempt_number' => 1, 'payment_method' => $method, 'submitted_amount' => $amount, 'paid_at' => now(), 'status' => 'approved', 'submitted_at' => now(), 'verified_at' => now(), 'verified_by' => $u->id]);
            $receipt = WaterReceipt::create(['water_member_id' => $m->id, 'water_submission_id' => $sub->id, 'receipt_number' => 'SIM-'.Str::uuid(), 'amount' => $amount, 'payment_method' => $method, 'payment_date' => now(), 'verified_by' => $u->id, 'verified_at' => now(), 'generated_at' => now(), 'snapshot' => ['faculty_name' => 'Synthetic Faculty', 'collection_title' => $title, 'period' => $c->period, 'officer_name' => 'SIMULATED - not a provider payment']]);
            $service->target($u, $c->id, $settled ? 10000 : 12000);
            if ($method === 'xendit_test') {
                $service->recognize($u, $receipt->id, 100);
                if ($settled) {
                    $service->settle($u, DB::table('wallet_settlements')->where('receipt_id', $receipt->id)->value('id'));
                }
            }
            $collections[] = $c->id;
        }
        $c = WaterCollection::findOrFail($collections[1]);
        $c->members()->create(['user_id' => $users['admin']->id, 'status' => 'unpaid', 'assigned_amount' => '80.00', 'assigned_at' => now()]);

        return ['users' => array_map(fn ($u) => $u->id, $users), 'collections' => $collections, 'destination' => $destination];
    }
}
