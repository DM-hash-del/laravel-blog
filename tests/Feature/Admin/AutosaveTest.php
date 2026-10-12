<?php

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-07-01 13:32:00', 'UTC'));
    $this->admin = User::factory()->admin()->create();
    $this->category = Category::factory()->create();
});

test('the first autosave of a new post creates a draft and says where later saves go', function () {
    $tag = Tag::factory()->create();

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.posts.autosave'), postPayload([
            'title' => 'Work In Progress',
            'category_id' => $this->category->id,
            'excerpt' => 'Early excerpt.',
            'body' => 'Early body.',
            'tags' => [$tag->id],
        ]))
        ->assertOk();

    $post = Post::sole();
    expect($post)
        ->status->toBe(PostStatus::Draft)
        ->title->toBe('Work In Progress')
        ->slug->toBe('work-in-progress')
        ->user_id->toBe($this->admin->id)
        ->excerpt->toBe('Early excerpt.')
        ->body->toBe('Early body.')
        ->published_at->toBeNull()
        ->and($post->tags->pluck('id')->all())->toBe([$tag->id]);

    $response->assertExactJson([
        'saved_at' => '14:32',
        'form_action' => route('admin.posts.update', $post),
        'autosave_url' => route('admin.posts.autosave.update', $post),
        'assist_url' => route('admin.posts.assist', $post),
    ]);

    expect(session()->has('_old_input'))->toBeFalse()
        ->and(session()->has('status'))->toBeFalse();
});

test('later autosaves update the same draft and its slug follows the title', function () {
    $post = Post::factory()->draft()->for($this->category)->create(['title' => 'First Title']);

    $this->actingAs($this->admin)
        ->putJson(route('admin.posts.autosave.update', $post), postPayload(['title' => 'Better Title', 'category_id' => $this->category->id]))
        ->assertOk();

    expect(Post::count())->toBe(1)
        ->and($post->fresh())
        ->title->toBe('Better Title')
        ->slug->toBe('better-title')
        ->status->toBe(PostStatus::Draft);
});

test('autosave never publishes or schedules, whatever the form carried', function (string $action) {
    $this->actingAs($this->admin)
        ->postJson(route('admin.posts.autosave'), postPayload([
            'action' => $action,
            'category_id' => $this->category->id,
            'published_at' => '2026-07-05T09:00',
        ]))
        ->assertOk();

    expect(Post::sole())
        ->status->toBe(PostStatus::Draft)
        ->published_at->toBeNull()
        ->first_published_at->toBeNull();
})->with(['publish', 'schedule']);

test('scheduled and published posts are never autosaved', function (string $state) {
    $post = Post::factory()->{$state}()->for($this->category)->create(['title' => 'Live Title']);

    $this->actingAs($this->admin)
        ->putJson(route('admin.posts.autosave.update', $post), postPayload(['title' => 'Changed', 'category_id' => $this->category->id, 'slug' => $post->slug]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['post' => 'Only drafts are autosaved.']);

    expect($post->fresh()->title)->toBe('Live Title');
})->with(['scheduled', 'published']);

test('autosave never creates a category or tag from the typed fields', function () {
    $tag = Tag::factory()->create();

    $this->actingAs($this->admin)
        ->postJson(route('admin.posts.autosave'), postPayload([
            'category_id' => $this->category->id,
            'new_category' => 'Half Typed',
            'tags' => [$tag->id],
            'new_tags' => 'brand, new',
        ]))
        ->assertOk();

    expect(Category::count())->toBe(1)
        ->and(Tag::count())->toBe(1)
        ->and(Post::sole()->tags->pluck('id')->all())->toBe([$tag->id]);
});

test('the Save draft rules still apply and nothing is saved when they fail', function (array $overrides, string $field) {
    Post::factory()->create(['title' => 'Taken Title']);

    $this->actingAs($this->admin)
        ->postJson(route('admin.posts.autosave'), postPayload(['category_id' => $this->category->id, ...$overrides]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(Post::count())->toBe(1);
})->with([
    'no title' => [['title' => ''], 'title'],
    'no category' => [['category_id' => null], 'category_id'],
    'title URL taken' => [['title' => 'Taken title'], 'title'],
]);

test('guests and non-admins cannot autosave', function () {
    $this->postJson(route('admin.posts.autosave'), postPayload())->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->postJson(route('admin.posts.autosave'), postPayload())
        ->assertForbidden();

    expect(Post::count())->toBe(0);
});

test('autosave is throttled', function () {
    $post = Post::factory()->draft()->for($this->category)->create();

    for ($save = 1; $save <= 30; $save++) {
        $this->actingAs($this->admin)
            ->putJson(route('admin.posts.autosave.update', $post), postPayload(['title' => "Title {$save}", 'category_id' => $this->category->id]))
            ->assertOk();
    }

    $this->actingAs($this->admin)
        ->putJson(route('admin.posts.autosave.update', $post), postPayload(['title' => 'One Too Many', 'category_id' => $this->category->id]))
        ->assertTooManyRequests();
});

test('only new posts and drafts are wired for autosave, and the toast sits outside the form', function () {
    $draft = Post::factory()->draft()->create();
    $scheduled = Post::factory()->scheduled()->create();
    $published = Post::factory()->published()->create();
    $this->actingAs($this->admin);

    $create = $this->get(route('admin.posts.create'))->getContent();
    expect($create)->toContain('data-autosave data-autosave-url="'.route('admin.posts.autosave').'"')
        ->and($create)->toContain('<div data-toast hidden role="status" aria-live="polite"')
        ->and(strpos($create, 'data-toast'))->toBeGreaterThan(strrpos($create, '</form>'));

    $this->get(route('admin.posts.edit', $draft))
        ->assertSee('data-autosave data-autosave-url="'.route('admin.posts.autosave.update', $draft).'"', false);

    $this->get(route('admin.posts.edit', $scheduled))->assertDontSee('data-autosave', false);
    $this->get(route('admin.posts.edit', $published))->assertDontSee('data-autosave', false);
});

test('after an autosave, Save draft on the same page updates that draft instead of creating another', function () {
    $reply = $this->actingAs($this->admin)
        ->postJson(route('admin.posts.autosave'), postPayload(['title' => 'Autosaved First', 'category_id' => $this->category->id]))
        ->assertOk()
        ->json();

    $this->actingAs($this->admin)
        ->put($reply['form_action'], postPayload(['action' => 'draft', 'title' => 'Saved By Hand', 'category_id' => $this->category->id]))
        ->assertSessionHasNoErrors();

    expect(Post::count())->toBe(1)
        ->and(Post::sole()->title)->toBe('Saved By Hand');
});
