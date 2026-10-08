<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);

        if (app()->isLocal()) {
            $this->seedSampleContent();
        }
    }

    /**
     * Create sample categories, tags and posts for the local dev site.
     */
    private function seedSampleContent(): void
    {
        $admin = User::where('is_admin', true)->firstOrFail();
        $categories = Category::factory()->count(5)->create();
        $tags = Tag::factory()->count(10)->create();

        $posts = collect()
            ->merge(Post::factory()->count(12)->published()->for($admin)->recycle($categories)->create())
            ->merge(Post::factory()->count(3)->scheduled()->for($admin)->recycle($categories)->create())
            ->merge(Post::factory()->count(4)->draft()->for($admin)->recycle($categories)->create());

        $posts->each(fn (Post $post) => $post->tags()->attach(
            $tags->random(fake()->numberBetween(1, 3))->pluck('id')
        ));
    }
}
