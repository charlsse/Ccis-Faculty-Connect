<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'title' => fake()->sentence(5),
            'category' => fake()->randomElement(['Meeting', 'Finance', 'Inventory', 'Event']),
            'audience' => 'All Faculty',
            'priority' => fake()->randomElement(['normal', 'important']),
            'body' => fake()->paragraphs(2, true),
            'image_path' => null,
            'is_pinned' => false,
            'published_at' => now()->subDays(fake()->numberBetween(1, 10)),
            'expires_at' => null,
        ];
    }
}
