<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('in-place AI is the default, with no switch to turn it off', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.posts.create'))
        ->assertOk()
        ->assertDontSee('data-ai-inline', false)
        ->assertDontSee('AI suggestions without reloading');
});

test('the Generate button keeps its reload fallback and carries the in-place hooks', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.posts.create'))
        ->assertSee('formaction="'.route('admin.posts.assist').'" formnovalidate data-ai-assist data-assist-url="'.route('admin.posts.assist').'"', false)
        ->assertSee('data-ai-status role="status" aria-live="polite" hidden', false);
});

test('the overlay is hidden and sits outside the form so it stays usable while the form is locked', function () {
    $html = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.posts.create'))
        ->getContent();

    expect($html)->toContain('<div data-ai-overlay hidden role="dialog" aria-modal="true"')
        ->and(strpos($html, 'data-ai-overlay'))->toBeGreaterThan(strrpos($html, '</form>'))
        ->and($html)->toContain('data-ai-cancel');
});
