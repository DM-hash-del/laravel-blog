<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-07-01 12:00:00', 'UTC'));
    $this->admin = User::factory()->admin()->create();
    Category::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);
    $this->editor = route('admin.posts.create');

    Http::fake(['api.openai.com/*' => Http::response([
        'id' => 'chatcmpl-test',
        'model' => 'test-model',
        'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode(['excerpt' => 'E', 'body' => 'B', 'category' => 'Laravel', 'tags' => []]), 'refusal' => null]]],
        'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
    ])]);
});

/**
 * Use up the AI limit with five different requests, then make one more.
 */
function hitTheAiLimit(bool $asJson = false): TestResponse
{
    $input = fn (int $attempt) => [
        'title' => "Testing Laravel Applications {$attempt}",
        'enhance' => ['title' => '0', 'excerpt' => '1', 'body' => '1', 'tags' => '1'],
    ];

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $asJson
            ? test()->actingAs(test()->admin)->postJson(route('admin.posts.assist'), $input($attempt))
            : test()->actingAs(test()->admin)->from(test()->editor)->post(route('admin.posts.assist'), $input($attempt));
    }

    return $asJson
        ? test()->actingAs(test()->admin)->postJson(route('admin.posts.assist'), $input(6))
        : test()->actingAs(test()->admin)->from(test()->editor)->post(route('admin.posts.assist'), $input(6));
}

/**
 * Read the remaining wait the editor renders on the Generate button, or null when there is none.
 */
function renderedWait(string $html): ?int
{
    return preg_match('/data-retry-after="(\d+)"/', $html, $match) ? (int) $match[1] : null;
}

test('without a limit hit, Generate is enabled with no countdown', function () {
    $html = $this->actingAs($this->admin)->get($this->editor)->assertOk()->getContent();

    expect(renderedWait($html))->toBeNull()
        ->and($html)->not->toContain('Too many AI requests');
});

test('after hitting the limit, the editor renders Generate disabled with the remaining wait', function () {
    hitTheAiLimit()->assertRedirect($this->editor);

    $html = $this->actingAs($this->admin)->get($this->editor)->assertOk()->getContent();
    $wait = renderedWait($html);

    expect($wait)->toBeGreaterThan(0)->toBeLessThanOrEqual(60)
        ->and($html)->toMatch('/disabled data-retry-after="\d+"/')
        ->and(substr_count($html, 'Too many AI requests.'))->toBe(1)
        ->and($html)->toContain("Try again in {$wait} seconds.");
});

test('the wait stays in sync across refreshes', function () {
    hitTheAiLimit();
    $first = renderedWait($this->actingAs($this->admin)->get($this->editor)->getContent());

    $this->travel(20)->seconds();
    $second = renderedWait($this->actingAs($this->admin)->get($this->editor)->getContent());

    expect($second)->toBe($first - 20);
});

test('once the wait is over, Generate is enabled again', function () {
    hitTheAiLimit();
    $wait = renderedWait($this->actingAs($this->admin)->get($this->editor)->getContent());

    $this->travel($wait + 1)->seconds();
    $html = $this->actingAs($this->admin)->get($this->editor)->getContent();

    expect(renderedWait($html))->toBeNull()
        ->and($html)->not->toContain('Too many AI requests');
});

test('the in-place path reports the same wait the editor then renders', function () {
    $response = hitTheAiLimit(asJson: true)->assertTooManyRequests();

    $wait = renderedWait($this->actingAs($this->admin)->get($this->editor)->getContent());

    expect($wait)->toBe($response->json('retry_after'));
});
