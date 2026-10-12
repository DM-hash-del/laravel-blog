<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::factory()->create();
    $this->tag = Tag::factory()->create();

    $this->published = Post::factory()->published()->for($this->category)->create(['title' => 'Visible Published Post']);
    $this->draft = Post::factory()->draft()->for($this->category)->create(['title' => 'Hidden Draft Post']);
    $this->scheduled = Post::factory()->scheduled()->for($this->category)->create(['title' => 'Hidden Scheduled Post']);
    $this->trashed = Post::factory()->published()->for($this->category)->create(['title' => 'Hidden Trashed Post']);

    foreach ([$this->published, $this->draft, $this->scheduled, $this->trashed] as $post) {
        $post->tags()->attach($this->tag);
    }

    $this->trashed->delete();
});

test('lists show only published posts', function (string $routeName) {
    $url = match ($routeName) {
        'home' => route('home'),
        'categories.show' => route('categories.show', $this->category),
        'tags.show' => route('tags.show', $this->tag),
    };

    $this->get($url)
        ->assertOk()
        ->assertSee('Visible Published Post')
        ->assertDontSee('Hidden Draft Post')
        ->assertDontSee('Hidden Scheduled Post')
        ->assertDontSee('Hidden Trashed Post');
})->with(['home', 'categories.show', 'tags.show']);

test('a published post can be read', function () {
    $this->get(route('posts.show', $this->published->slug))
        ->assertOk()
        ->assertSee('Visible Published Post');
});

test('draft, scheduled and trashed posts are not found', function (string $property) {
    $this->get(route('posts.show', $this->{$property}->slug))->assertNotFound();
})->with(['draft', 'scheduled', 'trashed']);

test('public lists ignore a status parameter', function () {
    $this->get(route('home', ['status' => 'draft']))
        ->assertOk()
        ->assertSee('Visible Published Post')
        ->assertDontSee('Hidden Draft Post');
});
