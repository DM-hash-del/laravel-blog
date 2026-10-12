<x-layouts.admin title="Edit tag">
    <div class="mx-auto w-full max-w-md">
        <a href="{{ route('admin.tags.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-accent-dark hover:underline dark:text-accent">
            ← Back to tags
        </a>

        <h1 class="mt-2 text-2xl font-semibold">Edit tag</h1>

        <form method="POST" action="{{ route('admin.tags.update', $tag) }}" class="mt-6 flex flex-col gap-5">
            @csrf
            @method('PUT')

            <x-form-field name="name" label="Name" :value="$tag->name" required maxlength="255" autofocus />

            <p class="text-sm break-all text-gray-600 dark:text-gray-400">URL slug: {{ $tag->slug }}. Set when the tag was created and does not change on rename.</p>

            <button type="submit" class="min-h-11 rounded-md bg-accent-dark px-4 font-medium text-white hover:bg-accent-dark/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 dark:bg-accent dark:text-gray-950 dark:hover:bg-accent/90 dark:focus-visible:ring-offset-gray-950">
                Save tag
            </button>
        </form>
    </div>
</x-layouts.admin>
