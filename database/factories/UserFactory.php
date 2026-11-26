<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'faculty_id' => fake()->unique()->bothify('FC-####'),
            'first_name' => $firstName,
            'middle_name' => fake()->optional()->lastName(),
            'last_name' => $lastName,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'department' => fake()->randomElement(['Computer Science', 'Information Technology', 'Information Systems']),
            'position' => fake()->randomElement(['Instructor', 'Assistant Professor', 'Associate Professor']),
            'contact_number' => fake()->phoneNumber(),
            'role' => User::ROLE_FACULTY,
            'status' => 'active',
            'profile_photo' => null,
            'password' => static::$password ??= Hash::make('password'),
            'activated_at' => now(),
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
            'security_version' => 0,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
