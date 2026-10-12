<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the post page shows the title, category, tags and uses the excerpt as the meta description', function () {
    $category = Category::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);
    $tag = Tag::factory()->create(['name' => 'eloquent', 'slug' => 'eloquent']);
    $post = Post::factory()->published()->for($category)->create([
        'title' => 'Understanding Scopes',
        'excerpt' => 'A short guide to query scopes.',
    ]);
    $post->tags()->attach($tag);

    $this->get(route('posts.show', $post->slug))
        ->assertOk()
        ->assertSee('<title>Understanding Scopes · '.config('app.name').'</title>', false)
        ->assertSee('<meta name="description" content="A short guide to query scopes.">', false)
        ->assertSee(route('categories.show', $category), false)
        ->assertSee(route('tags.show', $tag), false);
});

test('the body is rendered from Markdown', function () {
    $post = Post::factory()->published()->create(['body' => "## A Heading\n\n> Quoted & **bold** text"]);

    $this->get(route('posts.show', $post->slug))
        ->assertOk()
        ->assertSee('<h2>A Heading</h2>', false)
        ->assertSee('<blockquote>', false)
        ->assertSee('<strong>bold</strong>', false);
});

test('raw HTML in the body is stripped', function () {
    $post = Post::factory()->published()->create(['body' => "Hello\n\n<script>alert('x')</script>\n\n<p onclick=\"steal()\">Hi</p>"]);

    $this->get(route('posts.show', $post->slug))
        ->assertOk()
        ->assertDontSee("<script>alert('x')</script>", false)
        ->assertDontSee('onclick', false);
});

test('unsafe links in the body are blocked', function () {
    $post = Post::factory()->published()->create(['body' => '[click me](javascript:alert(1)) and [safe](https://example.com)']);

    $this->get(route('posts.show', $post->slug))
        ->assertOk()
        ->assertDontSee('href="javascript:', false)
        ->assertSee('href="https://example.com"', false);
});
