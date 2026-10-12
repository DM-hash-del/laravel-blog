<x-layouts.public :title="$post->title" :description="$post->excerpt">
    <article>
        <header>
            <p class="flex flex-wrap items-center gap-x-2 text-sm text-gray-600 dark:text-gray-400">
                <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->timezone(config('app.display_timezone'))->format('j F Y') }}</time>
                <span aria-hidden="true">·</span>
                <a href="{{ route('categories.show', $post->category) }}" class="inline-flex min-h-11 items-center text-accent-dark hover:underline dark:text-accent">{{ $post->category->name }}</a>
            </p>
            <h1 class="text-3xl font-bold break-words sm:text-4xl">{{ $post->title }}</h1>
        </header>

        <x-markdown :source="$post->body" class="mt-8" />

        @if ($post->tags->isNotEmpty())
            <footer class="mt-10 border-t border-gray-200 pt-6 dark:border-gray-800">
                <h2 class="text-sm font-medium text-gray-600 dark:text-gray-400">Tags</h2>
                <ul class="mt-2 flex flex-wrap gap-2">
                    @foreach ($post->tags->sortBy('name') as $tag)
                        <li>
                            <a href="{{ route('tags.show', $tag) }}" class="flex min-h-11 items-center rounded-full border border-gray-300 px-4 text-sm text-accent-dark hover:border-accent dark:border-gray-700 dark:text-accent">{{ $tag->name }}</a>
                        </li>
                    @endforeach
                </ul>
            </footer>
        @endif
    </article>
</x-layouts.public>
