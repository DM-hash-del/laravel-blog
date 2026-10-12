<x-layouts.admin title="Trash">
    <a href="{{ route('admin.posts.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-accent-dark hover:underline dark:text-accent">
        ← Back to posts
    </a>

    <h1 class="mt-2 text-2xl font-semibold">Trash</h1>
    <p class="mt-2 text-gray-600 dark:text-gray-400">Trashed posts are hidden from the site and keep their URL reserved. Restore one to edit it again.</p>

    @if ($posts->isEmpty())
        <p class="mt-8 text-gray-600 dark:text-gray-400">The trash is empty.</p>
    @else
        <ul class="mt-8 divide-y divide-gray-200 rounded-md border border-gray-200 bg-white dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-900">
            @foreach ($posts as $post)
                <li class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="font-medium break-words">{{ $post->title }}</p>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-600 dark:text-gray-400">
                            <x-status-badge :status="$post->status" />
                            <span>{{ $post->category->name }}</span>
                            <span>· Trashed <time datetime="{{ $post->deleted_at->toIso8601String() }}">{{ $post->deleted_at->timezone(config('app.display_timezone'))->format('j M Y, H:i') }}</time></span>
                        </p>
                    </div>

                    <form method="POST" action="{{ route('admin.posts.restore', $post) }}" class="shrink-0">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="min-h-11 rounded-md border border-gray-300 px-4 text-sm font-medium text-accent-dark hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent dark:border-gray-700 dark:text-accent dark:hover:bg-gray-800">
                            Restore
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">
            {{ $posts->links() }}
        </div>
    @endif
</x-layouts.admin>
