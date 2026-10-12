<x-layouts.admin title="Edit post">
    <div class="mx-auto w-full max-w-3xl">
        <a href="{{ route('admin.posts.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-accent-dark hover:underline dark:text-accent">
            ← Back to posts
        </a>

        <div class="mt-2 mb-6 flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold">Edit post</h1>
            <x-status-badge :status="$post->status" />
        </div>

        @include('admin.posts._form', ['action' => route('admin.posts.update', $post)])

        <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" class="mt-10 border-t border-gray-200 pt-6 dark:border-gray-800">
            @csrf
            @method('DELETE')
            <button type="submit" class="min-h-11 rounded-md border border-gray-300 px-4 text-sm font-medium text-red-700 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:border-gray-700 dark:text-red-400 dark:hover:bg-red-950">
                Move to trash
            </button>
        </form>
    </div>
</x-layouts.admin>
