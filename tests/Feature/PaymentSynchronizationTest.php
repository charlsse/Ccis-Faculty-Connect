<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WaterCollection;
use App\Models\WaterNotice;
use App\Models\WaterPaymentAccount;
use App\Notifications\WaterPaymentUpdated;
use App\Services\PaymentNotifications;
use App\Services\WaterPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class PaymentSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        config(['water.email_enabled' => false]);
        $treasurer = User::factory()->create(['role' => 'treasurer']);
        $faculty = User::factory()->create();
        $other = User::factory()->create();
        $account = WaterPaymentAccount::create(['label' => 'Test', 'account_name' => 'Test', 'masked_account_number' => '***1234', 'qr_image_path' => 'fixture.png', 'created_by' => $treasurer->id, 'is_active' => true]);
        $collection = WaterCollection::create(['title' => 'Synchronization fixture', 'period' => '2026-11', 'amount' => '125.00', 'due_date' => now()->addDays(3), 'status' => 'published', 'payment_account_id' => $account->id, 'created_by' => $treasurer->id]);
        $member = $collection->members()->create(['user_id' => $faculty->id, 'assigned_amount' => '125.00', 'assigned_at' => now(), 'status' => 'unpaid']);

        return compact('treasurer', 'faculty', 'other', 'collection', 'member');
    }

    public function test_manual_submission_approval_history_receipt_notifications_and_real_target(): void
    {
        extract($this->fixture());
        $service = app(WaterPaymentService::class);
        $this->actingAs($faculty);
        $service->submit($member, ['submitted_amount' => '125.00', 'reference_number' => 'SYNC-125', 'paid_at' => now(), 'proof_path' => 'fixture.png']);
        $submission = $member->submissions()->firstOrFail();
        $this->assertSame(2, WaterNotice::count());
        $this->assertSame('pending', $member->fresh()->status);
        $this->assertDatabaseCount('water_receipts', 0);
        $this->actingAs($treasurer);
        $service->review($submission, ['decision' => 'approve']);
        $this->assertSame('paid', $member->fresh()->status);
        $this->assertDatabaseCount('water_receipts', 1);
        $this->assertSame(4, WaterNotice::count());
        app(PaymentNotifications::class)->milestones($collection);
        $this->assertSame(4, WaterNotice::count());
        $this->get(route('water.demo.index'))->assertOk()->assertViewHas('transactions', fn ($rows) => $rows->total() === 1 && $rows->first()['receipt'] !== null && $rows->first()['simulation'] === false);
        $this->get(route('water.reports'))->assertOk();
        $this->actingAs($faculty)->get(route('notifications.feed'))->assertOk()->assertJsonPath('unread', 2);
        $this->get(route('water.demo.index'))->assertViewHas('transactions', fn ($rows) => $rows->total() === 1);
        $this->actingAs($other)->get(route('notifications.feed'))->assertOk()->assertJsonPath('unread', 0);
        $this->get(route('water.receipt', $member->fresh()->receipt))->assertForbidden();
    }

    public function test_rejection_is_not_a_receipt_and_contains_safe_reason_and_owned_link(): void
    {
        extract($this->fixture());
        $service = app(WaterPaymentService::class);
        $this->actingAs($faculty);
        $service->submit($member, ['submitted_amount' => '125.00', 'reference_number' => 'SYNC-REJECT', 'paid_at' => now(), 'proof_path' => 'fixture.png']);
        $this->actingAs($treasurer);
        $service->review($member->submissions()->first(), ['decision' => 'reject', 'rejection_reason' => '<script>unsafe</script> Reference does not match']);
        $this->assertDatabaseCount('water_receipts', 0);
        $this->assertSame('rejected', $member->fresh()->status);
        $this->actingAs($faculty)->get(route('notifications.feed'))->assertOk()->assertSee('Payment Rejected')->assertDontSee('<script>unsafe</script>', false);
    }

    public function test_reminders_are_unique_per_due_date_and_skip_pending_paid_and_unassigned(): void
    {
        extract($this->fixture());
        $notifications = app(PaymentNotifications::class);
        $notifications->reminders();
        $notifications->reminders();
        $this->assertSame(1, WaterNotice::count());
        $this->assertSame($faculty->id, WaterNotice::first()->user_id);
        $member->update(['status' => 'pending']);
        $this->travel(3)->days();
        $notifications->reminders();
        $this->assertSame(1, WaterNotice::count());
    }

    public function test_read_feed_counts_follow_owner_mark_read_and_role_revocation(): void
    {
        extract($this->fixture());
        $notifications = app(PaymentNotifications::class);
        $notifications->notice($faculty, $collection, 'Owner update', 'owner-feed');
        $notifications->notice($treasurer, $collection, 'Treasurer-only update', 'treasurer-feed', ['required_permission' => 'payments.manage']);
        $this->actingAs($faculty)->get(route('notifications.feed'))->assertOk()->assertJsonPath('unread', 1)->assertDontSee('Treasurer-only update');
        $notice = WaterNotice::where('user_id', $faculty->id)->first();
        $this->post(route('notifications.read', 'water-'.$notice->id))->assertRedirect();
        $this->get(route('notifications.feed'))->assertJsonPath('unread', 0);
        $this->actingAs($treasurer)->get(route('notifications.feed'))->assertJsonPath('unread', 1);
        $treasurer->update(['role' => 'faculty']);
        $this->actingAs($treasurer)->get(route('notifications.feed'))->assertJsonPath('unread', 0);
    }

    public function test_existing_notice_flow_is_compatible_before_privileged_schema_deployment(): void
    {
        extract($this->fixture());
        \Illuminate\Support\Facades\Schema::shouldReceive('hasColumn')->with('water_notices', 'event_key')->andReturn(false);
        $notifications = app(PaymentNotifications::class);
        $notifications->notice($faculty, $collection, 'Legacy-compatible update', 'fallback');
        $notifications->notice($faculty, $collection, 'Legacy-compatible update', 'fallback');
        $this->assertSame(1, WaterNotice::count());
        $this->assertNull(WaterNotice::first()->event_key);
    }

    public function test_maintenance_waits_for_schema_without_sending_or_modifying_records(): void
    {
        extract($this->fixture());
        config(['database.connections.xendit_demo.database' => ':memory:']);
        DB::purge('xendit_demo');
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        Notification::fake();
        $this->artisan('payments:maintain')->expectsOutputToContain('awaits the reviewed additive schema deployment')->assertSuccessful();
        $this->assertSame(0, WaterNotice::count());
        $this->assertSame('unpaid', $member->fresh()->status);
        Notification::assertNothingSent();
        \Illuminate\Support\Facades\Http::assertNothingSent();
        DB::purge('xendit_demo');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_email_retry_is_durable_and_does_not_change_payment_or_notice_read_state(): void
    {
        extract($this->fixture());
        config(['water.email_enabled' => true, 'mail.default' => 'smtp']);
        $notifications = app(PaymentNotifications::class);
        $notifications->notice($faculty, $collection, 'A safe payment update', 'email-fixture');
        // RefreshDatabase wraps the main DB; delivery must never precede commit.
        Notification::fake();
        $this->assertSame(0, $notifications->deliverEmail());
        Notification::assertNothingSent();
        DB::commit();
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('Mail transport unavailable'));
        $this->assertSame(0, $notifications->deliverEmail());
        $this->assertSame('pending', WaterNotice::first()->email_state);
        $this->assertSame(1, WaterNotice::first()->email_attempts);
        $this->travel(6)->minutes();
        Notification::fake();
        $this->assertSame(1, $notifications->deliverEmail());
        $this->assertSame(0, $notifications->deliverEmail());
        $this->assertNull(WaterNotice::first()->read_at);
        $this->assertSame('unpaid', $member->fresh()->status);
        Notification::assertSentToTimes($faculty, WaterPaymentUpdated::class, 1);
        DB::beginTransaction();
    }
}
