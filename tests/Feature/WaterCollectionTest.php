<?php

namespace Tests\Feature;

use App\Http\Controllers\WaterPaymentController;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WaterCollection;
use App\Models\WaterMember;
use App\Models\WaterNotice;
use App\Models\WaterPaymentAccount;
use App\Models\WaterReceipt;
use App\Models\WaterSubmission;
use App\Services\WaterPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WaterCollectionTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    private User $faculty;

    private WaterPaymentAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['water.email_enabled' => false]);
        $this->officer = User::factory()->create(['role' => 'treasurer']);
        $this->faculty = User::factory()->create();
        $path = $this->imageFile()->store('water/qr', 'local');
        $this->account = WaterPaymentAccount::create(['label' => 'Test club', 'account_name' => 'Synthetic CCIS Club', 'masked_account_number' => '09** *** 1234', 'qr_image_path' => $path, 'is_active' => true, 'created_by' => $this->officer->id]);
    }

    private function collection(string $status = 'published', ?User $faculty = null): WaterMember
    {
        $c = WaterCollection::create(['title' => 'Synthetic Water Collection', 'period' => now()->format('Y-m'), 'amount' => '100.00', 'due_date' => now()->addDays(7)->toDateString(), 'payment_account_id' => $this->account->id, 'status' => $status, 'published_at' => $status === 'draft' ? null : now(), 'created_by' => $this->officer->id]);

        return $c->members()->create(['user_id' => ($faculty ?? $this->faculty)->id, 'assigned_amount' => '100.00', 'assigned_at' => now(), 'status' => 'unpaid']);
    }

    public function test_treasurer_flow_without_any_gateway_configuration_or_migration(): void
    {
        \Illuminate\Support\Facades\Schema::dropIfExists('water_gateway_attempts');
        config(['payment_gateway.enabled' => false, 'payment_gateway.secret_key' => null, 'payment_gateway.webhook_secret' => null, 'payment_gateway.sandbox_data_confirmed' => false]);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        foreach (['water', 'other'] as $category) {
            $member = $this->collection();
            $member->collection->update(['category' => $category]);
            $this->actingAs($this->faculty)->get(route('water.index', ['category' => $category]))->assertOk();
            $this->get(route('water.checkout', $member))->assertOk()->assertSee('fc-manual-option', false)->assertDontSee('data-qr-dialog', false)->assertSee('Manual QR Payment')->assertDontSee('PayMongo');
            $this->get(route('water.qr', $member->collection))->assertOk();
            $this->post(route('water.submit', $member), $this->data(['reference_number' => 'TREASURER-'.$category]))->assertSessionHasNoErrors();
            $this->assertSame('pending', $member->fresh()->status);
            $submission = $member->submissions()->firstOrFail();
            $this->review($submission)->assertSessionHasNoErrors();
            $this->assertSame('paid', $member->fresh()->status);
            $this->assertSame($this->officer->id, $member->fresh()->receipt->verified_by);
            $this->get(route('water.report', $member->collection))->assertOk();
            $this->actingAs($this->faculty)->get(route('water.receipt', $member->fresh()->receipt))->assertOk();
            $this->assertTrue(WaterNotice::where('user_id', $this->faculty->id)->where('water_collection_id', $member->water_collection_id)->exists());
        }
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_treasurer_can_create_other_collection_with_shared_payment_workflow(): void
    {
        $this->actingAs($this->officer)->post(route('water.store'), [
            'category' => 'other', 'title' => 'Faculty Club Activity', 'period' => now()->format('Y-m'),
            'description' => 'Shared Treasurer collection', 'amount' => '100.00', 'due_date' => now()->addWeek()->toDateString(),
            'payment_account_id' => $this->account->id, 'assignment' => 'selected', 'members' => [$this->faculty->id],
        ])->assertSessionHasNoErrors();
        $collection = WaterCollection::where('title', 'Faculty Club Activity')->firstOrFail();
        $this->assertSame('other', $collection->category);
        $this->patch(route('water.transition', $collection), ['status' => 'published', 'confirmed' => 1])->assertSessionHasNoErrors();
        $this->actingAs($this->faculty)->get(route('water.index', ['category' => 'other']))->assertOk()->assertSee('Faculty Club Activity');
        $this->get(route('water.index', ['category' => 'water']))->assertOk()->assertViewHas('personalWater', fn ($items) => $items->isEmpty());
    }

    public function test_admin_financial_oversight_is_read_only_even_for_direct_requests(): void
    {
        $member = $this->collection();
        $submission = $this->pending($member);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $this->get(route('water.index', ['section' => 'collections']))->assertOk()->assertDontSee('Create collection')->assertDontSee('PayMongo');
        $this->get(route('water.show', [$member->collection, 'section' => 'members']))->assertOk()->assertSee($this->faculty->full_name)->assertDontSee('Record cash payment');
        $this->get(route('water.submission', $submission))->assertOk()->assertDontSee('Save review');
        $this->get(route('water.proof', $submission))->assertOk();
        $this->get(route('water.report', [$member->collection, 'download' => 1]))->assertOk();
        $this->get(route('water.reports'))->assertOk();
        $this->post(route('water.review', $submission), ['decision' => 'approve', 'confirmed' => 1])->assertForbidden();
        $this->post(route('water.review', $submission), ['decision' => 'reject', 'confirmed' => 1, 'rejection_reason' => 'No'])->assertForbidden();
        $this->post(route('water.accounts.store'), [])->assertForbidden();
        $this->patch(route('water.accounts.activate', $this->account), ['active' => 0, 'confirmed' => 1])->assertForbidden();
        $this->post(route('water.store'), [])->assertForbidden();
        $this->put(route('water.update', $member->collection), [])->assertForbidden();
        $this->patch(route('water.transition', $member->collection), ['status' => 'closed', 'confirmed' => 1])->assertForbidden();
        $this->post(route('water.cash.store', $member), $this->data())->assertForbidden();
        $this->post(route('water.waive', $member), ['reason' => 'No', 'confirmed' => 1])->assertForbidden();
        $legacy = \App\Models\Payment::factory()->create(['user_id' => $this->faculty->id]);
        $this->get(route('payments.index'))->assertOk()->assertSee($legacy->description)->assertDontSee('Add Manual Record');
        $this->post(route('payments.store'), [])->assertForbidden();
        $this->patch(route('payments.update', $legacy), ['status' => 'paid'])->assertForbidden();
        $this->delete(route('payments.destroy', $legacy))->assertForbidden();
        $this->assertSame('pending', $member->fresh()->status);
        $this->assertTrue($this->account->fresh()->is_active);
        $this->review($submission)->assertSessionHasNoErrors();
        $this->actingAs($admin)->get(route('water.receipt', $member->fresh()->receipt))->assertOk();
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['submitted_amount' => '100.00', 'reference_number' => 'TEST-REF-001', 'payment_date' => now()->subDay()->toDateString(), 'payment_time' => '10:30', 'proof' => $this->imageFile(), 'faculty_note' => 'Synthetic note', 'confirmed' => '1'], $overrides);
    }

    private function imageFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('test.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }

    private function pending(?WaterMember $member = null): WaterSubmission
    {
        $member ??= $this->collection();
        $this->actingAs($member->user)->post(route('water.submit', $member), $this->data())->assertSessionHasNoErrors()->assertRedirect();

        return $member->submissions()->firstOrFail();
    }

    private function review(WaterSubmission $submission, array $overrides = [])
    {
        return $this->actingAs($this->officer)->post(route('water.review', $submission), array_replace(['decision' => 'approve', 'confirmed' => '1'], $overrides));
    }

    public function test_excel_report_is_print_ready_and_preserves_reference_text(): void
    {
        $member = $this->collection();
        $submission = $this->pending($member);
        $submission->update(['reference_number' => '0000745000000001']);
        $this->review($submission)->assertSessionHasNoErrors();
        foreach (User::factory()->count(31)->create() as $user) {
            $member->collection->members()->create(['user_id' => $user->id, 'assigned_amount' => '100.00', 'assigned_at' => now(), 'status' => 'unpaid']);
        }
        $this->faculty->update(['first_name' => '=1+1']);
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get(route('water.report', [$member->collection, 'format' => 'xlsx']))->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'report-test-');
        file_put_contents($path, $response->getContent());
        $zip = new \ZipArchive;
        try {
            $this->assertTrue($zip->open($path));
            $xml = $zip->getFromName('xl/worksheets/sheet2.xml');
            $this->assertNotFalse(simplexml_load_string($xml));
            $this->assertStringContainsString('0000745000000001', $xml);
            $this->assertStringContainsString('=1+1', $xml);
            $this->assertStringNotContainsString('<f>', $xml);
            $this->assertStringContainsString('orientation="landscape" fitToWidth="1" fitToHeight="0"', $xml);
            $this->assertStringContainsString('A6:H38', $xml);
            $summary = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('<c r="C8" s="4"><v>3200</v>', $summary);
            $this->assertStringContainsString('<c r="C9" s="6"><v>100</v>', $summary);
            $this->assertStringContainsString('<c r="C10" s="4"><v>3100</v>', $summary);
            $this->assertStringContainsString('Transactions!$1:$6', $zip->getFromName('xl/workbook.xml'));
        } finally {
            $zip->close();
            unlink($path);
        }
        $this->actingAs($this->faculty)->get(route('water.report', [$member->collection, 'format' => 'xlsx']))->assertForbidden();
    }

    public function test_dashboard_prioritizes_only_personal_obligations_and_pending_is_not_paid(): void
    {
        $own = $this->collection();
        $other = $this->collection('published', $this->officer);
        $other->collection->update(['title' => 'Private officer obligation']);
        $this->actingAs($this->faculty)->get(route('dashboard'))->assertOk()
            ->assertSee('Pay Now')->assertDontSee('Private officer obligation')
            ->assertViewHas('personalWater', fn ($items) => $items->count() === 1 && $items->first()->id === $own->id);
        $submission = $this->pending($own);
        $this->actingAs($this->faculty)->get(route('dashboard'))->assertOk()
            ->assertSee('View pending submission')->assertDontSee('Pay Now')
            ->assertViewHas('waterSummary', fn ($summary) => (float) $summary['collected'] === 0.0 && (float) $summary['outstanding'] === 100.0);
        $this->actingAs($this->officer)->get(route('dashboard'))->assertOk()
            ->assertViewHas('personalWater', fn ($items) => $items->count() === 1 && $items->first()->id === $other->id);
    }

    public function test_guests_and_nonfinancial_roles_are_blocked(): void
    {
        $this->get(route('water.index'))->assertRedirect(route('login'));
        foreach (['faculty', 'secretary', 'staff'] as $role) {
            $u = User::factory()->create(['role' => $role]);
            foreach (['water.create', 'water.accounts', 'water.queue', 'water.audit'] as $route) {
                $this->actingAs($u)->get(route($route))->assertForbidden();
            }
            $this->actingAs($u)->post(route('water.accounts.store'), [])->assertForbidden();
        }
        $this->actingAs($this->officer)->get(route('water.accounts'))->assertOk();
        $this->actingAs($this->officer)->get(route('water.audit'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('water.audit'))->assertOk();
    }

    public function test_checkout_is_personal_even_for_officers_and_preserves_fixed_amounts(): void
    {
        $member = $this->collection();
        $own = $this->collection('published', $this->officer);
        $this->actingAs($this->faculty)->get(route('water.checkout', $member))->assertOk()
            ->assertSee('Continue to Payment')->assertSee('readonly', false)->assertSee('Synthetic CCIS Club');
        $this->get(route('water.checkout', $own))->assertForbidden();
        $this->actingAs($this->officer)->get(route('water.checkout', $member))->assertForbidden();
        $this->get(route('water.checkout', $own))->assertOk()->assertSee('Continue to Payment');
        $this->post(route('water.submit', $own), $this->data(['submitted_amount' => '1.00']))->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('water_submissions', 0);
        $this->post(route('water.submit', $own), $this->data())->assertSessionHasNoErrors()->assertRedirect(route('water.checkout', $own));
        $this->get(route('water.checkout', $own))->assertOk()->assertSee('Payment awaiting review')->assertDontSee('Pay Now');
        $this->assertSame('pending', $own->fresh()->status);
        $this->assertDatabaseCount('water_receipts', 0);
    }

    public function test_checkout_handles_missing_qr_and_closed_collections_without_broken_payment_action(): void
    {
        $member = $this->collection();
        Storage::disk('local')->delete($this->account->qr_image_path);
        $this->actingAs($this->faculty)->get(route('water.checkout', $member))->assertOk()
            ->assertSee('QR unavailable')->assertDontSee('Show payment QR');
        $member->collection->update(['status' => 'closed']);
        $this->get(route('water.checkout', $member))->assertOk()->assertSee('Payments are closed')->assertDontSee('Pay Now');
        $draft = $this->collection('draft');
        $this->get(route('water.checkout', $draft))->assertForbidden();
    }

    public function test_audit_filters_preserve_admin_only_visibility(): void
    {
        $member = $this->collection();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        app(\App\Services\AuditLogger::class)->record('water.filter_test', $member->user, $admin);
        $this->get(route('water.audit', ['action' => 'water.filter_test', 'actor' => $admin->id, 'date' => now()->toDateString()]))
            ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->count() === 1);
        $this->get(route('water.audit', ['actor' => $this->officer->id]))->assertOk()->assertViewHas('logs', fn ($logs) => $logs->isEmpty());
        $this->actingAs($this->officer)->get(route('water.audit', ['actor' => $admin->id]))->assertForbidden();
    }

    public function test_payment_navigation_reports_and_collection_filters_remain_permission_scoped(): void
    {
        $member = $this->collection();
        $other = $member->collection->members()->create(['user_id' => $this->officer->id, 'assigned_amount' => '100.00', 'assigned_at' => now(), 'status' => 'paid']);
        $this->actingAs($this->faculty)->get(route('water.index'))->assertOk()->assertSee('Pay Now')->assertDontSee('QR Accounts')->assertDontSee('Create collection');
        $this->get(route('water.reports'))->assertForbidden();
        $this->actingAs($this->officer)->get(route('water.reports'))->assertOk()->assertSee('Report & Excel export', false);
        $this->get(route('water.show', [$member->collection, 'section' => 'members', 'member_status' => 'paid']))->assertOk()
            ->assertViewHas('members', fn ($items) => $items->count() === 1 && $items->first()->id === $other->id)
            ->assertViewHas('summary', fn ($summary) => (float) $summary['expected'] === 200.0);
        $this->get(route('payments.index'))->assertOk()->assertDontSee('name="reference_no"', false);
        $this->get(route('payments.index', ['add' => 1]))->assertOk()->assertSee('name="reference_no"', false);
    }

    public function test_create_publish_assignments_visibility_and_immutable_terms(): void
    {
        $payload = ['title' => 'New water period', 'period' => now()->format('Y-m'), 'amount' => '100.00', 'due_date' => now()->addWeek()->toDateString(), 'payment_account_id' => $this->account->id, 'assignment' => 'selected', 'members' => [$this->faculty->id]];
        $this->actingAs($this->officer)->post(route('water.store'), $payload)->assertSessionHasNoErrors();
        $c = WaterCollection::firstOrFail();
        $this->assertCount(1, $c->members);
        $this->actingAs($this->faculty)->get(route('water.index'))->assertDontSee('New water period');
        $this->get(route('water.show', $c))->assertForbidden();
        $this->actingAs($this->officer)->patch(route('water.transition', $c), ['status' => 'published', 'confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('water_members', 1);
        $this->assertDatabaseHas('water_notices', ['user_id' => $this->faculty->id]);
        $this->actingAs($this->faculty)->get(route('water.show', $c))->assertOk()->assertSee('New water period');
        $this->actingAs(User::factory()->create())->get(route('water.show', $c))->assertForbidden();
        $this->actingAs($this->officer)->put(route('water.update', $c), $payload + ['description' => 'Unsafe edit'])->assertSessionHasErrors('payment');
        $this->assertSame('100.00', $c->fresh()->amount);
        $this->actingAs($this->officer)->post(route('water.store'), array_replace($payload, ['members' => [$this->faculty->id, $this->faculty->id]]))->assertSessionHasErrors('members.0');
        $this->actingAs($this->officer)->post(route('water.store'), array_replace($payload, ['amount' => 0, 'due_date' => '']))->assertSessionHasErrors(['amount', 'due_date']);
    }

    public function test_all_assignment_excludes_inactive_and_legacy_staff(): void
    {
        User::factory()->create(['status' => 'deactivated']);
        User::factory()->create(['role' => 'staff']);
        $this->actingAs($this->officer)->post(route('water.store'), ['title' => 'All active', 'period' => now()->format('Y-m'), 'amount' => 10, 'due_date' => now()->toDateString(), 'payment_account_id' => $this->account->id, 'assignment' => 'all'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('water_members', 2);
    }

    public function test_valid_submission_is_private_pending_and_cannot_be_duplicated(): void
    {
        $s = $this->pending();
        $this->assertSame('pending', $s->member->status);
        $this->assertSame('TEST-REF-001', $s->reference_number);
        $this->assertNotSame('TEST-REF-001', DB::table('water_submissions')->value('reference_number'));
        Storage::disk('local')->assertExists($s->proof_path);
        $this->assertDatabaseCount('water_receipts', 0);
        $this->post(route('water.submit', $s->member), $this->data(['reference_number' => 'OTHER-REF']))->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('water_submissions', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('water/proofs'));
        $this->get(route('water.proof', $s))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/storage/'.$s->proof_path)->assertForbidden();
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('water.proof', $s))->assertForbidden();
        $this->get(route('water.submission', $s))->assertForbidden();
        $this->post(route('water.submit', $s->member), $this->data())->assertForbidden();
        $this->actingAs($this->officer)->get(route('water.proof', $s))->assertOk();
        $this->get(route('water.submission', $s))->assertOk()->assertSee('Treasurer verification');
    }

    public function test_invalid_payment_inputs_and_executable_uploads_are_rejected(): void
    {
        $m = $this->collection();
        $this->actingAs($this->faculty);
        foreach ([['submitted_amount' => '99.99'], ['reference_number' => ''], ['payment_date' => 'invalid'], ['payment_date' => now()->addDay()->toDateString()], ['submitted_amount' => '1.001'], ['proof' => UploadedFile::fake()->create('evil.php', 2, 'application/x-php')], ['proof' => UploadedFile::fake()->create('fake.png', 2, 'text/plain')], ['proof' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')], ['confirmed' => 0]] as $invalid) {
            $this->withoutMiddleware(ThrottleRequests::class)->post(route('water.submit', $m), $this->data($invalid))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('water_submissions', 0);
    }

    public function test_reference_uniqueness_and_rejected_attempt_chain(): void
    {
        $s = $this->pending();
        $this->review($s, ['decision' => 'reject', 'rejection_reason' => 'Reference does not match actual funds.'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $s->member->fresh()->status);
        $this->actingAs($this->faculty)->get(route('water.show', $s->member->collection))->assertSee('Reference does not match actual funds.');
        $this->post(route('water.submit', $s->member), $this->data(['reference_number' => ' test-ref-001 ']))->assertSessionHasNoErrors();
        $next = $s->member->submissions()->first();
        $this->assertSame(2, $next->attempt_number);
        $this->assertSame($s->id, $next->previous_submission_id);
        $this->assertSame('rejected', $s->fresh()->status);
        Storage::disk('local')->assertExists($s->proof_path);
        $other = $this->collection('published', User::factory()->create());
        $this->actingAs($other->user)->post(route('water.submit', $other), $this->data(['reference_number' => 'test-ref-001']))->assertSessionHasErrors('payment');
        $this->review($next)->assertSessionHasNoErrors();
    }

    public function test_approval_generates_exactly_one_receipt_and_blocks_unauthorized_review(): void
    {
        $s = $this->pending();
        $this->post(route('water.review', $s), ['decision' => 'approve', 'confirmed' => 1])->assertForbidden();
        $this->review($s)->assertSessionHasNoErrors();
        $this->assertSame('paid', $s->member->fresh()->status);
        $this->assertSame('approved', $s->fresh()->status);
        $r = WaterReceipt::firstOrFail();
        $this->assertSame($this->officer->id, $r->verified_by);
        $this->assertStringStartsWith('FC-WATER-', $r->receipt_number);
        $this->review($s)->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('water_receipts', 1);
        $this->actingAs($this->faculty)->get(route('water.receipt', $r))->assertOk()->assertSee('Completed');
        $this->actingAs(User::factory()->create())->get(route('water.receipt', $r))->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'water.approve']);
    }

    public function test_rejection_requires_reason_and_correction_notifies_member(): void
    {
        $s = $this->pending();
        $this->review($s, ['decision' => 'reject'])->assertSessionHasErrors('rejection_reason');
        $this->assertSame('pending', $s->fresh()->status);
        $this->review($s, ['decision' => 'correct', 'rejection_reason' => 'Upload a readable proof.'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $s->fresh()->status);
        $this->assertTrue(WaterNotice::where('user_id', $this->faculty->id)->where('message', 'like', '%correction%')->exists());
    }

    public function test_approval_rolls_back_when_receipt_creation_fails(): void
    {
        $s = $this->pending();
        $this->actingAs($this->officer);
        $service = \Mockery::mock(WaterPaymentService::class)->makePartial();
        $service->shouldReceive('receipt')->once()->andThrow(new \RuntimeException('Synthetic receipt failure'));
        try {
            $service->review($s, ['decision' => 'approve']);
            $this->fail('Expected rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic receipt failure', $e->getMessage());
        }
        $this->assertSame('pending', $s->fresh()->status);
        $this->assertSame('pending', $s->member->fresh()->status);
        $this->assertDatabaseCount('water_receipts', 0);
        $this->assertFalse(AuditLog::where('action', 'water.approve')->exists());
    }

    public function test_cash_waiver_archive_and_reports(): void
    {
        $m = $this->collection();
        $this->actingAs($this->faculty)->post(route('water.cash.store', $m), $this->data())->assertForbidden();
        $this->actingAs($this->officer)->post(route('water.cash.store', $m), $this->data())->assertSessionHasNoErrors();
        $this->assertSame('cash', WaterReceipt::first()->payment_method);
        $this->post(route('water.cash.store', $m), $this->data())->assertSessionHasErrors('payment');
        $other = $m->collection->members()->create(['user_id' => $this->officer->id, 'assigned_amount' => '100.00', 'assigned_at' => now()]);
        $this->post(route('water.waive', $other), ['confirmed' => 1])->assertSessionHasErrors('reason');
        $this->post(route('water.waive', $other), ['confirmed' => 1, 'reason' => 'Authorized exemption'])->assertSessionHasNoErrors();
        $report = $this->get(route('water.report', $m->collection))->assertOk();
        $report->assertViewHas('summary', fn ($s) => (float) $s['expected'] === 200.0 && (float) $s['collected'] === 100.0 && (float) $s['outstanding'] === 0.0 && (float) $s['waived'] === 100.0);
        $this->patch(route('water.transition', $m->collection), ['status' => 'closed', 'confirmed' => 1])->assertSessionHasNoErrors();
        $this->patch(route('water.transition', $m->collection), ['status' => 'archived', 'confirmed' => 1])->assertSessionHasNoErrors();
        $this->actingAs($this->faculty)->get(route('water.show', $m->collection))->assertOk()->assertSee('Archived');
        $this->get(route('water.report', $m->collection))->assertForbidden();
    }

    public function test_csv_formula_injection_and_queue_filters(): void
    {
        $this->faculty->update(['first_name' => '=HYPERLINK("evil")']);
        $s = $this->pending();
        $this->actingAs($this->officer)->get(route('water.queue', ['reference' => 'TEST-REF-001']))->assertOk()->assertSee('Synthetic Water Collection');
        $this->get(route('water.queue', ['reference' => 'NO-MATCH']))->assertOk()->assertSee('No submissions match');
        $csv = $this->get(route('water.report', [$s->member->collection, 'download' => 1]))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        foreach (['=x', '+x', '-x', '@x', "\t=x", '  @x'] as $value) {
            $this->assertSame("'".$value, WaterPaymentController::csvCell($value));
        }
    }

    public function test_accounts_validate_real_images_and_preserve_old_destinations(): void
    {
        $m = $this->collection();
        $this->actingAs($this->officer);
        $payload = ['label' => 'Replacement', 'account_name' => 'Synthetic club', 'masked_account_number' => '09123456789', 'qr' => $this->imageFile(), 'confirmed' => 1];
        $this->post(route('water.accounts.store'), $payload)->assertSessionHasErrors('masked_account_number');
        $payload['masked_account_number'] = '09** *** 5678';
        $this->post(route('water.accounts.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, WaterPaymentAccount::where('is_active', true)->count());
        $this->assertSame($this->account->id, $m->collection->fresh()->payment_account_id);
        $this->assertFalse($this->account->fresh()->is_active);
        $this->actingAs($this->faculty)->get(route('water.qr', $m->collection))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('water.qr', $m->collection))->assertForbidden();
    }

    public function test_draft_closed_paid_and_pending_cash_rules(): void
    {
        $m = $this->collection('draft');
        $this->actingAs($this->faculty)->post(route('water.submit', $m), $this->data())->assertForbidden();
        $m->collection->update(['status' => 'published', 'published_at' => now()]);
        $s = $this->pending($m);
        $this->actingAs($this->officer)->post(route('water.cash.store', $m), $this->data())->assertSessionHasErrors('payment');
        $this->patch(route('water.transition', $m->collection), ['status' => 'draft', 'confirmed' => 1])->assertSessionHasErrors('payment');
        $this->patch(route('water.transition', $m->collection), ['status' => 'closed', 'confirmed' => 1])->assertSessionHasNoErrors();
        $this->patch(route('water.transition', $m->collection), ['status' => 'archived', 'confirmed' => 1])->assertSessionHasErrors('payment');
        $this->review($s)->assertSessionHasNoErrors();
        $this->actingAs($this->faculty)->post(route('water.submit', $m), $this->data())->assertForbidden();
    }

    public function test_pdf_is_generated_only_for_authorized_verified_payments_and_uses_snapshot(): void
    {
        $s = $this->pending();
        $this->review($s)->assertSessionHasNoErrors();
        $r = WaterReceipt::firstOrFail();
        $name = $r->snapshot['faculty_name'];
        $this->faculty->update(['first_name' => 'Changed']);
        $this->actingAs($this->faculty)->get(route('water.receipt', $r))->assertSee($name);
        $pdf = $this->get(route('water.receipt', [$r, 'download' => 1]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        if (getenv('FC_UI_PREVIEWS') === '1') {
            $dir = storage_path('app/private/ui-preview');
            if (!is_dir($dir)) mkdir($dir, 0700, true);
            file_put_contents($dir.'/manual-receipt.pdf', $pdf->getContent());
        }
        $this->assertDatabaseCount('water_receipts', 1);
        $this->actingAs(User::factory()->create())->get(route('water.receipt', [$r, 'download' => 1]))->assertForbidden();
        $r->member->update(['status' => 'pending']);
        $this->actingAs($this->faculty)->get(route('water.receipt', $r))->assertNotFound();
    }

    public function test_pdf_proofs_are_attachment_only_and_private(): void
    {
        $m = $this->collection();
        $file = UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
        $this->actingAs($this->faculty)->post(route('water.submit', $m), $this->data(['proof' => $file]))->assertSessionHasNoErrors();
        $s = $m->submissions()->firstOrFail();
        $response = $this->get(route('water.proof', $s))->assertOk()->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
    }

    public function test_mail_failure_does_not_roll_back_approval_or_duplicate_receipt(): void
    {
        $s = $this->pending();
        config(['water.email_enabled' => true, 'mail.default' => 'log']);
        $this->review($s)->assertSessionHasNoErrors();
        $this->assertSame('paid', $s->member->fresh()->status);
        $this->assertDatabaseCount('water_receipts', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'water.email_failed']);
        $this->assertTrue(WaterNotice::where('user_id', $this->faculty->id)->exists());
        $this->review($s)->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('water_receipts', 1);
    }

    public function test_unique_database_constraints_prevent_duplicate_assignments_receipts_and_reference_claims(): void
    {
        $s = $this->pending();
        $this->assertSame(0, DB::table('water_reference_claims')->insertOrIgnore(['reference_lookup' => $s->reference_lookup, 'water_member_id' => $s->water_member_id]));
        $this->assertSame(0, DB::table('water_members')->insertOrIgnore(['water_collection_id' => $s->member->water_collection_id, 'user_id' => $this->faculty->id, 'assigned_amount' => 100, 'assigned_at' => now()]));
        $this->review($s)->assertSessionHasNoErrors();
        $copy = (array) DB::table('water_receipts')->first();
        unset($copy['id']);
        $this->assertSame(0, DB::table('water_receipts')->insertOrIgnore($copy));
        $this->assertDatabaseCount('water_receipts', 1);
    }
}
