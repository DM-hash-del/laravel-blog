<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['auth.admin' => [
        'name' => 'Blog Admin',
        'email' => 'admin@example.com',
        'password' => 'correct-horse-battery',
    ]]);
});

test('it creates the admin from config with a hashed password', function () {
    $this->seed(AdminUserSeeder::class);

    $admin = User::where('email', 'admin@example.com')->sole();

    expect($admin->is_admin)->toBeTrue()
        ->and($admin->name)->toBe('Blog Admin')
        ->and($admin->password)->not->toBe('correct-horse-battery')
        ->and(Hash::check('correct-horse-battery', $admin->password))->toBeTrue();
});

test('running it twice does not create a second admin', function () {
    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    $this->assertDatabaseCount('users', 1);
});

test('it refuses to run without an admin email or password', function (string $missingKey) {
    config(["auth.admin.{$missingKey}" => null]);

    expect(fn () => $this->seed(AdminUserSeeder::class))->toThrow(RuntimeException::class);
    $this->assertDatabaseCount('users', 0);
})->with(['email', 'password']);
