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
    $this->admin = User::factory()->admin()->create();
});

test('a draft saves with only a title and category', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload([
            'category_id' => $category->id,
            'excerpt' => null,
            'body' => null,
        ]))
        ->assertSessionHasNoErrors();

    $post = Post::sole();
    expect($post)
        ->title->toBe('A Hand Written Post')
        ->category_id->toBe($category->id)
        ->user_id->toBe($this->admin->id)
        ->status->toBe(PostStatus::Draft)
        ->excerpt->toBeNull()
        ->body->toBeNull()
        ->published_at->toBeNull();
});

test('a draft without a title or category is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload(['title' => '', 'category_id' => null]))
        ->assertSessionHasErrors(['title', 'category_id']);

    $this->assertDatabaseCount('posts', 0);
});

test('a nonexistent category is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload(['category_id' => 999]))
        ->assertSessionHasErrors('category_id');

    $this->assertDatabaseCount('posts', 0);
});

test('publish and schedule need an excerpt and a body', function (string $action) {
    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload([
            'action' => $action,
            'excerpt' => '',
            'body' => '',
            'published_at' => now()->addDay()->timezone(config('app.display_timezone'))->format('Y-m-d\TH:i'),
        ]))
        ->assertSessionHasErrors(['excerpt', 'body']);

    $this->assertDatabaseCount('posts', 0);
})->with(['publish', 'schedule']);

test('saving syncs the chosen tags', function () {
    $tags = Tag::factory()->count(3)->create();
    $post = Post::factory()->create();
    $post->tags()->attach($tags[0]);

    $this->actingAs($this->admin)
        ->put(route('admin.posts.update', $post), postPayload([
            'title' => $post->title,
            'tags' => [$tags[1]->id, $tags[2]->id],
        ]))
        ->assertSessionHasNoErrors();

    expect($post->tags()->pluck('tags.id')->sort()->values()->all())
        ->toBe([$tags[1]->id, $tags[2]->id]);
});

test('a nonexistent tag is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload(['tags' => [999]]))
        ->assertSessionHasErrors('tags.0');

    $this->assertDatabaseCount('posts', 0);
});

test('publishing sets the publish dates to now', function () {
    $this->travelTo(Carbon::parse('2026-10-12 09:30:00', 'UTC'));

    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload(['action' => 'publish']))
        ->assertSessionHasNoErrors();

    $post = Post::sole();
    expect($post->status)->toBe(PostStatus::Published)
        ->and($post->published_at->toDateTimeString())->toBe('2026-10-12 09:30:00')
        ->and($post->first_published_at->toDateTimeString())->toBe('2026-10-12 09:30:00');
});

test('updating a published post keeps its original publish date', function () {
    $post = Post::factory()->published()->create(['published_at' => '2026-01-05 10:00:00', 'first_published_at' => '2026-01-05 10:00:00']);

    $this->actingAs($this->admin)
        ->put(route('admin.posts.update', $post), postPayload([
            'action' => 'publish',
            'title' => 'Edited Title',
            'slug' => $post->slug,
        ]))
        ->assertSessionHasNoErrors();

    expect($post->fresh())
        ->title->toBe('Edited Title')
        ->status->toBe(PostStatus::Published)
        ->and($post->fresh()->published_at->toDateTimeString())->toBe('2026-01-05 10:00:00');
});

test('a schedule entered in UK time is stored in UTC', function (string $entered, string $storedUtc) {
    $this->travelTo(Carbon::parse('2026-01-01 00:00:00', 'UTC'));

    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload(['action' => 'schedule', 'published_at' => $entered]))
        ->assertSessionHasNoErrors();

    $post = Post::sole();
    expect($post->status)->toBe(PostStatus::Scheduled)
        ->and($post->published_at->toDateTimeString())->toBe($storedUtc)
        ->and($post->first_published_at)->toBeNull();
})->with([
    'summer time (BST)' => ['2026-07-01T14:00', '2026-07-01 13:00:00'],
    'winter time (GMT)' => ['2026-12-01T14:00', '2026-12-01 14:00:00'],
]);

test('a schedule in the past is rejected', function () {
    $this->travelTo(Carbon::parse('2026-07-01 13:00:00', 'UTC'));

    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload(['action' => 'schedule', 'published_at' => '2026-07-01T13:59']))
        ->assertSessionHasErrors(['published_at' => 'The scheduled time must be in the future.']);

    $this->assertDatabaseCount('posts', 0);
});

