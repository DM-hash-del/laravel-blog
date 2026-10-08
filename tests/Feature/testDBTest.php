<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('testing database connection', function () {
    expect(DB::connection()->getDatabaseName())->not->toBe('laravel_blog')->and(app()->environment())->toBe('testing');
});
