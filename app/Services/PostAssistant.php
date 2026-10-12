<?php

namespace App\Services;

use App\Enums\AiGenerationStatus;
use App\Models\AiGeneration;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\AiAssist\AiAssistFailed;
use App\Services\AiAssist\FieldStates;
use App\Services\AiAssist\RequestBuilder;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use JsonException;

/**
 * The one class that calls OpenAI: asks for post suggestions and logs every request.
 *
 * Each request is logged as pending before the call, then completed or failed.
 * OpenAI's own error text is never stored, because some errors echo part of the key.
 */
class PostAssistant
{
    public function __construct(private readonly RequestBuilder $requestBuilder) {}

    /**
     * How long a finished request's outcome is kept for an identical repeat (a double-click) to reuse.
     */
    private const int OutcomeSeconds = 15;

    /**
     * Ask for suggestions for the fields to generate or enhance.
     *
     * Only one request per admin runs at a time. A repeat of the same request waits
     * for the running one and reuses its outcome instead of calling OpenAI again.
     *
     * @param  array{title: string, excerpt: string, body: string, category: string, tags: list<string>}  $values
     * @return array<string, mixed> suggestions keyed by field; untouched fields are never included
     *
     * @throws AiAssistFailed
     */
    public function suggest(User $user, ?Post $post, array $values, FieldStates $states): array
    {
        if (blank(config('services.openai.key')) || blank(config('services.openai.model'))) {
            throw new AiAssistFailed('AI help is not set up yet: add OPENAI_API_KEY and OPENAI_MODEL to the .env file.');
        }

        $categoryNames = Category::orderBy('name')->pluck('name')->all();
        $askedFields = $this->requestBuilder->askedFields($states, $categoryNames);

        if ($askedFields === []) {
            throw new AiAssistFailed('There are no categories to choose from yet. Add one in the Categories panel, or tick Enhance on another field.');
        }

        $body = $this->requestBuilder->build($values, $states, $categoryNames, Tag::orderBy('name')->pluck('name')->all());
        $outcomeKey = "ai-assist:{$user->id}:".hash('sha256', json_encode($body, JSON_THROW_ON_ERROR));

        try {
            return Cache::lock("ai-assist:{$user->id}", config('services.openai.timeout') + 15)
                ->block(config('services.openai.duplicate_wait'), function () use ($outcomeKey, $user, $post, $values, $states, $body, $askedFields, $categoryNames): array {
                    $outcome = Cache::get($outcomeKey);

                    if (is_array($outcome)) {
                        return array_key_exists('failure', $outcome)
                            ? throw new AiAssistFailed($outcome['failure'])
                            : $outcome['suggestions'];
                    }

                    try {
                        $suggestions = $this->requestSuggestions($user, $post, $values, $states, $body, $askedFields, $categoryNames);
                    } catch (AiAssistFailed $failure) {
                        Cache::put($outcomeKey, ['failure' => $failure->getMessage()], self::OutcomeSeconds);

                        throw $failure;
                    }

                    Cache::put($outcomeKey, ['suggestions' => $suggestions], self::OutcomeSeconds);

                    return $suggestions;
                });
        } catch (LockTimeoutException) {
            throw new AiAssistFailed('Your previous AI request is still running. Try again in a moment.');
        }
    }

    /**
     * Send one request to OpenAI and log it: pending first, then completed or failed.
     *
     * @param  array{title: string, excerpt: string, body: string, category: string, tags: list<string>}  $values
     * @param  array<string, mixed>  $body
     * @param  list<string>  $askedFields
     * @param  list<string>  $categoryNames
     * @return array<string, mixed>
     *
     * @throws AiAssistFailed
     */
    private function requestSuggestions(User $user, ?Post $post, array $values, FieldStates $states, array $body, array $askedFields, array $categoryNames): array
    {
        $generation = $user->aiGenerations()->create([
            'post_id' => $post?->id,
            'topic_input' => $values['title'] !== '' ? $values['title'] : null,
            'field_states' => $states->toArray(),
            'request_payload' => $body,
            'model' => $body['model'],
        ]);

        $startedAt = hrtime(true);

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(config('services.openai.timeout'))
                ->post(config('services.openai.url'), $body);
        } catch (ConnectionException) {
            $this->fail($generation, $startedAt, 'timeout', "OpenAI didn't reply in time. Your text is unchanged; try again in a moment.");
        }

