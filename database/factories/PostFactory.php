<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(), '.');

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->paragraph(),
            'body' => '## '.fake()->sentence()."\n\n".implode("\n\n", fake()->paragraphs(4)),
            'status' => PostStatus::Draft,
            'published_at' => null,
            'first_published_at' => null,
        ];
    }

    /**
     * Indicate that the post is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Draft,
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the post is scheduled to publish in the future.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Scheduled,
            'published_at' => fake()->dateTimeBetween('+1 hour', '+1 month'),
        ]);
    }

    /**
     * Indicate that the post has been published.
     */
    public function published(): static
    {
        return $this->state(function (array $attributes) {
            $publishedAt = fake()->dateTimeBetween('-1 year', '-1 hour');

            return [
                'status' => PostStatus::Published,
                'published_at' => $publishedAt,
                'first_published_at' => $publishedAt,
            ];
        });
    }
}
