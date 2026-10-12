<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * Get the posts in the category.
     *
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Determine whether any post uses the category, trashed posts included.
     */
    public function isInUse(): bool
    {
        return $this->posts()->withTrashed()->exists();
    }

    /**
     * Check a proposed category name, returning the first problem or null when it is usable.
     *
     * Pass the category being renamed as $ignoring. Slugs never change after creation,
     * so the slug clash check applies only to new categories.
     */
    public static function nameError(string $name, ?Category $ignoring = null): ?string
    {
        $slug = Str::slug($name);

        if ($slug === '') {
            return 'The name must contain letters or numbers.';
        }

        $nameIsTaken = static::whereRaw('lower(name) = ?', [Str::lower($name)])
            ->when($ignoring, fn ($query, Category $category) => $query->whereKeyNot($category->getKey()))
            ->exists();

        if ($nameIsTaken) {
            return 'A category with this name already exists.';
        }

        if ($ignoring === null && static::where('slug', $slug)->exists()) {
            return "This name's URL is already used by another category. Choose a different name.";
        }

        return null;
    }

    /**
     * Create a category from its name, generating the slug that it keeps for good.
     */
    public static function createFromName(string $name): Category
    {
        return static::create(['name' => $name, 'slug' => Str::slug($name)]);
    }
}
