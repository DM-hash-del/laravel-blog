<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('a new post gets its slug from the title', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload(['title' => 'My First Post']))
        ->assertSessionHasNoErrors();

    expect(Post::sole()->slug)->toBe('my-first-post');
});

test('a draft slug follows title edits', function () {
    $post = Post::factory()->draft()->create(['title' => 'Old Title']);

    $this->actingAs($this->admin)
        ->put(route('admin.posts.update', $post), postPayload(['title' => 'New Title']))
        ->assertSessionHasNoErrors();

    expect($post->fresh()->slug)->toBe('new-title');
});

test('a submitted slug is ignored before the first publish', function () {
    $post = Post::factory()->draft()->create(['title' => 'Old Title']);

    $this->actingAs($this->admin)
        ->put(route('admin.posts.update', $post), postPayload(['title' => 'New Title', 'slug' => 'something-else']))
        ->assertSessionHasNoErrors();

    expect($post->fresh()->slug)->toBe('new-title');
});

test('publishing takes the slug from the new title, then freezes it', function () {
    $post = Post::factory()->draft()->create(['title' => 'Draft Title']);

    $this->actingAs($this->admin)
        ->put(route('admin.posts.update', $post), postPayload(['action' => 'publish', 'title' => 'Launch Title']))
        ->assertSessionHasNoErrors();

    expect($post->fresh()->slug)->toBe('launch-title');

    $this->actingAs($this->admin)
        ->put(route('admin.posts.update', $post), postPayload(['action' => 'publish', 'title' => 'Renamed After Launch', 'slug' => 'launch-title']))
        ->assertSessionHasNoErrors();

    expect($post->fresh())
        ->title->toBe('Renamed After Launch')
        ->slug->toBe('launch-title');
});

test('the slug stays frozen after a post is unpublished', function () {
    $post = Post::factory()->published()->create(['title' => 'Live Post', 'slug' => 'live-post']);

    $this->actingAs($this->admin)
        ->put(route('admin.posts.update', $post), postPayload(['action' => 'draft', 'title' => 'Live Post', 'slug' => 'live-post']))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->put(route('admin.posts.update', $post), postPayload(['action' => 'draft', 'title' => 'Back In Draft', 'slug' => 'live-post']))
        ->assertSessionHasNoErrors();

    expect($post->fresh())
        ->title->toBe('Back In Draft')
        ->slug->toBe('live-post');
});

test('an admin can deliberately change a frozen slug', function () {
    $post = Post::factory()->published()->create(['title' => 'Live Post', 'slug' => 'live-post']);

    $this->actingAs($this->admin)
        ->put(route('admin.posts.update', $post), postPayload(['action' => 'publish', 'title' => 'Live Post', 'slug' => 'a-better-url']))
        ->assertSessionHasNoErrors();

    expect($post->fresh()->slug)->toBe('a-better-url');
});

test('a frozen slug must be present, well formed and unique, trashed posts included', function (string $slug, string $message) {
    $trashed = Post::factory()->create(['title' => 'Taken']);
    $trashed->delete();
    $post = Post::factory()->published()->create(['title' => 'Live Post', 'slug' => 'live-post']);

    $this->actingAs($this->admin)
        ->put(route('admin.posts.update', $post), postPayload(['action' => 'publish', 'title' => 'Live Post', 'slug' => $slug]))
        ->assertSessionHasErrors(['slug' => $message]);

    expect($post->fresh()->slug)->toBe('live-post');
})->with([
    'empty' => ['', 'The slug field is required.'],
    'badly formed' => ['Not A Slug!', 'The slug may contain only lowercase letters, numbers and single hyphens.'],
    'held by a trashed post' => ['taken', 'Another post, possibly in the trash, already uses this slug.'],
]);

test('a title whose slug is held by a trashed post is rejected on the title', function () {
    Post::factory()->create(['title' => 'Old News'])->delete();

    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload(['title' => 'Old news']))
        ->assertSessionHasErrors(['title' => "Another post, possibly in the trash, already uses this title's URL."])
        ->assertSessionDoesntHaveErrors('slug');

    $this->assertDatabaseCount('posts', 1);
});

test('a draft can keep its own title without clashing with itself', function () {
    $post = Post::factory()->draft()->create(['title' => 'Same Title']);

    $this->actingAs($this->admin)
        ->put(route('admin.posts.update', $post), postPayload(['title' => 'Same Title']))
        ->assertSessionHasNoErrors();
});

test('a title without letters or numbers is rejected with one message', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.posts.store'), postPayload(['title' => '!!!']))
        ->assertSessionHasErrors(['title' => 'The title must contain letters or numbers.']);

    expect(session('errors')->get('title'))->toHaveCount(1);
    $this->assertDatabaseCount('posts', 0);
});

test('the editor shows a slug field only once the post has been published', function () {
    $draft = Post::factory()->draft()->create();
    $published = Post::factory()->published()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.posts.edit', $draft))
        ->assertDontSee('name="slug"', false);

    $this->actingAs($this->admin)
        ->get(route('admin.posts.edit', $published))
        ->assertSee('name="slug"', false);
});
