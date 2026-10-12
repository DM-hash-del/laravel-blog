<x-layouts.admin title="New post">
    <div class="mx-auto w-full max-w-3xl">
        <a href="{{ route('admin.posts.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-accent-dark hover:underline dark:text-accent">
            ← Back to posts
        </a>

        <h1 class="mt-2 mb-6 text-2xl font-semibold">New post</h1>

        @include('admin.posts._form', ['action' => route('admin.posts.store')])
    </div>
</x-layouts.admin>