test('a schedule needs a date and time', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload(['action' => 'schedule', 'published_at' => null]))
        ->assertSessionHasErrors('published_at');

    $this->assertDatabaseCount('posts', 0);
});

test('unscheduling returns a scheduled post to draft', function () {
    $post = Post::factory()->scheduled()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.posts.update', $post), postPayload(['action' => 'draft', 'title' => $post->title]))
        ->assertSessionHasNoErrors();

    expect($post->fresh())
        ->status->toBe(PostStatus::Draft)
        ->published_at->toBeNull();
});

test('unpublishing returns a published post to draft but remembers the first publish', function () {
    $post = Post::factory()->published()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.posts.update', $post), postPayload(['action' => 'draft', 'title' => $post->title, 'slug' => $post->slug]))
        ->assertSessionHasNoErrors();

    expect($post->fresh())
        ->status->toBe(PostStatus::Draft)
        ->published_at->toBeNull()
        ->first_published_at->not->toBeNull();
});

test('an unknown action is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload(['action' => 'delete']))
        ->assertSessionHasErrors('action');

    $this->assertDatabaseCount('posts', 0);
});

test('creating a post returns the admin to the post list', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload())
        ->assertRedirect(route('admin.posts.index'))
        ->assertSessionHas('status', 'Draft saved.');
});

test('every editor button on an existing post returns the admin to the post list', function (string $action, string $message) {
    $this->travelTo(Carbon::parse('2026-07-01 12:00:00', 'UTC'));
    $post = Post::factory()->draft()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.posts.update', $post), postPayload([
            'action' => $action,
            'title' => $post->title,
            'published_at' => '2026-07-02T09:00',
        ]))
        ->assertRedirect(route('admin.posts.index'))
        ->assertSessionHas('status', $message);
})->with([
    'save draft' => ['draft', 'Draft saved.'],
    'publish' => ['publish', 'Post published.'],
    'schedule' => ['schedule', 'Post scheduled for 2 Jul 2026, 09:00.'],
]);

test('a failed update stays on the editor with the input kept', function () {
    $post = Post::factory()->draft()->create();

    $this->actingAs($this->admin)
        ->from(route('admin.posts.edit', $post))
        ->put(route('admin.posts.update', $post), postPayload(['action' => 'publish', 'title' => 'Typed But Not Saved', 'body' => '']))
        ->assertRedirect(route('admin.posts.edit', $post))
        ->assertSessionHasErrors('body')
        ->assertSessionHasInput('title', 'Typed But Not Saved');

    expect($post->fresh()->title)->not->toBe('Typed But Not Saved');
});

test('typing a new category creates it and assigns it to the post', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload(['category_id' => null, 'new_category' => 'Web Security']))
        ->assertSessionHasNoErrors();

    $category = Category::sole();
    expect($category)
        ->name->toBe('Web Security')
        ->slug->toBe('web-security')
        ->and(Post::sole()->category_id)->toBe($category->id);
});

test('a category must be chosen or typed', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload(['category_id' => null, 'new_category' => '']))
        ->assertSessionHasErrors(['category_id' => 'Choose a category or type a new one.']);

    $this->assertDatabaseCount('posts', 0);
    $this->assertDatabaseCount('categories', 0);
});

test('choosing a category and typing a new one is rejected', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload(['category_id' => $category->id, 'new_category' => 'Another']))
        ->assertSessionHasErrors(['new_category' => 'Choose an existing category or type a new one, not both.']);

    $this->assertDatabaseCount('posts', 0);
    $this->assertDatabaseCount('categories', 1);
});

test('a new category follows the same name rules as the panel', function (string $name, string $message) {
    Category::factory()->create(['name' => 'PHP', 'slug' => 'php']);
    Category::factory()->create(['name' => 'Backend', 'slug' => 'laravel']);

    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload(['category_id' => null, 'new_category' => $name]))
        ->assertSessionHasErrors(['new_category' => $message]);

    $this->assertDatabaseCount('posts', 0);
    $this->assertDatabaseCount('categories', 2);
})->with([
    'existing name in another case' => ['php', 'A category with this name already exists.'],
    'no letters or numbers' => ['!!!', 'The name must contain letters or numbers.'],
    'URL held by a renamed category' => ['Laravel', "This name's URL is already used by another category. Choose a different name."],
]);

test('no category is created when the rest of the post fails validation', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload(['action' => 'publish', 'body' => '', 'category_id' => null, 'new_category' => 'Fresh']))
        ->assertSessionHasErrors('body');

    $this->assertDatabaseCount('posts', 0);
    $this->assertDatabaseCount('categories', 0);
});
