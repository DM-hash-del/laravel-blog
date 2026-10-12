<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| Open to everyone. Only published content may be exposed here.
|
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Auth routes
|--------------------------------------------------------------------------
|
| Login and logout only. There is no registration and no password reset.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
|
| Every admin route goes inside this group, behind the auth middleware and
| the admin Gate. Never add an admin route outside it.
|
*/

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:access-admin'])->group(function () {
    Route::view('/', 'admin.dashboard')->name('dashboard');

    Route::get('posts/trash', [PostController::class, 'trash'])->name('posts.trash');
    Route::patch('posts/{post}/restore', [PostController::class, 'restore'])->withTrashed()->name('posts.restore');
    Route::resource('posts', PostController::class)->except(['show']);

    Route::resource('categories', CategoryController::class)->except(['create', 'show']);
    Route::resource('tags', TagController::class)->except(['create', 'show']);
});
