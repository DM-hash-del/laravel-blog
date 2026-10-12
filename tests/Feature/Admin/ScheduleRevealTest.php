<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('a new post has a Schedule trigger that still submits without JavaScript, and a hidden Confirm row', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.posts.create'))
        ->assertOk()
        ->assertSee('name="action" value="schedule" data-schedule-trigger', false)
        ->assertSee('data-schedule-panel', false)
        ->assertDontSee('data-schedule-open', false)
        ->assertSee('<div data-schedule-actions hidden', false)
        ->assertSeeInOrder(['data-schedule-actions hidden', 'name="action" value="schedule"', 'Confirm schedule', 'data-schedule-cancel'], false);
});

test('a scheduled post offers Update schedule, with the time prefilled in UK time', function () {
    $post = Post::factory()->scheduled()->create(['published_at' => Carbon::parse('2026-07-01 13:00:00', 'UTC')]);

    $this->actingAs($this->admin)
        ->get(route('admin.posts.edit', $post))
        ->assertOk()
        ->assertSee('name="action" value="schedule" data-schedule-trigger class=', false)
        ->assertSee('value="2026-07-01T14:00"', false)
        ->assertSeeInOrder(['data-schedule-actions hidden', 'Update schedule', 'data-schedule-cancel'], false);
});

test('a published post has no schedule trigger', function () {
    $post = Post::factory()->published()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.posts.edit', $post))
        ->assertOk()
        ->assertDontSee('data-schedule-trigger', false);
});

test('after a failed schedule the panel opens with the error', function () {
    $this->travelTo(Carbon::parse('2026-07-01 12:00:00', 'UTC'));
    $post = Post::factory()->draft()->create();
    $editor = route('admin.posts.edit', $post);

    $this->actingAs($this->admin)
        ->from($editor)
        ->followingRedirects()
        ->put(route('admin.posts.update', $post), postPayload(['action' => 'schedule', 'title' => $post->title, 'published_at' => '2026-06-30T09:00']))
        ->assertOk()
        ->assertSee('data-schedule-open', false)
        ->assertSee('The scheduled time must be in the future.');
});
