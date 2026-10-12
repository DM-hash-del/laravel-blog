<x-layouts.admin title="Tags">
    <h1 class="text-2xl font-semibold">Tags</h1>

    <form method="POST" action="{{ route('admin.tags.store') }}" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-start">
        @csrf

        <div class="sm:flex-1">
            <x-form-field name="name" label="New tag" required maxlength="255" />
        </div>

        <button type="submit" class="min-h-11 rounded-md bg-accent-dark px-4 font-medium text-white hover:bg-accent-dark/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 sm:mt-7 dark:bg-accent dark:text-gray-950 dark:hover:bg-accent/90 dark:focus-visible:ring-offset-gray-950">
            Add tag
        </button>
    </form>

    @if ($tags->isEmpty())
        <p class="mt-8 text-gray-600 dark:text-gray-400">No tags yet.</p>
    @else
        <ul class="mt-8 divide-y divide-gray-200 rounded-md border border-gray-200 bg-white dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-900">
            @foreach ($tags as $tag)
                <li class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="font-medium break-words">{{ $tag->name }}</p>
                        <p class="text-sm break-all text-gray-600 dark:text-gray-400">
                            {{ $tag->slug }} · {{ trans_choice('{0} No posts|{1} :count post|[2,*] :count posts', $tag->posts_count) }}
                        </p>
                    </div>

                    <div class="flex shrink-0 gap-2">
                        <a href="{{ route('admin.tags.edit', $tag) }}" class="flex min-h-11 items-center rounded-md border border-gray-300 px-4 text-sm font-medium text-accent-dark hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent dark:border-gray-700 dark:text-accent dark:hover:bg-gray-800">
                            Edit
                        </a>

                        <form method="POST" action="{{ route('admin.tags.destroy', $tag) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="min-h-11 rounded-md border border-gray-300 px-4 text-sm font-medium text-red-700 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:border-gray-700 dark:text-red-400 dark:hover:bg-red-950">
                                Delete
                            </button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">
            {{ $tags->links() }}
        </div>
    @endif
</x-layouts.admin>
