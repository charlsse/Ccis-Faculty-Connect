<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        $status = fake()->randomElement(['paid', 'pending']);

        return [
            'user_id' => User::factory(),
            'recorded_by' => null,
            'reference_no' => fake()->unique()->bothify('FC-PAY-####'),
            'description' => fake()->randomElement(['Monthly Contribution', 'Faculty Activity Contribution']),
            'amount' => fake()->randomElement([200, 500, 750]),
            'status' => $status,
            'due_date' => now()->addDays(10)->toDateString(),
            'paid_at' => $status === 'paid' ? now()->subDays(2) : null,
            'notes' => null,
        ];
    }
}
