<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostAutosaveRequest;
use App\Http\Requests\Admin\PostListRequest;
use App\Http\Requests\Admin\PostRequest;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Services\AiAssist\AiRateLimit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PostController extends Controller
{
    /**
     * List every live post, whatever its status, with the shared search and sort.
     */
    public function index(PostListRequest $request): View
    {
        $posts = Post::query()
            ->with('category')
            ->listed($request->filters(), 'created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.posts.index', ['posts' => $posts, 'filters' => $request->filters()]);
    }

    /**
     * Show the editor for a new post.
     */
    public function create(AiRateLimit $aiRateLimit): View
    {
        return view('admin.posts.create', [
            'post' => new Post,
            ...$this->formOptions($aiRateLimit),
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
     * Autosave a new post as a draft, without leaving the New post page.
     *
     * The reply tells the editor where to send later saves, so the same draft is updated.
     */
    public function autosaveStore(PostAutosaveRequest $request): JsonResponse
    {
        $post = new Post;
        $post->user()->associate($request->user());

        $this->save($post, $request);

        return $this->autosaved($post);
    }

    /**
     * Autosave an existing draft. Scheduled and published posts are refused by the request.
     */
    public function autosaveUpdate(PostAutosaveRequest $request, Post $post): JsonResponse
    {
        $this->save($post, $request);

        return $this->autosaved($post);
    }

    /**
     * Show the editor for an existing post, whatever its status.
     */
    public function edit(Post $post, AiRateLimit $aiRateLimit): View
    {
        $post->load('tags');

        return view('admin.posts.edit', [
            'post' => $post,
            ...$this->formOptions($aiRateLimit),
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
     * A category or tags typed into the editor are created here, so a failed save leaves nothing behind.
     * Typed tags reuse an existing tag when the name matches, ignoring case.
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
            $post->tags()->sync([
                ...$request->tagIds(),
                ...Tag::findOrCreateByNames($request->newTagNames())->modelKeys(),
            ]);
        });
    }

    /**
     * Describe an autosave for the editor: when it happened and where later saves go.
     */
    private function autosaved(Post $post): JsonResponse
    {
        return response()->json([
            'saved_at' => $post->updated_at->timezone(config('app.display_timezone'))->format('H:i'),
            'form_action' => route('admin.posts.update', $post),
            'autosave_url' => route('admin.posts.autosave.update', $post),
            'assist_url' => route('admin.posts.assist', $post),
        ]);
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
     * Get the categories and tags the editor offers, and any AI rate-limit wait still running.
     *
     * @return array{categories: Collection<int, Category>, tags: Collection<int, Tag>, aiRetryAfter: int}
     */
    private function formOptions(AiRateLimit $aiRateLimit): array
    {
        return [
            'categories' => Category::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
            'aiRetryAfter' => $aiRateLimit->secondsRemaining(auth()->user()),
        ];
    }
}
