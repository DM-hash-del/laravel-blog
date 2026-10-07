<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('testing database connection', function () {
    expect(DB::connection()->getDatabaseName())->toBe('laravel_blog_testing')->and(app()->environment())->toBe('testing');
});