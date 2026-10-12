<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('the admin list searches every status, not just published posts', function () {
    Post::factory()->draft()->create(['title' => 'Draft About Queues']);
    Post::factory()->scheduled()->create(['title' => 'Scheduled About Queues']);
    Post::factory()->published()->create(['title' => 'Published About Queues']);
    Post::factory()->published()->create(['title' => 'Something Else']);

    $this->actingAs($this->admin)
        ->get(route('admin.posts.index', ['search' => 'queues']))
        ->assertOk()
        ->assertSee('Draft About Queues')
        ->assertSee('Scheduled About Queues')
        ->assertSee('Published About Queues')
        ->assertDontSee('Something Else');
});

test('the admin list sorts by created date', function () {
    $this->travelTo('2026-01-01 09:00:00');
    Post::factory()->draft()->create(['title' => 'Created First']);
    $this->travelTo('2026-02-01 09:00:00');
    Post::factory()->draft()->create(['title' => 'Created Second']);

    $this->actingAs($this->admin)
        ->get(route('admin.posts.index'))
        ->assertSeeInOrder(['Created Second', 'Created First']);

    $this->actingAs($this->admin)
        ->get(route('admin.posts.index', ['sort' => 'oldest']))
        ->assertSeeInOrder(['Created First', 'Created Second']);
});

test('the admin list filters by status', function (string $status, string $expectedTitle) {
    Post::factory()->draft()->create(['title' => 'The Draft']);
    Post::factory()->scheduled()->create(['title' => 'The Scheduled']);
    Post::factory()->published()->create(['title' => 'The Published']);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.posts.index', ['status' => $status]))
        ->assertOk()
        ->assertSee($expectedTitle);

    collect(['The Draft', 'The Scheduled', 'The Published'])
        ->reject(fn (string $title) => $title === $expectedTitle)
        ->each(fn (string $title) => $response->assertDontSee($title));
})->with([
    'draft' => ['draft', 'The Draft'],
    'scheduled' => ['scheduled', 'The Scheduled'],
    'published' => ['published', 'The Published'],
]);

test('the status filter combines with search and sort', function () {
    $this->travelTo('2026-01-01 09:00:00');
    Post::factory()->draft()->create(['title' => 'Older Draft About Queues']);
    $this->travelTo('2026-02-01 09:00:00');
    Post::factory()->draft()->create(['title' => 'Newer Draft About Queues']);
    Post::factory()->draft()->create(['title' => 'Draft About Something Else']);
    Post::factory()->published()->create(['title' => 'Published About Queues']);

    $this->actingAs($this->admin)
        ->get(route('admin.posts.index', ['status' => 'draft', 'search' => 'queues', 'sort' => 'oldest']))
        ->assertOk()
        ->assertSeeInOrder(['Older Draft About Queues', 'Newer Draft About Queues'])
        ->assertDontSee('Draft About Something Else')
        ->assertDontSee('Published About Queues');
});

test('pagination keeps the status filter', function () {
    Post::factory()->draft()->count(26)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.posts.index', ['status' => 'draft']))
        ->assertOk()
        ->assertSee('status=draft', false)
        ->assertSee('page=2', false);
});

test('an unknown status redirects to the clean admin list with an error', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.posts.index', ['status' => 'archived']))
        ->assertRedirect(route('admin.posts.index'))
        ->assertSessionHasErrors('status');
});
