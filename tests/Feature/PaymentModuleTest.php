<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_member_only_sees_their_own_payments(): void
    {
        $faculty = User::factory()->create([
            'role' => User::ROLE_FACULTY,
        ]);
        $otherFaculty = User::factory()->create([
            'role' => User::ROLE_FACULTY,
        ]);

        Payment::factory()->create([
            'user_id' => $faculty->id,
            'reference_no' => 'OWN-PAYMENT-001',
            'description' => 'Own Monthly Contribution',
        ]);
        Payment::factory()->create([
            'user_id' => $otherFaculty->id,
            'reference_no' => 'OTHER-PAYMENT-001',
            'description' => 'Other Faculty Contribution',
        ]);

        $this->actingAs($faculty)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Own Monthly Contribution')
            ->assertDontSee('Other Faculty Contribution');
    }

    public function test_treasurer_can_create_and_update_payment_record(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_TREASURER,
        ]);
        $faculty = User::factory()->create([
            'role' => User::ROLE_FACULTY,
        ]);

        $this->actingAs($admin)
            ->post(route('payments.store'), [
                'user_id' => $faculty->id,
                'reference_no' => 'FC-PAY-TEST-001',
                'description' => 'Monthly Contribution',
                'amount' => 500,
                'status' => 'pending',
                'due_date' => now()->endOfMonth()->toDateString(),
                'notes' => 'Initial collection record.',
            ])
            ->assertRedirect();

        $payment = Payment::whereReference('FC-PAY-TEST-001')->firstOrFail();
        $this->assertSame('pending', $payment->status);

        $this->actingAs($admin)
            ->patch(route('payments.update', $payment), [
                'user_id' => $faculty->id,
                'reference_no' => 'FC-PAY-TEST-001',
                'description' => 'Monthly Contribution',
                'amount' => 500,
                'status' => 'paid',
                'due_date' => now()->endOfMonth()->toDateString(),
                'notes' => 'Confirmed payment.',
            ])
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_faculty_member_cannot_create_payment_record(): void
    {
        $faculty = User::factory()->create([
            'role' => User::ROLE_FACULTY,
        ]);

        $this->actingAs($faculty)
            ->post(route('payments.store'), [
                'user_id' => $faculty->id,
                'reference_no' => 'FC-PAY-BLOCKED',
                'description' => 'Blocked Contribution',
                'amount' => 500,
                'status' => 'paid',
            ])
            ->assertForbidden();
    }
}
