<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a guest is redirected from every post action and nothing changes', function () {
    $post = Post::factory()->create(['title' => 'Original Title']);
    $trashed = Post::factory()->create();
    $trashed->delete();

    $this->get(route('admin.posts.index'))->assertRedirect(route('login'));
    $this->get(route('admin.posts.trash'))->assertRedirect(route('login'));
    $this->post(route('admin.posts.store'), postPayload())->assertRedirect(route('login'));
    $this->put(route('admin.posts.update', $post), postPayload(['title' => 'Changed']))->assertRedirect(route('login'));
    $this->delete(route('admin.posts.destroy', $post))->assertRedirect(route('login'));
    $this->patch(route('admin.posts.restore', $trashed))->assertRedirect(route('login'));

    $this->assertDatabaseCount('posts', 2);
    $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => 'Original Title', 'deleted_at' => null]);
    $this->assertSoftDeleted($trashed);
});

test('a non-admin is forbidden from every post action and nothing changes', function () {
    $post = Post::factory()->create(['title' => 'Original Title']);
    $trashed = Post::factory()->create();
    $trashed->delete();
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.posts.index'))->assertForbidden();
    $this->get(route('admin.posts.trash'))->assertForbidden();
    $this->get(route('admin.posts.edit', $post))->assertForbidden();
    $this->post(route('admin.posts.store'), postPayload())->assertForbidden();
    $this->put(route('admin.posts.update', $post), postPayload(['title' => 'Changed']))->assertForbidden();
    $this->delete(route('admin.posts.destroy', $post))->assertForbidden();
    $this->patch(route('admin.posts.restore', $trashed))->assertForbidden();

    $this->assertDatabaseCount('posts', 2);
    $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => 'Original Title', 'deleted_at' => null]);
    $this->assertSoftDeleted($trashed);
});

test('an admin can open the post list and the editor for every status', function () {
    $admin = User::factory()->admin()->create();
    $posts = [
        Post::factory()->draft()->create(),
        Post::factory()->scheduled()->create(),
        Post::factory()->published()->create(),
    ];

    $this->actingAs($admin)->get(route('admin.posts.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.posts.create'))->assertOk()->assertSee('Save draft');

    foreach ($posts as $post) {
        $this->actingAs($admin)->get(route('admin.posts.edit', $post))->assertOk()->assertSee($post->title);
    }
});
