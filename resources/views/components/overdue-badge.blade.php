@props(['post'])

@if ($post->isOverdue())
    <span {{ $attributes->class('inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-950 dark:text-red-300') }}>
        Overdue
    </span>
@endif
