<x-layouts.admin title="Posts">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Posts</h1>

        <div class="flex gap-2">
            <a href="{{ route('admin.posts.trash') }}" class="flex min-h-11 items-center rounded-md border border-gray-300 px-4 text-sm font-medium text-gray-800 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                Trash
            </a>
            <a href="{{ route('admin.posts.create') }}" class="flex min-h-11 items-center rounded-md bg-accent-dark px-4 text-sm font-medium text-white hover:bg-accent-dark/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 dark:bg-accent dark:text-gray-950 dark:hover:bg-accent/90 dark:focus-visible:ring-offset-gray-950">
                New post
            </a>
        </div>
    </div>

    @if ($posts->isEmpty())
        <p class="mt-8 text-gray-600 dark:text-gray-400">No posts yet.</p>
    @else
        <ul class="mt-8 divide-y divide-gray-200 rounded-md border border-gray-200 bg-white dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-900">
            @foreach ($posts as $post)
                <li class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="font-medium break-words">{{ $post->title }}</p>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-600 dark:text-gray-400">
                            <x-status-badge :status="$post->status" />
                            <span>{{ $post->category->name }}</span>
                            @if ($post->published_at)
                                <span>
                                    · {{ $post->status === \App\Enums\PostStatus::Scheduled ? 'Scheduled for' : 'Published' }}
                                    <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->timezone(config('app.display_timezone'))->format('j M Y, H:i') }}</time>
                                </span>
                            @endif
                        </p>
                    </div>

                    <a href="{{ route('admin.posts.edit', $post) }}" class="flex min-h-11 shrink-0 items-center self-start rounded-md border border-gray-300 px-4 text-sm font-medium text-accent-dark hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent sm:self-auto dark:border-gray-700 dark:text-accent dark:hover:bg-gray-800">
                        Edit
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">
            {{ $posts->links() }}
        </div>
    @endif
</x-layouts.admin>
