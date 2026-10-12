<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('a guest is redirected from every category action and nothing changes', function () {
    $category = Category::factory()->create();

    $this->get(route('admin.categories.index'))->assertRedirect(route('login'));
    $this->post(route('admin.categories.store'), ['name' => 'News'])->assertRedirect(route('login'));
    $this->delete(route('admin.categories.destroy', $category))->assertRedirect(route('login'));

    $this->assertDatabaseCount('categories', 1);
    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

test('a non-admin is forbidden from every category action and nothing changes', function () {
    $category = Category::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.categories.index'))->assertForbidden();
    $this->post(route('admin.categories.store'), ['name' => 'News'])->assertForbidden();
    $this->delete(route('admin.categories.destroy', $category))->assertForbidden();

    $this->assertDatabaseCount('categories', 1);
    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

test('the index lists categories with post counts that include trashed posts', function () {
    $category = Category::factory()->create(['name' => 'Laravel']);
    Post::factory()->count(2)->for($category)->create();
    Post::factory()->for($category)->create()->delete();
    Category::factory()->create(['name' => 'Unused']);

    $this->actingAs($this->admin)
        ->get(route('admin.categories.index'))
        ->assertOk()
        ->assertSeeInOrder(['Laravel', '3 posts', 'Unused', 'No posts']);
});

test('an admin can create a category with a slug generated from its name', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), ['name' => 'Web Development'])
        ->assertRedirect(route('admin.categories.index'))
        ->assertSessionHas('status');

    $this->assertDatabaseHas('categories', ['name' => 'Web Development', 'slug' => 'web-development']);
});

test('an empty name gets a single error on name and creates nothing', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), ['name' => ''])
        ->assertSessionHasErrors('name')
        ->assertSessionDoesntHaveErrors('slug');

    expect(session('errors')->get('name'))->toHaveCount(1);
    $this->assertDatabaseCount('categories', 0);
});

test('a name that produces no slug is rejected on name', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), ['name' => '!!!'])
        ->assertSessionHasErrors(['name' => 'The name must contain letters or numbers.'])
        ->assertSessionDoesntHaveErrors('slug');

    $this->assertDatabaseCount('categories', 0);
});

test('a duplicate name differing only in case is rejected on name', function () {
    Category::factory()->create(['name' => 'PHP', 'slug' => 'php']);

    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), ['name' => 'php'])
        ->assertSessionHasErrors(['name' => 'A category with this name already exists.'])
        ->assertSessionDoesntHaveErrors('slug');

    $this->assertDatabaseCount('categories', 1);
});

test('the duplicate name message is shown to the admin on the page', function () {
    Category::factory()->create(['name' => 'PHP', 'slug' => 'php']);

    $this->actingAs($this->admin)
        ->from(route('admin.categories.index'))
        ->followingRedirects()
        ->post(route('admin.categories.store'), ['name' => 'php'])
        ->assertOk()
        ->assertSee('A category with this name already exists.');
});

test('an admin can rename a category and its slug stays the same', function () {
    $category = Category::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

    $this->actingAs($this->admin)
        ->put(route('admin.categories.update', $category), ['name' => 'New Name'])
        ->assertRedirect(route('admin.categories.index'));

    expect($category->fresh())
        ->name->toBe('New Name')
        ->slug->toBe('old-name');
});

test('renaming a category to a case variant of another category name is rejected on name', function () {
    Category::factory()->create(['name' => 'PHP', 'slug' => 'php']);
    $category = Category::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);

    $this->actingAs($this->admin)
        ->put(route('admin.categories.update', $category), ['name' => 'php'])
        ->assertSessionHasErrors(['name' => 'A category with this name already exists.']);

    expect($category->fresh())
        ->name->toBe('Laravel')
        ->slug->toBe('laravel');
});

test('changing only the case of a category name passes', function () {
    $category = Category::factory()->create(['name' => 'php', 'slug' => 'php']);

    $this->actingAs($this->admin)
        ->put(route('admin.categories.update', $category), ['name' => 'PHP'])
        ->assertSessionHasNoErrors();

    expect($category->fresh()->name)->toBe('PHP');
});

test('creating a category whose slug is still held by a renamed category is rejected on name', function () {
    Category::factory()->create(['name' => 'Backend', 'slug' => 'php']);

    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), ['name' => 'PHP'])
        ->assertSessionHasErrors(['name' => "This name's URL is already used by another category. Choose a different name."])
        ->assertSessionDoesntHaveErrors('slug');

    $this->assertDatabaseCount('categories', 1);
});

test('renaming a category to a name matching another category frozen slug passes and keeps its own slug', function () {
    Category::factory()->create(['name' => 'Backend', 'slug' => 'php']);
    $category = Category::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);

    $this->actingAs($this->admin)
        ->put(route('admin.categories.update', $category), ['name' => 'PHP'])
        ->assertSessionHasNoErrors();

    expect($category->fresh())
        ->name->toBe('PHP')
        ->slug->toBe('laravel');
});

test('saving a category with its unchanged name passes', function () {
    $category = Category::factory()->create(['name' => 'PHP', 'slug' => 'php']);

    $this->actingAs($this->admin)
        ->put(route('admin.categories.update', $category), ['name' => 'PHP'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.categories.index'));
});

test('renaming a category to another category name is rejected on name', function () {
    Category::factory()->create(['name' => 'PHP', 'slug' => 'php']);
    $category = Category::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);

    $this->actingAs($this->admin)
        ->put(route('admin.categories.update', $category), ['name' => 'PHP'])
        ->assertSessionHasErrors(['name' => 'A category with this name already exists.']);

    expect($category->fresh()->slug)->toBe('laravel');
});

test('an unused category can be deleted', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect(route('admin.categories.index'))
        ->assertSessionHas('status');

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('a category used by a post cannot be deleted', function () {
    $category = Category::factory()->create();
    $post = Post::factory()->for($category)->create();

    $this->actingAs($this->admin)
        ->from(route('admin.categories.index'))
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect(route('admin.categories.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
    $this->assertDatabaseHas('posts', ['id' => $post->id, 'category_id' => $category->id]);
});

test('a category whose only post is trashed cannot be deleted', function () {
    $category = Category::factory()->create();
    Post::factory()->for($category)->create()->delete();

    $this->actingAs($this->admin)
        ->delete(route('admin.categories.destroy', $category))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});
