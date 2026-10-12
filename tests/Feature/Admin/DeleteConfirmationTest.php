<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('deleting a category asks for confirmation', function () {
    Category::factory()->create(['name' => 'Laravel']);

    $this->actingAs($this->admin)
        ->get(route('admin.categories.index'))
        ->assertOk()
        ->assertSee('data-confirm="Delete the category “Laravel”? This can\'t be undone." data-confirm-label="Delete category"', false);
});

test('deleting a tag asks for confirmation and says how many posts lose it', function () {
    $tag = Tag::factory()->create(['name' => 'php']);
    $tag->posts()->attach(Post::factory()->count(2)->create());

    $this->actingAs($this->admin)
        ->get(route('admin.tags.index'))
        ->assertOk()
        ->assertSee('data-confirm="Delete the tag “php”? It will be removed from 2 posts. This can\'t be undone." data-confirm-label="Delete tag"', false);
});

test('moving a post to the trash asks for confirmation', function () {
    $post = Post::factory()->create(['title' => 'Old Notes']);

    $this->actingAs($this->admin)
        ->get(route('admin.posts.edit', $post))
        ->assertOk()
        ->assertSee('data-confirm="Move “Old Notes” to the trash? It will be hidden from the site, and you can restore it from the Trash page." data-confirm-label="Move to trash"', false);
});

test('names in confirmation messages are escaped', function () {
    Category::factory()->create(['name' => 'Tips "&" <Tricks>', 'slug' => 'tips-tricks']);

    $this->actingAs($this->admin)
        ->get(route('admin.categories.index'))
        ->assertSee('Delete the category “Tips &quot;&amp;&quot; &lt;Tricks&gt;”?', false)
        ->assertDontSee('data-confirm="Delete the category “Tips "&"', false);
});

test('the admin layout has one confirm dialog and public pages have none', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.posts.index'))->assertOk();

    expect(substr_count($response->getContent(), 'id="confirm-dialog"'))->toBe(1);

    $this->get(route('home'))->assertOk()->assertDontSee('id="confirm-dialog"', false);
});
