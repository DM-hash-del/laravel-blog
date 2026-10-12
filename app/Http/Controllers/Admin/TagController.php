<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TagController extends Controller
{
    /**
     * List the tags with how many posts use each, trashed posts included.
     */
    public function index(): View
    {
        $tags = Tag::query()
            ->withCount(['posts' => fn ($query) => $query->withTrashed()])
            ->orderBy('name')
            ->paginate(25);

        return view('admin.tags.index', ['tags' => $tags]);
    }

    /**
     * Create a tag.
     */
    public function store(TagRequest $request): RedirectResponse
    {
        $tag = Tag::create($request->attributesForCreate());

        return to_route('admin.tags.index')->with('status', "Tag \"{$tag->name}\" created.");
    }

    /**
     * Show the form for editing a tag.
     */
    public function edit(Tag $tag): View
    {
        return view('admin.tags.edit', ['tag' => $tag]);
    }

    /**
     * Rename a tag. The slug is left unchanged so its URLs stay stable.
     */
    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update($request->validated());

        return to_route('admin.tags.index')->with('status', "Tag \"{$tag->name}\" updated.");
    }

    /**
     * Delete a tag. Its links to posts go with it; the posts themselves stay.
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        return to_route('admin.tags.index')->with('status', "Tag \"{$tag->name}\" deleted.");
    }
}
