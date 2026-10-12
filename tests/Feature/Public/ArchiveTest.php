<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a category archive shows only that category\'s published posts', function () {
    $laravel = Category::factory()->create(['name' => 'Laravel']);
    $other = Category::factory()->create();
    Post::factory()->published()->for($laravel)->create(['title' => 'In Laravel']);
    Post::factory()->published()->for($other)->create(['title' => 'Somewhere Else']);

    $this->get(route('categories.show', $laravel))
        ->assertOk()
        ->assertSee('In Laravel')
        ->assertDontSee('Somewhere Else');
});

test('a tag archive shows only published posts with that tag', function () {
    $tag = Tag::factory()->create(['name' => 'testing']);
    Post::factory()->published()->create(['title' => 'Tagged Post'])->tags()->attach($tag);
    Post::factory()->published()->create(['title' => 'Untagged Post']);

    $this->get(route('tags.show', $tag))
        ->assertOk()
        ->assertSee('Tagged Post')
        ->assertDontSee('Untagged Post');
});

test('an archive with no published posts shows an empty state', function () {
    $category = Category::factory()->create();
    Post::factory()->draft()->for($category)->create();

    $this->get(route('categories.show', $category))
        ->assertOk()
        ->assertSee('No posts yet.');
});

test('an unknown archive is not found', function () {
    $this->get(route('categories.show', 'no-such-category'))->assertNotFound();
    $this->get(route('tags.show', 'no-such-tag'))->assertNotFound();
});
