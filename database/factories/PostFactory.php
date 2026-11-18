<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => rtrim(fake()->unique()->sentence(4), '.'),
            'body' => implode("\n\n", fake()->paragraphs(3)),
            'status' => PostStatus::Draft,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => PostStatus::Draft]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Scheduled,
            'publish_at' => now()->addWeek(),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Published,
            'published_at' => fake()->dateTimeBetween('-1 month'),
        ]);
    }
}
