<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('a guest is redirected to the login page', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('a logged-in non-admin is forbidden', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('an admin can open the admin area', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.posts.index'))
        ->assertOk()
        ->assertSee('Log out');
});

test('opening the admin root takes the admin to the post list', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.posts.index'));
});

test('the admin gate allows only admins', function () {
    expect(Gate::forUser(User::factory()->admin()->create())->allows('access-admin'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows('access-admin'))->toBeFalse();
});

test('every admin route sits behind auth and the admin gate', function () {
    $adminRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => str_starts_with((string) $route->getName(), 'admin.'));

    expect($adminRoutes)->not->toBeEmpty();

    $adminRoutes->each(function (RoutingRoute $route): void {
        expect($route->gatherMiddleware())
            ->toContain('auth')
            ->toContain('can:access-admin');
    });
});

test('there is no registration or password reset route', function () {
    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('password.request'))->toBeFalse()
        ->and(Route::has('password.reset'))->toBeFalse();

    $this->get('/register')->assertNotFound();
});
