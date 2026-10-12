<?php

use App\Http\Controllers\PostController;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('search matches post titles, ignoring case', function () {
    Post::factory()->published()->create(['title' => 'Testing With Pest']);
    Post::factory()->published()->create(['title' => 'Deploying To cPanel']);

    $this->get(route('home', ['search' => 'pest']))
        ->assertOk()
        ->assertSee('Testing With Pest')
        ->assertDontSee('Deploying To cPanel');
});

test('search matches category names', function () {
    $security = Category::factory()->create(['name' => 'Security']);
    Post::factory()->published()->for($security)->create(['title' => 'Hashing Passwords']);
    Post::factory()->published()->create(['title' => 'Unrelated Post']);

    $this->get(route('home', ['search' => 'secur']))
        ->assertOk()
        ->assertSee('Hashing Passwords')
        ->assertDontSee('Unrelated Post');
});

test('search treats LIKE wildcards as plain text', function () {
    Post::factory()->published()->create(['title' => '100% Coverage']);
    Post::factory()->published()->create(['title' => 'Partial Coverage']);

    $this->get(route('home', ['search' => '100%']))
        ->assertSee('100% Coverage')
        ->assertDontSee('Partial Coverage');

    $this->get(route('home', ['search' => '%']))
        ->assertSee('100% Coverage')
        ->assertDontSee('Partial Coverage');
});

test('posts are newest first by default and oldest first on request', function () {
    Post::factory()->published()->create(['title' => 'Older Post', 'published_at' => '2026-01-01 09:00:00']);
    Post::factory()->published()->create(['title' => 'Newer Post', 'published_at' => '2026-06-01 09:00:00']);

    $this->get(route('home'))->assertSeeInOrder(['Newer Post', 'Older Post']);
    $this->get(route('home', ['sort' => 'oldest']))->assertSeeInOrder(['Older Post', 'Newer Post']);
});

test('pagination keeps the search and sort', function () {
    Post::factory()->published()->count(PostController::PER_PAGE + 1)->sequence(fn ($sequence) => ['title' => "Matching Post {$sequence->index}"])->create();

    $this->get(route('home', ['search' => 'Matching', 'sort' => 'oldest']))
        ->assertOk()
        ->assertSee('search=Matching', false)
        ->assertSee('sort=oldest', false)
        ->assertSee('page=2', false);
});

test('invalid list parameters redirect to the clean list with an error', function (array $query, string $field) {
    $this->get(route('home', $query))
        ->assertRedirect(route('home'))
        ->assertSessionHasErrors($field);
})->with([
    'unknown sort' => [['sort' => 'random'], 'sort'],
    'over-long search' => [['search' => str_repeat('a', 101)], 'search'],
]);

test('search and sort work on an archive', function () {
    $category = Category::factory()->create();
    Post::factory()->published()->for($category)->create(['title' => 'Alpha Post', 'published_at' => '2026-01-01 09:00:00']);
    Post::factory()->published()->for($category)->create(['title' => 'Beta Post', 'published_at' => '2026-06-01 09:00:00']);
    Post::factory()->published()->for($category)->create(['title' => 'Gamma Post', 'published_at' => '2026-03-01 09:00:00']);

    $this->get(route('categories.show', [$category, 'search' => 'a Post', 'sort' => 'oldest']))
        ->assertOk()
        ->assertSeeInOrder(['Alpha Post', 'Gamma Post', 'Beta Post']);

    $this->get(route('categories.show', [$category, 'search' => 'beta']))
        ->assertSee('Beta Post')
        ->assertDontSee('Alpha Post');
});
