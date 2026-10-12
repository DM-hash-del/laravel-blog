<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostRequest;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PostController extends Controller
{
    /**
     * List the posts, newest first.
     */
    public function index(): View
    {
        $posts = Post::query()
            ->with('category')
            ->latest()
            ->paginate(25);

        return view('admin.posts.index', ['posts' => $posts]);
    }

    /**
     * Show the editor for a new post.
     */
    public function create(): View
    {
        return view('admin.posts.create', [
            'post' => new Post,
            ...$this->formOptions(),
        ]);
    }

    /**
     * Create a post with the status chosen by the button pressed.
     */
    public function store(PostRequest $request): RedirectResponse
    {
        $post = new Post;
        $post->user()->associate($request->user());

        $this->save($post, $request);

        return to_route('admin.posts.index')->with('status', $this->savedMessage($post));
    }

    /**
     * Show the editor for an existing post, whatever its status.
     */
    public function edit(Post $post): View
    {
        $post->load('tags');

        return view('admin.posts.edit', [
            'post' => $post,
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update a post with the status chosen by the button pressed.
     */
    public function update(PostRequest $request, Post $post): RedirectResponse
    {
        $this->save($post, $request);

        return to_route('admin.posts.index')->with('status', $this->savedMessage($post));
    }

    /**
     * Move a post to the trash. Its category, tags and tag links are kept for a restore.
     */
    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return to_route('admin.posts.index')->with('status', "Post \"{$post->title}\" moved to the trash.");
    }

    /**
     * List the trashed posts, most recently trashed first.
     */
    public function trash(): View
    {
        $posts = Post::onlyTrashed()
            ->with('category')
            ->latest('deleted_at')
            ->paginate(25);

        return view('admin.posts.trash', ['posts' => $posts]);
    }

    /**
     * Restore a trashed post. The route binding explicitly includes trashed posts.
     */
    public function restore(Post $post): RedirectResponse
    {
        $post->restore();

        return to_route('admin.posts.edit', $post)->with('status', "Post \"{$post->title}\" restored.");
    }

    /**
     * Fill, set the status of and save a post, then sync its tags, as one transaction.
     *
     * A category typed into the editor is created here, so a failed save leaves no stray category.
     */
    private function save(Post $post, PostRequest $request): void
    {
        DB::transaction(function () use ($post, $request): void {
            $post->fill($request->postAttributes());

            if ($request->newCategoryName() !== null) {
                $post->category()->associate(Category::createFromName($request->newCategoryName()));
            }

            match ($request->action()) {
                'publish' => $post->publish(),
                'schedule' => $post->scheduleFor($request->scheduledFor()),
                default => $post->saveAsDraft(),
            };

            $post->save();
            $post->tags()->sync($request->tagIds());
        });
    }

    /**
     * Describe the saved post's status for the flash message.
     */
    private function savedMessage(Post $post): string
    {
        return match ($post->status) {
            PostStatus::Published => 'Post published.',
            PostStatus::Scheduled => 'Post scheduled for '.$post->published_at->timezone(config('app.display_timezone'))->format('j M Y, H:i').'.',
            PostStatus::Draft => 'Draft saved.',
        };
    }

    /**
     * Get the categories and tags the editor offers.
     *
     * @return array{categories: Collection<int, Category>, tags: Collection<int, Tag>}
     */
    private function formOptions(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
        ];
    }
}
