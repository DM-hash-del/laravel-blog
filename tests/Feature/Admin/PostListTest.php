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
