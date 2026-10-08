<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'correct-horse-battery',
    ]);
});

test('the login page renders for guests', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Log in')
        ->assertDontSee('Log out');
});

test('a logged-in user visiting the login page is sent to the admin area', function () {
    $this->actingAs($this->admin)
        ->get(route('login'))
        ->assertRedirect(route('admin.dashboard'));
});

test('the admin can log in and the session is regenerated', function () {
    $this->startSession();
    $sessionIdBeforeLogin = session()->getId();

    $this->post(route('login.store'), [
        'email' => 'admin@example.com',
        'password' => 'correct-horse-battery',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($this->admin);
    expect(session()->getId())->not->toBe($sessionIdBeforeLogin);
});

test('wrong credentials are rejected', function () {
    $this->from(route('login'))
        ->post(route('login.store'), [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

test('email and password are required', function () {
    $this->post(route('login.store'), [])
        ->assertSessionHasErrors([
            'email' => 'The email field is required.',
            'password' => 'The password field is required.',
        ]);

    $this->assertGuest();
});

test('login is throttled after five failed attempts', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    $this->post(route('login.store'), [
        'email' => 'admin@example.com',
        'password' => 'correct-horse-battery',
    ])->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toStartWith('Too many login attempts.');
    $this->assertGuest();
});

test('a successful login clears earlier failed attempts', function () {
    foreach (range(1, 4) as $attempt) {
        $this->post(route('login.store'), [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);
    }

    $this->post(route('login.store'), [
        'email' => 'admin@example.com',
        'password' => 'correct-horse-battery',
    ]);
    $this->post(route('logout'));

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email' => __('auth.failed')]);
    }
});

test('logout ends the session', function () {
    $this->actingAs($this->admin)
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();

    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

test('logout only accepts POST requests', function () {
    $this->actingAs($this->admin)
        ->get('/logout')
        ->assertMethodNotAllowed();

    $this->assertAuthenticatedAs($this->admin);
});
