<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * List the categories with how many posts use each, trashed posts included.
     */
    public function index(): View
    {
        $categories = Category::query()
            ->withCount(['posts' => fn ($query) => $query->withTrashed()])
            ->orderBy('name')
            ->paginate(25);

        return view('admin.categories.index', ['categories' => $categories]);
    }

    /**
     * Create a category.
     */
    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->attributesForCreate());

        return to_route('admin.categories.index')->with('status', "Category \"{$category->name}\" created.");
    }

    /**
     * Show the form for editing a category.
     */
    public function edit(Category $category): View
    {
        return view('admin.categories.edit', ['category' => $category]);
    }

    /**
     * Rename a category. The slug is left unchanged so its URLs stay stable.
     */
    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return to_route('admin.categories.index')->with('status', "Category \"{$category->name}\" updated.");
    }

    /**
     * Delete a category, refusing while any post uses it.
     */
    public function destroy(Category $category): RedirectResponse
    {
        if ($category->isInUse()) {
            return back()->with('error', "Category \"{$category->name}\" is used by posts, including any in the trash. Move those posts to another category first.");
        }

        $category->delete();

        return to_route('admin.categories.index')->with('status', "Category \"{$category->name}\" deleted.");
    }
}
