<x-layouts.public>
    <h1 class="sr-only">Latest posts</h1>

    <x-list-controls :filters="$filters" :action="route('home')" />

    <x-post-list :posts="$posts" :filters="$filters" />
</x-layouts.public>
