<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-07-01 08:00:00', 'UTC'));
    $this->due = Post::factory()->scheduled()->create(['title' => 'Due At Nine', 'published_at' => '2026-07-01 09:00:00']);
});

test('a scheduled post is not published before its time', function () {
    $this->travelTo(Carbon::parse('2026-07-01 08:59:00', 'UTC'));

    $this->artisan('posts:publish-scheduled')
        ->expectsOutput('Published 0 scheduled posts.')
        ->assertSuccessful();

    expect($this->due->fresh()->status)->toBe(PostStatus::Scheduled);
});

test('a scheduled post is published at its time, keeping the scheduled publish date', function () {
    $this->travelTo(Carbon::parse('2026-07-01 09:00:00', 'UTC'));

    $this->artisan('posts:publish-scheduled')
        ->expectsOutput('Published 1 scheduled post.')
        ->assertSuccessful();

    $post = $this->due->fresh();
    expect($post->status)->toBe(PostStatus::Published)
        ->and($post->published_at->toDateTimeString())->toBe('2026-07-01 09:00:00')
        ->and($post->first_published_at->toDateTimeString())->toBe('2026-07-01 09:00:00');
});

test('a post picked up late, because cron stalled, still shows its scheduled time', function () {
    $this->travelTo(Carbon::parse('2026-07-01 11:30:00', 'UTC'));

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    expect($this->due->fresh()->published_at->toDateTimeString())->toBe('2026-07-01 09:00:00');
});

test('drafts, published posts, future and trashed scheduled posts are left alone', function () {
    $draft = Post::factory()->draft()->create();
    $published = Post::factory()->published()->create(['published_at' => '2026-06-01 10:00:00', 'first_published_at' => '2026-06-01 10:00:00']);
    $future = Post::factory()->scheduled()->create(['published_at' => '2026-07-02 09:00:00']);
    $trashed = Post::factory()->scheduled()->create(['published_at' => '2026-07-01 08:30:00']);
    $trashed->delete();
    $this->travelTo(Carbon::parse('2026-07-01 10:00:00', 'UTC'));

    $this->artisan('posts:publish-scheduled')
        ->expectsOutput('Published 1 scheduled post.')
        ->assertSuccessful();

    expect($draft->fresh()->status)->toBe(PostStatus::Draft)
        ->and($published->fresh()->published_at->toDateTimeString())->toBe('2026-06-01 10:00:00')
        ->and($future->fresh()->status)->toBe(PostStatus::Scheduled)
        ->and(Post::withTrashed()->find($trashed->id)->status)->toBe(PostStatus::Scheduled);
});

test('once published by the scheduler, the post is public and its slug is frozen', function () {
    $this->travelTo(Carbon::parse('2026-07-01 09:01:00', 'UTC'));
    $this->artisan('posts:publish-scheduled');

    $this->get(route('home'))->assertSee('Due At Nine');
    $this->get(route('posts.show', 'due-at-nine'))->assertOk();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.posts.update', $this->due), postPayload(['action' => 'publish', 'title' => 'Renamed Later', 'slug' => 'due-at-nine']))
        ->assertSessionHasNoErrors();

    expect($this->due->fresh()->slug)->toBe('due-at-nine');
});

test('the task is scheduled every minute without overlapping, with a short-lived lock', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'posts:publish-scheduled'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(5);
});
