@props(['post'])

<article class="py-6">
    <p class="flex flex-wrap items-center gap-x-2 text-sm text-gray-600 dark:text-gray-400">
        <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->timezone(config('app.display_timezone'))->format('j F Y') }}</time>
        <span aria-hidden="true">·</span>
        <a href="{{ route('categories.show', $post->category) }}" class="inline-flex min-h-11 items-center text-accent-dark hover:underline dark:text-accent">{{ $post->category->name }}</a>
    </p>
    <h2 class="text-xl font-semibold break-words">
        <a href="{{ route('posts.show', $post->slug) }}" class="hover:text-accent-dark dark:hover:text-accent">{{ $post->title }}</a>
    </h2>
    @if ($post->excerpt)
        <p class="mt-2 text-gray-700 dark:text-gray-300">{{ $post->excerpt }}</p>
    @endif
</article>
