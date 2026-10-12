<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostListRequest;
use App\Models\Category;
use Illuminate\View\View;

class CategoryArchiveController extends Controller
{
    /**
     * List the published posts in a category.
     */
    public function __invoke(PostListRequest $request, Category $category): View
    {
        $posts = $category->posts()
            ->published()
            ->with('category')
            ->listed($request->filters(), 'published_at')
            ->paginate(PostController::PER_PAGE)
            ->withQueryString();

        return view('categories.show', ['category' => $category, 'posts' => $posts, 'filters' => $request->filters()]);
    }
}
