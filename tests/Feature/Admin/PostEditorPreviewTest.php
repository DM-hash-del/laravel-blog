<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('the editor previews the saved body as rendered Markdown below the raw text', function () {
    $post = Post::factory()->draft()->create(['body' => "## Preview Heading\n\nSome **bold** text."]);

    $this->actingAs($this->admin)
        ->get(route('admin.posts.edit', $post))
        ->assertOk()
        ->assertSeeInOrder(['<textarea id="body"', '## Preview Heading', '</textarea>', 'id="body-preview"', '<h2>Preview Heading</h2>', '<strong>bold</strong>'], false);
});

test('the preview strips raw HTML and blocks unsafe links', function () {
    $post = Post::factory()->draft()->create(['body' => "<script>alert('x')</script>\n\n[bad](javascript:alert(1))"]);

    $this->actingAs($this->admin)
        ->get(route('admin.posts.edit', $post))
        ->assertOk()
        ->assertDontSee("<script>alert('x')</script>", false)
        ->assertDontSee('href="javascript:', false);
});

test('after a failed save the preview shows the body that was typed', function () {
    $post = Post::factory()->draft()->create(['body' => 'Saved body']);

    $this->actingAs($this->admin)
        ->from(route('admin.posts.edit', $post))
        ->followingRedirects()
        ->put(route('admin.posts.update', $post), postPayload(['action' => 'publish', 'title' => $post->title, 'excerpt' => '', 'body' => '## Typed Heading']))
        ->assertOk()
        ->assertSee('<h2>Typed Heading</h2>', false);
});

test('a new post shows an empty preview', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.posts.create'))
        ->assertOk()
        ->assertSee('Nothing to preview yet.');
});

test('the preview toggle is ticked by default and never submitted', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.posts.create'))
        ->assertSee('<input id="show-preview" type="checkbox" checked aria-controls="body-preview"', false)
        ->assertDontSee('name="show-preview"', false);
});
