<?php

use App\Enums\AiGenerationStatus;
use App\Enums\PostStatus;
use App\Models\AiGeneration;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('a post belongs to its author and category and both sides see it', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    $post = Post::factory()->for($user)->for($category)->create();

    expect($post->user->is($user))->toBeTrue()
        ->and($post->category->is($category))->toBeTrue()
        ->and($user->posts->pluck('id')->all())->toBe([$post->id])
        ->and($category->posts->pluck('id')->all())->toBe([$post->id]);
});

test('tags attach to a post without pivot timestamps and both sides see them', function () {
    $post = Post::factory()->create();
    $tags = Tag::factory()->count(2)->create();

    $post->tags()->attach($tags->pluck('id'));

    expect($post->tags->pluck('id')->sort()->values()->all())->toBe($tags->pluck('id')->sort()->values()->all())
        ->and($tags->first()->posts->pluck('id')->all())->toBe([$post->id]);
    $this->assertDatabaseCount('post_tag', 2);
});

test('an ai generation belongs to its user and post and both sides see it', function () {
    $post = Post::factory()->create();
    $generation = AiGeneration::factory()->for($post->user)->for($post)->create();

    expect($generation->user->is($post->user))->toBeTrue()
        ->and($generation->post->is($post))->toBeTrue()
        ->and($post->aiGenerations->pluck('id')->all())->toBe([$generation->id])
        ->and($post->user->aiGenerations->pluck('id')->all())->toBe([$generation->id]);
});

test('post status and published_at are cast', function () {
    $post = Post::factory()->published()->create()->fresh();

    expect($post->status)->toBe(PostStatus::Published)
        ->and($post->published_at)->toBeInstanceOf(Carbon::class);
});

test('a new post defaults to draft', function () {
    $post = new Post;

    expect($post->status)->toBe(PostStatus::Draft);
});

test('ai generation json columns are cast to arrays and status to the enum', function () {
    $generation = AiGeneration::factory()->create()->fresh();

    expect($generation->field_states)->toBeArray()
        ->and($generation->request_payload)->toBeArray()
        ->and($generation->response_payload)->toBeArray()
        ->and($generation->status)->toBe(AiGenerationStatus::Completed);
});

test('post factory states set a matching status and publish date', function () {
    $draft = Post::factory()->draft()->create();
    $scheduled = Post::factory()->scheduled()->create();
    $published = Post::factory()->published()->create();

    expect($draft->status)->toBe(PostStatus::Draft)
        ->and($draft->published_at)->toBeNull()
        ->and($scheduled->status)->toBe(PostStatus::Scheduled)
        ->and($scheduled->published_at->isFuture())->toBeTrue()
        ->and($published->status)->toBe(PostStatus::Published)
        ->and($published->published_at->isPast())->toBeTrue();
});

test('ai generation factory states set a matching status', function () {
    expect(AiGeneration::factory()->pending()->create()->status)->toBe(AiGenerationStatus::Pending)
        ->and(AiGeneration::factory()->failed()->create())
        ->status->toBe(AiGenerationStatus::Failed)
        ->error_message->not->toBeNull()
        ->response_payload->toBeNull();
});

test('soft deleting a post keeps its tags so a restore brings them back', function () {
    $post = Post::factory()->create();
    $tags = Tag::factory()->count(2)->create();
    $post->tags()->attach($tags->pluck('id'));

    $post->delete();

    expect(Post::find($post->id))->toBeNull()
        ->and(Post::withTrashed()->find($post->id))->not->toBeNull();
    $this->assertSoftDeleted($post);
    $this->assertDatabaseCount('post_tag', 2);

    $post->restore();

    expect($post->fresh()->tags)->toHaveCount(2);
});

test('a trashed post keeps its slug reserved', function () {
    $post = Post::factory()->create(['slug' => 'reserved-slug']);
    $post->delete();

    expect(fn () => Post::factory()->create(['slug' => 'reserved-slug']))
        ->toThrow(QueryException::class);
});

test('permanently deleting a post removes its pivot rows but keeps its tags, category and ai log', function () {
    $post = Post::factory()->create();
    $tags = Tag::factory()->count(2)->create();
    $post->tags()->attach($tags->pluck('id'));
    $generation = AiGeneration::factory()->for($post)->create();

    $post->forceDelete();

    $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    $this->assertDatabaseCount('post_tag', 0);
    $this->assertDatabaseCount('tags', 2);
    $this->assertDatabaseHas('categories', ['id' => $post->category_id]);
    $this->assertDatabaseHas('ai_generations', ['id' => $generation->id, 'post_id' => null]);
});

test('a category used by a live post cannot be deleted', function () {
    $post = Post::factory()->create();

    expect(fn () => $post->category->delete())->toThrow(QueryException::class);
    $this->assertDatabaseHas('categories', ['id' => $post->category_id]);
});

test('a category used only by a trashed post cannot be deleted', function () {
    $post = Post::factory()->create();
    $post->delete();

    expect(fn () => $post->category->delete())->toThrow(QueryException::class);
    $this->assertDatabaseHas('categories', ['id' => $post->category_id]);
});

test('is_admin cannot be mass assigned', function () {
    $user = User::create([
        'name' => 'Visitor',
        'email' => 'visitor@example.com',
        'password' => 'secret-password',
        'is_admin' => true,
    ]);

    expect($user->fresh()->is_admin)->toBeFalse();
});
