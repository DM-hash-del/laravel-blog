<?php

use App\Enums\AiGenerationStatus;
use App\Models\AiGeneration;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Fake a Chat Completions reply whose message content is the given suggestions.
 *
 * @param  array<string, mixed>|string  $content
 * @param  array<string, mixed>  $message
 */
function fakeOpenAiReply(array|string $content, string $finishReason = 'stop', array $message = []): void
{
    Http::fake(['api.openai.com/*' => Http::response([
        'id' => 'chatcmpl-test',
        'model' => 'test-model',
        'choices' => [[
            'index' => 0,
            'finish_reason' => $finishReason,
            'message' => ['role' => 'assistant', 'content' => is_string($content) ? $content : json_encode($content), 'refusal' => null, ...$message],
        ]],
        'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 480, 'total_tokens' => 600],
    ])]);
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->category = Category::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);
    $this->editor = route('admin.posts.create');
});

/**
 * An editor submission with only a usable title, so everything else is generated.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function assistInput(array $overrides = []): array
{
    return [
        'title' => 'Testing Laravel Applications',
        'excerpt' => '',
        'body' => '',
        'category_id' => '',
        'new_category' => '',
        'new_tags' => '',
        'enhance' => ['title' => '0', 'excerpt' => '1', 'body' => '1', 'category' => '1', 'tags' => '1'],
        ...$overrides,
    ];
}

test('guests and non-admins cannot use the AI', function () {
    $this->post(route('admin.posts.assist'), assistInput())->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->post(route('admin.posts.assist'), assistInput())
        ->assertForbidden();

    Http::assertNothingSent();
    $this->assertDatabaseCount('ai_generations', 0);
});

test('nothing is sent unless a field has enough to work from', function () {
    $this->actingAs($this->admin)
        ->from($this->editor)
        ->post(route('admin.posts.assist'), assistInput(['title' => 'Too short']))
        ->assertRedirect($this->editor)
        ->assertSessionHasErrors('ai')
        ->assertSessionHasInput('title', 'Too short');

    Http::assertNothingSent();
    $this->assertDatabaseCount('ai_generations', 0);
});

test('nothing is sent when every field is filled and none is ticked', function () {
    $tags = Tag::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->from($this->editor)
        ->post(route('admin.posts.assist'), assistInput([
            'excerpt' => 'An excerpt',
            'body' => 'A body',
            'category_id' => $this->category->id,
            'tags' => $tags->modelKeys(),
            'enhance' => ['title' => '0', 'excerpt' => '0', 'body' => '0', 'category' => '0', 'tags' => '0'],
        ]))
        ->assertSessionHasErrors(['ai' => 'Nothing to generate or enhance: tick Enhance with AI on a field.']);

    Http::assertNothingSent();
});

test('suggestions are filled into the editor for review and the request is logged as completed', function () {
    $php = Tag::factory()->create(['name' => 'php', 'slug' => 'php']);
    fakeOpenAiReply([
        'excerpt' => 'How to test Laravel apps with Pest.',
        'body' => "## Why test\n\nBecause.",
        'category' => 'Laravel',
        'tags' => ['PHP', 'pest', 'testing'],
    ]);

    $this->actingAs($this->admin)
        ->from($this->editor)
        ->post(route('admin.posts.assist'), assistInput())
        ->assertRedirect($this->editor)
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Suggestions added. Review them, then save.')
        ->assertSessionHasInput('title', 'Testing Laravel Applications')
        ->assertSessionHasInput('excerpt', 'How to test Laravel apps with Pest.')
        ->assertSessionHasInput('body', "## Why test\n\nBecause.")
        ->assertSessionHasInput('category_id', $this->category->id)
        ->assertSessionHasInput('tags', [$php->id])
        ->assertSessionHasInput('new_tags', 'pest, testing');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request['model'] === 'test-model');

    $generation = AiGeneration::sole();
    expect($generation)
        ->status->toBe(AiGenerationStatus::Completed)
        ->user_id->toBe($this->admin->id)
        ->model->toBe('test-model')
        ->prompt_tokens->toBe(120)
        ->completion_tokens->toBe(480)
        ->total_tokens->toBe(600)
        ->duration_ms->not->toBeNull()
        ->field_states->toBe(['title' => 'untouched', 'excerpt' => 'generate', 'body' => 'generate', 'category' => 'generate', 'tags' => 'generate'])
        ->and($generation->response_payload['usage']['total_tokens'])->toBe(600);

    $this->assertDatabaseCount('posts', 0);
    $this->assertDatabaseCount('tags', 1);
});

test('output for an untouched field is discarded', function () {
    fakeOpenAiReply([
        'title' => 'A Title The Admin Did Not Ask For',
        'excerpt' => 'Generated excerpt.',
        'body' => 'Generated body.',
        'category' => 'Laravel',
        'tags' => ['testing'],
    ]);

    $this->actingAs($this->admin)
        ->from($this->editor)
        ->post(route('admin.posts.assist'), assistInput())
        ->assertSessionHasInput('title', 'Testing Laravel Applications');
});

test('enhancing an existing post fills the form but saves nothing', function () {
    $post = Post::factory()->draft()->for($this->category)->create(['title' => 'Original Saved Title', 'body' => 'Original saved body.']);
    $editor = route('admin.posts.edit', $post);
    fakeOpenAiReply(['body' => 'A much better body.']);

    $this->actingAs($this->admin)
        ->from($editor)
        ->put(route('admin.posts.assist', $post), assistInput([
            'title' => 'Original Saved Title',
            'excerpt' => 'An excerpt the admin wrote.',
            'body' => 'Original saved body.',
            'category_id' => $this->category->id,
            'new_tags' => 'one, two, three',
            'enhance' => ['title' => '0', 'excerpt' => '0', 'body' => '1', 'category' => '0', 'tags' => '0'],
        ]))
        ->assertRedirect($editor)
        ->assertSessionHasInput('body', 'A much better body.');

    expect($post->fresh()->body)->toBe('Original saved body.')
        ->and(AiGeneration::sole()->post_id)->toBe($post->id);
    Http::assertSent(fn (Request $request) => array_keys($request['response_format']['json_schema']['schema']['properties']) === ['body']);
});

test('the API key is never stored', function () {
    fakeOpenAiReply(['excerpt' => 'E', 'body' => 'B', 'category' => 'Laravel', 'tags' => []]);

    $this->actingAs($this->admin)->from($this->editor)->post(route('admin.posts.assist'), assistInput());

    expect(json_encode(AiGeneration::sole()->getAttributes()))->not->toContain('test-key');
});

test('failures keep the admin\'s input, show a friendly message and are logged', function (Closure $fake, string $reason) {
    $fake();

    $this->actingAs($this->admin)
        ->from($this->editor)
        ->post(route('admin.posts.assist'), assistInput(['body' => 'Words the admin typed.']))
        ->assertRedirect($this->editor)
        ->assertSessionHasErrors('ai')
        ->assertSessionHasInput('body', 'Words the admin typed.');

    $generation = AiGeneration::sole();
    expect($generation->status)->toBe(AiGenerationStatus::Failed)
        ->and($generation->error_message)->toBe($reason)
        ->and($generation->duration_ms)->not->toBeNull();
})->with([
    'rate limited' => [fn () => Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Rate limit reached']], 429)]), 'http_429'],
    'server error' => [fn () => Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Oops']], 500)]), 'http_500'],
    'timeout' => [fn () => Http::fake(['api.openai.com/*' => Http::failedConnection()]), 'timeout'],
    'not JSON' => [fn () => fakeOpenAiReply('this is not json'), 'malformed'],
    'missing a field' => [fn () => fakeOpenAiReply(['excerpt' => 'Only an excerpt']), 'malformed'],
    'category not in the list' => [fn () => fakeOpenAiReply(['excerpt' => 'E', 'body' => 'B', 'category' => 'Invented', 'tags' => []]), 'malformed'],
    'too many tags' => [fn () => fakeOpenAiReply(['excerpt' => 'E', 'body' => 'B', 'category' => 'Laravel', 'tags' => ['a', 'b', 'c', 'd', 'e', 'f', 'g']]), 'malformed'],
    'refusal' => [fn () => fakeOpenAiReply('', 'stop', ['content' => null, 'refusal' => 'I cannot help with that.']), 'refusal'],
    'cut off' => [fn () => fakeOpenAiReply('{"excerpt": "Trunc', 'length'), 'length'],
]);

test('OpenAI error text, which can echo part of the key, is never stored', function () {
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key provided: sk-proj-****abcd.']], 401)]);

    $this->actingAs($this->admin)
        ->from($this->editor)
        ->post(route('admin.posts.assist'), assistInput())
        ->assertSessionHasErrors(['ai' => 'OpenAI returned an error (status 401). Your text is unchanged.']);

    expect(AiGeneration::sole()->error_message)->toBe('http_401')
        ->and(json_encode(AiGeneration::sole()->getAttributes()))->not->toContain('sk-proj');
});

test('a friendly message is shown when AI is not configured, and nothing is sent', function () {
    config(['services.openai.key' => null]);

    $this->actingAs($this->admin)
        ->from($this->editor)
        ->post(route('admin.posts.assist'), assistInput())
        ->assertSessionHasErrors('ai');

    Http::assertNothingSent();
    $this->assertDatabaseCount('ai_generations', 0);
});

test('the AI route is throttled with a friendly message that keeps the input', function () {
    fakeOpenAiReply(['excerpt' => 'E', 'body' => 'B', 'category' => 'Laravel', 'tags' => []]);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->actingAs($this->admin)
            ->from($this->editor)
            ->post(route('admin.posts.assist'), assistInput(['title' => "Testing Laravel Applications {$attempt}"]))
            ->assertRedirect($this->editor)
            ->assertSessionHasNoErrors();
    }

    $this->actingAs($this->admin)
        ->from($this->editor)
        ->post(route('admin.posts.assist'), assistInput(['title' => 'One Request Too Many']))
        ->assertRedirect($this->editor)
        ->assertSessionHasErrors('ai')
        ->assertSessionHasInput('title', 'One Request Too Many');

    expect(session('errors')->first('ai'))->toStartWith('Too many AI requests. Try again in ');
    $this->assertDatabaseCount('ai_generations', 5);
});

test('pressing Generate again with the same input reuses the result instead of calling OpenAI twice', function () {
    fakeOpenAiReply(['excerpt' => 'Reused excerpt.', 'body' => 'B', 'category' => 'Laravel', 'tags' => []]);

    foreach ([1, 2] as $press) {
        $this->actingAs($this->admin)
            ->from($this->editor)
            ->post(route('admin.posts.assist'), assistInput())
            ->assertSessionHasNoErrors()
            ->assertSessionHasInput('excerpt', 'Reused excerpt.');
    }

    Http::assertSentCount(1);
    $this->assertDatabaseCount('ai_generations', 1);
});

test('a request with different input makes its own call', function () {
    fakeOpenAiReply(['excerpt' => 'E', 'body' => 'B', 'category' => 'Laravel', 'tags' => []]);

    $this->actingAs($this->admin)->from($this->editor)->post(route('admin.posts.assist'), assistInput(['title' => 'Testing Laravel Applications']));
    $this->actingAs($this->admin)->from($this->editor)->post(route('admin.posts.assist'), assistInput(['title' => 'Deploying Laravel Applications']));

    Http::assertSentCount(2);
    $this->assertDatabaseCount('ai_generations', 2);
});

test('a repeated failure is reused rather than retried straight away', function () {
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Busy']], 503)]);

    foreach ([1, 2] as $press) {
        $this->actingAs($this->admin)
            ->from($this->editor)
            ->post(route('admin.posts.assist'), assistInput())
            ->assertSessionHasErrors(['ai' => 'OpenAI returned an error (status 503). Your text is unchanged.']);
    }

    Http::assertSentCount(1);
    $this->assertDatabaseCount('ai_generations', 1);
});

test('a request made while another is still running is turned away without calling OpenAI', function () {
    config(['services.openai.duplicate_wait' => 1]);
    $runningRequest = Cache::lock('ai-assist:'.$this->admin->id, 30);
    $runningRequest->get();

    $this->actingAs($this->admin)
        ->from($this->editor)
        ->post(route('admin.posts.assist'), assistInput(['body' => 'Words the admin typed.']))
        ->assertRedirect($this->editor)
        ->assertSessionHasErrors(['ai' => 'Your previous AI request is still running. Try again in a moment.'])
        ->assertSessionHasInput('body', 'Words the admin typed.');

    $runningRequest->release();
    Http::assertNothingSent();
    $this->assertDatabaseCount('ai_generations', 0);
});

test('when no existing category fits, the category stays empty with a note', function () {
    fakeOpenAiReply(['excerpt' => 'E', 'body' => 'B', 'category' => null, 'tags' => []]);

    $this->actingAs($this->admin)
        ->from($this->editor)
        ->post(route('admin.posts.assist'), assistInput())
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Suggestions added. Review them, then save. No existing category fits this post, so choose one or type a new one.');

    expect(session()->getOldInput())->toHaveKey('category_id')
        ->and(session()->getOldInput('category_id'))->toBeNull();

    expect(AiGeneration::sole()->status)->toBe(AiGenerationStatus::Completed);
    $this->assertDatabaseCount('categories', 1);
});

test('a chosen category is never sent to be changed, even if enhance is posted for it', function () {
    $other = Category::factory()->create(['name' => 'PHP', 'slug' => 'php']);
    fakeOpenAiReply(['excerpt' => 'E', 'body' => 'B', 'tags' => []]);

    $this->actingAs($this->admin)
        ->from($this->editor)
        ->post(route('admin.posts.assist'), assistInput([
            'category_id' => $this->category->id,
            'enhance' => ['title' => '0', 'excerpt' => '1', 'body' => '1', 'category' => '1', 'tags' => '1'],
        ]))
        ->assertSessionHasNoErrors()
        ->assertSessionHasInput('category_id', $this->category->id);

    Http::assertSent(fn (Request $request) => ! array_key_exists('category', $request['response_format']['json_schema']['schema']['properties']));
    expect(AiGeneration::sole()->field_states['category'])->toBe('untouched');
});

test('the editor shows Enhance boxes, ticked only for empty fields', function () {
    $post = Post::factory()->draft()->create(['excerpt' => null]);

    $this->actingAs($this->admin)
        ->get(route('admin.posts.edit', $post))
        ->assertOk()
        ->assertSee('Generate / enhance with AI')
        ->assertSee('<input type="checkbox" name="enhance[excerpt]" value="1" checked', false)
        ->assertSee('<input type="checkbox" name="enhance[title]" value="1"', false)
        ->assertDontSee('<input type="checkbox" name="enhance[title]" value="1" checked', false)
        ->assertDontSee('name="enhance[category]"', false);
});

test('the in-place mode gets the suggestions as JSON, with nothing flashed to the session', function () {
    $php = Tag::factory()->create(['name' => 'php', 'slug' => 'php']);
    fakeOpenAiReply([
        'excerpt' => 'In place excerpt.',
        'body' => '## In place',
        'category' => 'Laravel',
        'tags' => ['PHP', 'pest'],
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('admin.posts.assist'), assistInput())
        ->assertOk()
        ->assertJsonPath('status', 'Suggestions added. Review them, then save.')
        ->assertJsonPath('input.title', 'Testing Laravel Applications')
        ->assertJsonPath('input.excerpt', 'In place excerpt.')
        ->assertJsonPath('input.body', '## In place')
        ->assertJsonPath('input.category_id', $this->category->id)
        ->assertJsonPath('input.tags', [$php->id])
        ->assertJsonPath('input.new_tags', 'pest')
        ->assertJsonMissingPath('input.enhance')
        ->assertJsonMissingPath('input._token');

    expect(AiGeneration::sole()->status)->toBe(AiGenerationStatus::Completed)
        ->and(session()->has('_old_input'))->toBeFalse()
        ->and(session()->has('status'))->toBeFalse();
    $this->assertDatabaseCount('posts', 0);
});

test('the in-place mode reports failures as JSON and logs them', function () {
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Busy']], 503)]);

    $this->actingAs($this->admin)
        ->postJson(route('admin.posts.assist'), assistInput())
        ->assertUnprocessable()
        ->assertJsonPath('errors.ai.0', 'OpenAI returned an error (status 503). Your text is unchanged.');

    expect(AiGeneration::sole()->status)->toBe(AiGenerationStatus::Failed)
        ->and(session()->has('_old_input'))->toBeFalse();
});

test('the in-place mode still applies the minimum-content check before anything is sent', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.posts.assist'), assistInput(['title' => 'Too short']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ai');

    Http::assertNothingSent();
    $this->assertDatabaseCount('ai_generations', 0);
});

test('the in-place mode gets the rate limit as JSON with the wait in seconds', function () {
    fakeOpenAiReply(['excerpt' => 'E', 'body' => 'B', 'category' => 'Laravel', 'tags' => []]);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->actingAs($this->admin)->postJson(route('admin.posts.assist'), assistInput(['title' => "Testing Laravel Applications {$attempt}"]))->assertOk();
    }

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.posts.assist'), assistInput(['title' => 'One Request Too Many']))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');

    expect($response->json('retry_after'))->toBeInt()->toBeGreaterThan(0)
        ->and($response->json('message'))->toBe("Too many AI requests. Try again in {$response->json('retry_after')} seconds.");
});

test('the in-place mode works when editing an existing post', function () {
    $post = Post::factory()->draft()->for($this->category)->create(['title' => 'Original Saved Title', 'body' => 'Original saved body.']);
    fakeOpenAiReply(['body' => 'A much better body.']);

    $this->actingAs($this->admin)
        ->putJson(route('admin.posts.assist', $post), assistInput([
            'title' => 'Original Saved Title',
            'excerpt' => 'An excerpt the admin wrote.',
            'body' => 'Original saved body.',
            'category_id' => $this->category->id,
            'new_tags' => 'one, two, three',
            'enhance' => ['title' => '0', 'excerpt' => '0', 'body' => '1', 'tags' => '0'],
        ]))
        ->assertOk()
        ->assertJsonPath('input.body', 'A much better body.')
        ->assertJsonPath('input.excerpt', 'An excerpt the admin wrote.');

    expect($post->fresh()->body)->toBe('Original saved body.');
});
