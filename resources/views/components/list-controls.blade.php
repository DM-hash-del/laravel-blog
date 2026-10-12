@props(['filters', 'action'])

@php
    $isFiltered = filled($filters['search']) || $filters['sort'] !== 'newest';
@endphp

<form method="GET" action="{{ $action }}" role="search" class="flex flex-col gap-3 sm:flex-row sm:items-end">
    <div class="flex flex-1 flex-col gap-1.5">
        <label for="search" class="text-sm font-medium">Search titles and categories</label>
        <input id="search" name="search" type="search" value="{{ $filters['search'] }}" maxlength="100"
            class="min-h-11 rounded-md border border-gray-300 bg-white px-3 text-base focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent dark:border-gray-700 dark:bg-gray-900">
    </div>

    <div class="flex flex-col gap-1.5">
        <label for="sort" class="text-sm font-medium">Sort</label>
        <select id="sort" name="sort"
            class="min-h-11 rounded-md border border-gray-300 bg-white px-3 text-base focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent dark:border-gray-700 dark:bg-gray-900">
            <option value="newest" @selected($filters['sort'] === 'newest')>Newest first</option>
            <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest first</option>
        </select>
    </div>

    <div class="flex gap-2">
        <button type="submit" class="min-h-11 flex-1 rounded-md bg-accent-dark px-4 font-medium text-white hover:bg-accent-dark/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 sm:flex-none dark:bg-accent dark:text-gray-950 dark:hover:bg-accent/90 dark:focus-visible:ring-offset-gray-950">
            Apply
        </button>
        @if ($isFiltered)
            <a href="{{ $action }}" class="flex min-h-11 items-center rounded-md border border-gray-300 px-4 text-sm font-medium text-gray-800 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                Clear
            </a>
        @endif
    </div>
</form>

@if ($errors->has('search') || $errors->has('sort'))
    <p role="alert" class="mt-3 text-sm text-red-700 dark:text-red-400">
        {{ $errors->first('search') ?: $errors->first('sort') }} The list has been reset.
    </p>
@endif
