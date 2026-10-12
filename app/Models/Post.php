<?php

namespace App\Models;

use App\Enums\PostStatus;
use Carbon\CarbonInterface;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable(['category_id', 'title', 'slug', 'excerpt', 'body', 'status', 'published_at'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PostStatus::Draft->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'first_published_at' => 'datetime',
        ];
    }

    /**
     * Keep the slug following the title until the post is first published.
     */
    protected static function booted(): void
    {
        static::saving(function (Post $post): void {
            if (! $post->isSlugFrozen()) {
                $post->slug = Str::slug($post->title);
            }
        });
    }

    /**
     * Determine whether the slug is frozen because the post has been published before.
     *
     * Reads the stored value, so a post being published in this save still
     * takes its slug from the new title once.
     */
    public function isSlugFrozen(): bool
    {
        return $this->getOriginal('first_published_at') !== null;
    }

    /**
     * Move the post to draft, taking it off the site. Used to unschedule and unpublish.
     */
    public function saveAsDraft(): void
    {
        $this->status = PostStatus::Draft;
        $this->published_at = null;
    }

    /**
     * Publish the post now, keeping the original date if it is already published.
     */
    public function publish(): void
    {
        if ($this->status !== PostStatus::Published || $this->published_at === null) {
            $this->published_at = Carbon::now();
        }

        $this->status = PostStatus::Published;
        $this->first_published_at ??= $this->published_at;
    }

    /**
     * Schedule the post to be published at the given moment.
     */
    public function scheduleFor(CarbonInterface $publishAt): void
    {
        $this->status = PostStatus::Scheduled;
        $this->published_at = $publishAt->copy()->utc();
    }

    /**
     * Get the author of the post.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the category of the post.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the tags on the post.
     *
     * The post_tag pivot has no timestamp columns. Soft deleting a post keeps
     * its pivot rows so a restored post comes back with its tags; the rows are
     * removed by the database only when the post is permanently deleted.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Get the AI generation requests logged against the post.
     *
     * @return HasMany<AiGeneration, $this>
     */
    public function aiGenerations(): HasMany
    {
        return $this->hasMany(AiGeneration::class);
    }
}
