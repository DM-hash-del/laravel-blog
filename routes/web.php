<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\Admin\TagController as AdminTagController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CategoryArchiveController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\TagArchiveController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| Open to everyone. Only published content may be exposed here.
|
*/

Route::get('/', [PostController::class, 'index'])->name('home');
Route::get('posts/{slug}', [PostController::class, 'show'])->name('posts.show');
Route::get('categories/{category:slug}', CategoryArchiveController::class)->name('categories.show');
Route::get('tags/{tag:slug}', TagArchiveController::class)->name('tags.show');

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

    Route::get('posts/trash', [AdminPostController::class, 'trash'])->name('posts.trash');
    Route::patch('posts/{post}/restore', [AdminPostController::class, 'restore'])->withTrashed()->name('posts.restore');
    Route::resource('posts', AdminPostController::class)->except(['show']);

    Route::resource('categories', AdminCategoryController::class)->except(['create', 'show']);
    Route::resource('tags', AdminTagController::class)->except(['create', 'show']);
});