        $suggestions = $this->suggestionsFrom($response, $generation, $startedAt, $askedFields, $categoryNames);

        $generation->update([
            'status' => AiGenerationStatus::Completed,
            'response_payload' => [
                'id' => $response->json('id'),
                'model' => $response->json('model'),
                'suggestions' => $suggestions,
                'usage' => $response->json('usage'),
            ],
            'prompt_tokens' => $response->json('usage.prompt_tokens'),
            'completion_tokens' => $response->json('usage.completion_tokens'),
            'total_tokens' => $response->json('usage.total_tokens'),
            'duration_ms' => $this->elapsedMilliseconds($startedAt),
        ]);

        return Arr::only($suggestions, $askedFields);
    }

    /**
     * Read and validate the suggestions from a reply, failing the generation if they can't be used.
     *
     * @param  list<string>  $askedFields
     * @param  list<string>  $categoryNames
     * @return array<string, mixed>
     *
     * @throws AiAssistFailed
     */
    private function suggestionsFrom(Response $response, AiGeneration $generation, int $startedAt, array $askedFields, array $categoryNames): array
    {
        if ($response->status() === 429) {
            $this->fail($generation, $startedAt, 'http_429', 'OpenAI is limiting requests or the account has run out of credit. Try again shortly.');
        }

        if (! $response->successful()) {
            $this->fail($generation, $startedAt, 'http_'.$response->status(), "OpenAI returned an error (status {$response->status()}). Your text is unchanged.");
        }

        if (filled($response->json('choices.0.message.refusal'))) {
            $this->fail($generation, $startedAt, 'refusal', 'OpenAI declined to write this. Try rewording the title or content.');
        }

        if ($response->json('choices.0.finish_reason') === 'length') {
            $this->fail($generation, $startedAt, 'length', 'The reply was cut off by the token limit. Try again, or raise OPENAI_MAX_COMPLETION_TOKENS.');
        }

        try {
            $suggestions = json_decode((string) $response->json('choices.0.message.content'), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $suggestions = null;
        }

        if (! is_array($suggestions) || Validator::make($suggestions, $this->replyRules($askedFields, $categoryNames))->fails()) {
            $this->fail($generation, $startedAt, 'malformed', "OpenAI's reply couldn't be used. Your text is unchanged; try again.");
        }

        return $suggestions;
    }

    /**
     * Get the rules a reply must pass for each asked-for field.
     *
     * @param  list<string>  $askedFields
     * @param  list<string>  $categoryNames
     * @return array<string, array<int, mixed>>
     */
    private function replyRules(array $askedFields, array $categoryNames): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['required', 'string'],
            'body' => ['required', 'string'],
            'category' => ['present', 'nullable', 'string', Rule::in($categoryNames)],
            'tags' => ['present', 'array', 'max:6'],
        ];

        $askedRules = Arr::only($rules, $askedFields);

        if (in_array('tags', $askedFields, true)) {
            $askedRules['tags.*'] = ['required', 'string', 'max:50'];
        }

        return $askedRules;
    }

    /**
     * Mark the generation as failed, without storing OpenAI's error text, and stop with a friendly message.
     *
     * @throws AiAssistFailed
     */
    private function fail(AiGeneration $generation, int $startedAt, string $reason, string $friendlyMessage): never
    {
        $generation->update([
            'status' => AiGenerationStatus::Failed,
            'error_message' => $reason,
            'duration_ms' => $this->elapsedMilliseconds($startedAt),
        ]);

        throw new AiAssistFailed($friendlyMessage);
    }

    /**
     * Get the milliseconds since a high-resolution start time.
     */
    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
