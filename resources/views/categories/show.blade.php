<x-layouts.public :title="$category->name" :description="'Posts in the '.$category->name.' category.'">
    <p class="text-sm text-gray-600 dark:text-gray-400">Category</p>
    <h1 class="mb-6 text-3xl font-bold break-words">{{ $category->name }}</h1>

    <x-list-controls :filters="$filters" :action="route('categories.show', $category)" />

    <x-post-list :posts="$posts" :filters="$filters" />
</x-layouts.public>
