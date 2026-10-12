<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    /**
     * Get the posts that carry the tag.
     *
     * The post_tag pivot has no timestamp columns.
     *
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    /**
     * Split a comma-separated list of tag names, trimming blanks and repeats (ignoring case).
     *
     * @return list<string>
     */
    public static function namesFromList(?string $list): array
    {
        return collect(explode(',', (string) $list))
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => Str::lower($name))
            ->values()
            ->all();
    }

    /**
     * Find the existing tag for a name, matching the name ignoring case, or the slug it would have.
     */
    public static function findByName(string $name): ?Tag
    {
        return static::whereRaw('lower(name) = ?', [Str::lower($name)])
            ->orWhere('slug', Str::slug($name))
            ->first();
    }

    /**
     * Get the tags for the given names, creating a tag only when no existing one matches.
     *
     * @param  list<string>  $names
     * @return Collection<int, Tag>
     */
    public static function findOrCreateByNames(array $names): Collection
    {
        return new Collection(array_map(
            fn (string $name): Tag => static::findByName($name) ?? static::create(['name' => $name, 'slug' => Str::slug($name)]),
            $names,
        ));
    }
}
