<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostListRequest;
use App\Models\Tag;
use Illuminate\View\View;

class TagArchiveController extends Controller
{
    /**
     * List the published posts carrying a tag.
     */
    public function __invoke(PostListRequest $request, Tag $tag): View
    {
        $posts = $tag->posts()
            ->published()
            ->with('category')
            ->listed($request->filters(), 'published_at')
            ->paginate(PostController::PER_PAGE)
            ->withQueryString();

        return view('tags.show', ['tag' => $tag, 'posts' => $posts, 'filters' => $request->filters()]);
    }
}
