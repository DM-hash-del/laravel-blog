@props(['posts', 'filters'])

@if ($posts->isEmpty())
    <p class="mt-8 text-gray-600 dark:text-gray-400">
        @if (filled($filters['search']))
            No posts match “{{ $filters['search'] }}”.
        @else
            No posts yet.
        @endif
    </p>
@else
    <div class="mt-4 divide-y divide-gray-200 dark:divide-gray-800">
        @foreach ($posts as $post)
            <x-post-card :post="$post" />
        @endforeach
    </div>

    <div class="mt-6">
        {{ $posts->links() }}
    </div>
@endif
