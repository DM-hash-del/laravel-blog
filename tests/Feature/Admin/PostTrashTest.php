<?php

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('deleting a post trashes it and keeps its category, tags and tag links', function () {
    $post = Post::factory()->published()->create();
    $tag = Tag::factory()->create();
    $post->tags()->attach($tag);

    $this->actingAs($this->admin)
        ->delete(route('admin.posts.destroy', $post))
        ->assertRedirect(route('admin.posts.index'))
        ->assertSessionHas('status');

    $this->assertSoftDeleted($post);
    $this->assertDatabaseHas('categories', ['id' => $post->category_id]);
    $this->assertDatabaseHas('tags', ['id' => $tag->id]);
    $this->assertDatabaseHas('post_tag', ['post_id' => $post->id, 'tag_id' => $tag->id]);
});

test('the trash lists only trashed posts and the post list only live ones', function () {
    $live = Post::factory()->create(['title' => 'Still Here']);
    $trashed = Post::factory()->create(['title' => 'Thrown Away']);
    $trashed->delete();

    $this->actingAs($this->admin)
        ->get(route('admin.posts.trash'))
        ->assertOk()
        ->assertSee('Thrown Away')
        ->assertDontSee('Still Here');

    $this->actingAs($this->admin)
        ->get(route('admin.posts.index'))
        ->assertOk()
        ->assertSee('Still Here')
        ->assertDontSee('Thrown Away');
});

test('restoring a trashed post brings it back with its tags', function () {
    $post = Post::factory()->create();
    $tag = Tag::factory()->create();
    $post->tags()->attach($tag);
    $post->delete();

    $this->actingAs($this->admin)
        ->patch(route('admin.posts.restore', $post))
        ->assertRedirect(route('admin.posts.edit', $post));

    $this->assertNotSoftDeleted($post);
    expect($post->fresh()->tags->pluck('id')->all())->toBe([$tag->id]);
});

test('a trashed post cannot be edited until restored', function () {
    $post = Post::factory()->create();
    $post->delete();

    $this->actingAs($this->admin)
        ->get(route('admin.posts.edit', $post))
        ->assertNotFound();
});
