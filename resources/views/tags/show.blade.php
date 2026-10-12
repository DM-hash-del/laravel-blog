<x-layouts.public :title="$tag->name" :description="'Posts tagged '.$tag->name.'.'">
    <p class="text-sm text-gray-600 dark:text-gray-400">Tag</p>
    <h1 class="mb-6 text-3xl font-bold break-words">{{ $tag->name }}</h1>

    <x-list-controls :filters="$filters" :action="route('tags.show', $tag)" />

    <x-post-list :posts="$posts" :filters="$filters" />
</x-layouts.public>
