<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

dataset('layouts', [
    'public home' => [fn () => test()->get(route('home'))],
    'admin page' => [fn () => test()->actingAs(User::factory()->admin()->create())->get(route('admin.posts.index'))],
    'login page' => [fn () => test()->get(route('login'))],
]);

test('the stored theme is applied in the head, before the stylesheet loads', function (Closure $visit) {
    $html = $visit()->assertOk()->getContent();

    $script = strpos($html, "localStorage.getItem('theme')");
    $stylesheet = strpos($html, 'resources/css/app.css');

    expect($script)->not->toBeFalse()
        ->and($script)->toBeLessThan($stylesheet)
        ->and($script)->toBeLessThan(strpos($html, '</head>'));
})->with('layouts');

test('the theme toggle is rendered once, hidden until JavaScript runs, with System first', function (Closure $visit) {
    $html = $visit()->assertOk()->getContent();

    expect(substr_count($html, 'data-theme-toggle'))->toBe(1)
        ->and($html)->toMatch('/<fieldset data-theme-toggle hidden/');

    $visit()->assertSeeInOrder([
        'name="theme" value="system"', 'System',
        'name="theme" value="light"', 'Light',
        'name="theme" value="dark"', 'Dark',
    ], false);
})->with('layouts');
