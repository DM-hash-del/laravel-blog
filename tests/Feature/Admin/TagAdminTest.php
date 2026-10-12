<?php

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('a guest is redirected from every tag action and nothing changes', function () {
    $tag = Tag::factory()->create();

    $this->get(route('admin.tags.index'))->assertRedirect(route('login'));
    $this->post(route('admin.tags.store'), ['name' => 'php'])->assertRedirect(route('login'));
    $this->delete(route('admin.tags.destroy', $tag))->assertRedirect(route('login'));

    $this->assertDatabaseCount('tags', 1);
    $this->assertDatabaseHas('tags', ['id' => $tag->id]);
});

test('a non-admin is forbidden from every tag action and nothing changes', function () {
    $tag = Tag::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.tags.index'))->assertForbidden();
    $this->post(route('admin.tags.store'), ['name' => 'php'])->assertForbidden();
    $this->delete(route('admin.tags.destroy', $tag))->assertForbidden();

    $this->assertDatabaseCount('tags', 1);
    $this->assertDatabaseHas('tags', ['id' => $tag->id]);
});

test('the index lists tags with post counts that include trashed posts', function () {
    $tag = Tag::factory()->create(['name' => 'eloquent']);
    $tag->posts()->attach(Post::factory()->count(2)->create());
    $trashedPost = Post::factory()->create();
    $tag->posts()->attach($trashedPost);
    $trashedPost->delete();
    Tag::factory()->create(['name' => 'unused']);

    $this->actingAs($this->admin)
        ->get(route('admin.tags.index'))
        ->assertOk()
        ->assertSeeInOrder(['eloquent', '3 posts', 'unused', 'No posts']);
});

test('an admin can create a tag with a slug generated from its name', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.tags.store'), ['name' => 'Blade Components'])
        ->assertRedirect(route('admin.tags.index'))
        ->assertSessionHas('status');

    $this->assertDatabaseHas('tags', ['name' => 'Blade Components', 'slug' => 'blade-components']);
});

test('an empty tag name gets a single error on name and creates nothing', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.tags.store'), ['name' => ''])
        ->assertSessionHasErrors('name')
        ->assertSessionDoesntHaveErrors('slug');

    expect(session('errors')->get('name'))->toHaveCount(1);
    $this->assertDatabaseCount('tags', 0);
});

test('a tag name that produces no slug is rejected on name', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.tags.store'), ['name' => '???'])
        ->assertSessionHasErrors(['name' => 'The name must contain letters or numbers.'])
        ->assertSessionDoesntHaveErrors('slug');

    $this->assertDatabaseCount('tags', 0);
});

test('a duplicate tag name differing only in case is rejected on name', function () {
    Tag::factory()->create(['name' => 'PHP', 'slug' => 'php']);

    $this->actingAs($this->admin)
        ->post(route('admin.tags.store'), ['name' => 'php'])
        ->assertSessionHasErrors(['name' => 'A tag with this name already exists.'])
        ->assertSessionDoesntHaveErrors('slug');

    $this->assertDatabaseCount('tags', 1);
});

test('the duplicate tag name message is shown to the admin on the page', function () {
    Tag::factory()->create(['name' => 'PHP', 'slug' => 'php']);

    $this->actingAs($this->admin)
        ->from(route('admin.tags.index'))
        ->followingRedirects()
        ->post(route('admin.tags.store'), ['name' => 'php'])
        ->assertOk()
        ->assertSee('A tag with this name already exists.');
});

test('an admin can rename a tag and its slug stays the same', function () {
    $tag = Tag::factory()->create(['name' => 'old name', 'slug' => 'old-name']);

    $this->actingAs($this->admin)
        ->put(route('admin.tags.update', $tag), ['name' => 'new name'])
        ->assertRedirect(route('admin.tags.index'));

    expect($tag->fresh())
        ->name->toBe('new name')
        ->slug->toBe('old-name');
});

test('renaming a tag to a case variant of another tag name is rejected on name', function () {
    Tag::factory()->create(['name' => 'PHP', 'slug' => 'php']);
    $tag = Tag::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);

    $this->actingAs($this->admin)
        ->put(route('admin.tags.update', $tag), ['name' => 'php'])
        ->assertSessionHasErrors(['name' => 'A tag with this name already exists.']);

    expect($tag->fresh())
        ->name->toBe('Laravel')
        ->slug->toBe('laravel');
});

test('changing only the case of a tag name passes', function () {
    $tag = Tag::factory()->create(['name' => 'php', 'slug' => 'php']);

    $this->actingAs($this->admin)
        ->put(route('admin.tags.update', $tag), ['name' => 'PHP'])
        ->assertSessionHasNoErrors();

    expect($tag->fresh()->name)->toBe('PHP');
});

test('creating a tag whose slug is still held by a renamed tag is rejected on name', function () {
    Tag::factory()->create(['name' => 'Backend', 'slug' => 'php']);

    $this->actingAs($this->admin)
        ->post(route('admin.tags.store'), ['name' => 'PHP'])
        ->assertSessionHasErrors(['name' => "This name's URL is already used by another tag. Choose a different name."])
        ->assertSessionDoesntHaveErrors('slug');

    $this->assertDatabaseCount('tags', 1);
});

test('renaming a tag to a name matching another tag frozen slug passes and keeps its own slug', function () {
    Tag::factory()->create(['name' => 'Backend', 'slug' => 'php']);
    $tag = Tag::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);

    $this->actingAs($this->admin)
        ->put(route('admin.tags.update', $tag), ['name' => 'PHP'])
        ->assertSessionHasNoErrors();

    expect($tag->fresh())
        ->name->toBe('PHP')
        ->slug->toBe('laravel');
});

test('saving a tag with its unchanged name passes', function () {
    $tag = Tag::factory()->create(['name' => 'PHP', 'slug' => 'php']);

    $this->actingAs($this->admin)
        ->put(route('admin.tags.update', $tag), ['name' => 'PHP'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.tags.index'));
});

test('renaming a tag to another tag name is rejected on name', function () {
    Tag::factory()->create(['name' => 'PHP', 'slug' => 'php']);
    $tag = Tag::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);

    $this->actingAs($this->admin)
        ->put(route('admin.tags.update', $tag), ['name' => 'PHP'])
        ->assertSessionHasErrors(['name' => 'A tag with this name already exists.']);

    expect($tag->fresh()->slug)->toBe('laravel');
});

test('a tag in use can be deleted and its posts remain', function () {
    $tag = Tag::factory()->create();
    $post = Post::factory()->create();
    $post->tags()->attach($tag);

    $this->actingAs($this->admin)
        ->delete(route('admin.tags.destroy', $tag))
        ->assertRedirect(route('admin.tags.index'))
        ->assertSessionHas('status');

    $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    $this->assertDatabaseMissing('post_tag', ['tag_id' => $tag->id]);
    $this->assertDatabaseHas('posts', ['id' => $post->id, 'deleted_at' => null]);
});
