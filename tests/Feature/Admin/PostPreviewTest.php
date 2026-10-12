<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('guests and non-admins cannot use the preview', function () {
    $this->postJson(route('admin.posts.preview'), ['body' => '## Hi'])->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->postJson(route('admin.posts.preview'), ['body' => '## Hi'])
        ->assertForbidden();
});

test('the preview renders Markdown through the shared component', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.posts.preview'), ['body' => "## A Heading\n\nSome **bold** text."])
        ->assertOk()
        ->assertJsonPath('html', fn (string $html) => str_contains($html, '<h2>A Heading</h2>')
            && str_contains($html, '<strong>bold</strong>')
            && str_contains($html, 'prose'));
});

test('the preview strips raw HTML and blocks unsafe links', function () {
    $html = $this->actingAs($this->admin)
        ->postJson(route('admin.posts.preview'), ['body' => "<script>alert('x')</script>\n\n[bad](javascript:alert(1))"])
        ->assertOk()
        ->json('html');

    expect($html)->not->toContain("<script>alert('x')</script>")
        ->and($html)->not->toContain('href="javascript:');
});

test('an empty body shows the empty state', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.posts.preview'), ['body' => ''])
        ->assertOk()
        ->assertJsonPath('html', fn (string $html) => str_contains($html, 'Nothing to preview yet.'));
});

test('a body that is too long is rejected', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.posts.preview'), ['body' => str_repeat('a', 100001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');
});

test('the preview is throttled', function () {
    for ($request = 1; $request <= 120; $request++) {
        $this->actingAs($this->admin)->postJson(route('admin.posts.preview'), ['body' => 'Hi'])->assertOk();
    }

    $this->actingAs($this->admin)->postJson(route('admin.posts.preview'), ['body' => 'Hi'])->assertTooManyRequests();
});

test('the editor is wired for the live preview and still renders the saved body', function () {
    $post = Post::factory()->draft()->create(['body' => '## Saved Heading']);

    $this->actingAs($this->admin)
        ->get(route('admin.posts.edit', $post))
        ->assertOk()
        ->assertSee('<meta name="csrf-token" content="', false)
        ->assertSee('data-live-preview data-preview-url="'.route('admin.posts.preview').'"', false)
        ->assertSeeInOrder(['data-preview-content', '<h2>Saved Heading</h2>'], false);
});
