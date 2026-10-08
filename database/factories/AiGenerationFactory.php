<?php

namespace Database\Factories;

use App\Enums\AiGenerationStatus;
use App\Models\AiGeneration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiGeneration>
 */
class AiGenerationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->sentence(), '.');
        $promptTokens = fake()->numberBetween(100, 800);
        $completionTokens = fake()->numberBetween(200, 1500);

        return [
            'post_id' => null,
            'user_id' => User::factory(),
            'topic_input' => $title,
            'field_states' => [
                'title' => 'untouched',
                'excerpt' => 'generate',
                'body' => 'generate',
                'category' => 'generate',
                'tags' => 'generate',
            ],
            'request_payload' => [
                'fields' => [
                    'title' => ['state' => 'untouched', 'value' => $title],
                    'excerpt' => ['state' => 'generate', 'value' => null],
                    'body' => ['state' => 'generate', 'value' => null],
                    'category' => ['state' => 'generate', 'value' => null],
                    'tags' => ['state' => 'generate', 'value' => []],
                ],
            ],
            'response_payload' => [
                'excerpt' => fake()->paragraph(),
                'body' => implode("\n\n", fake()->paragraphs(3)),
                'category' => fake()->word(),
                'tags' => fake()->words(3),
            ],
            'model' => 'test-model',
            'status' => AiGenerationStatus::Completed,
            'error_message' => null,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $promptTokens + $completionTokens,
            'duration_ms' => fake()->numberBetween(800, 20000),
        ];
    }

    /**
     * Indicate that the generation request is still waiting for a reply.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AiGenerationStatus::Pending,
            'response_payload' => null,
            'error_message' => null,
            'prompt_tokens' => null,
            'completion_tokens' => null,
            'total_tokens' => null,
            'duration_ms' => null,
        ]);
    }

    /**
     * Indicate that the generation request failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AiGenerationStatus::Failed,
            'response_payload' => null,
            'error_message' => 'The AI service timed out.',
            'prompt_tokens' => null,
            'completion_tokens' => null,
            'total_tokens' => null,
        ]);
    }
}
