<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostListRequest;
use App\Models\Post;
use Illuminate\View\View;

class PostController extends Controller
{
    /**
     * The number of posts per page on the public lists.
     */
    public const int PER_PAGE = 10;

    /**
     * List the published posts on the home page.
     */
    public function index(PostListRequest $request): View
    {
        $posts = Post::published()
            ->with('category')
            ->listed($request->filters(), 'published_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('posts.index', ['posts' => $posts, 'filters' => $request->filters()]);
    }

    /**
     * Show a published post. Drafts, scheduled and trashed posts are not found.
     */
    public function show(string $slug): View
    {
        $post = Post::published()
            ->where('slug', $slug)
            ->with('category', 'tags')
            ->firstOrFail();

        return view('posts.show', ['post' => $post]);
    }
}
