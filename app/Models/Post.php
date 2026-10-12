<?php

namespace App\Models;

use App\Enums\PostStatus;
use Carbon\CarbonInterface;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
     * Limit the query to posts visitors may see: published and not trashed.
     *
     * Trashed posts are already excluded by soft deletes, and scheduled posts
     * stay hidden until the scheduler publishes them.
     *
     * @param  Builder<Post>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), PostStatus::Published);
    }

    /**
     * Apply the list search and sort shared by the home page, the archives and the admin list.
     *
     * An optional status narrows the list; only the admin list passes one.
     * Search matches the title or the category name, with LIKE wildcards in the
     * term escaped (using "!", which MariaDB and SQLite both accept) so they match
     * literally. Sort is by the given date column, newest first unless "oldest" is asked for.
     *
     * @param  Builder<Post>  $query
     * @param  array{search: ?string, sort: string, status?: ?PostStatus}  $filters
     */
    #[Scope]
    protected function listed(Builder $query, array $filters, string $dateColumn): void
    {
        if (($filters['status'] ?? null) !== null) {
            $query->where($query->qualifyColumn('status'), $filters['status']);
        }

        if (filled($filters['search'])) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']).'%';

            $titleColumn = $query->qualifyColumn('title');

            $query->where(function (Builder $query) use ($pattern, $titleColumn): void {
                $query->whereRaw("{$titleColumn} like ? escape '!'", [$pattern])
                    ->orWhereHas('category', fn (Builder $category) => $category->whereRaw("name like ? escape '!'", [$pattern]));
            });
        }

        $direction = $filters['sort'] === 'oldest' ? 'asc' : 'desc';

        $query->orderBy($query->qualifyColumn($dateColumn), $direction)
            ->orderBy($query->qualifyColumn('id'), $direction);
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
     * Publish the post, keeping its date when that date has already arrived.
     *
     * A published post keeps its original date, and a scheduled post that is due
     * (or overdue because the scheduler ran late) keeps its scheduled time. A draft,
     * or a scheduled post published early, takes the current time.
     */
    public function publish(): void
    {
        if ($this->published_at === null || $this->published_at->isFuture()) {
            $this->published_at = Carbon::now();
        }

        $this->status = PostStatus::Published;
        $this->first_published_at ??= $this->published_at;
    }

    /**
     * Determine whether the post is scheduled for a time that has already passed,
     * which means the scheduler has not run.
     */
    public function isOverdue(): bool
    {
        return $this->status === PostStatus::Scheduled
            && $this->published_at !== null
            && $this->published_at->isPast();
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
