<x-layouts.admin title="Edit post">
    <div class="mx-auto w-full max-w-3xl">
        <a href="{{ route('admin.posts.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-accent-dark hover:underline dark:text-accent">
            ← Back to posts
        </a>

        <div class="mt-2 mb-6 flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold">Edit post</h1>
            <x-status-badge :status="$post->status" />
            <x-overdue-badge :post="$post" />
        </div>

        @if ($post->isOverdue())
            <p role="status" class="mb-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                This post was due to publish at {{ $post->published_at->timezone(config('app.display_timezone'))->format('j M Y, H:i') }} but hasn't yet. Check that the scheduler (cron) is running.
            </p>
        @endif

        @include('admin.posts._form', ['action' => route('admin.posts.update', $post)])

        <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" class="mt-10 border-t border-gray-200 pt-6 dark:border-gray-800"
            data-confirm="Move “{{ $post->title }}” to the trash? It will be hidden from the site, and you can restore it from the Trash page." data-confirm-label="Move to trash">
            @csrf
            @method('DELETE')
            <button type="submit" class="min-h-11 rounded-md border border-gray-300 px-4 text-sm font-medium text-red-700 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:border-gray-700 dark:text-red-400 dark:hover:bg-red-950">
                Move to trash
            </button>
        </form>
    </div>
</x-layouts.admin>
