@props(['status'])

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
    'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => $status === \App\Enums\PostStatus::Draft,
    'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' => $status === \App\Enums\PostStatus::Scheduled,
    'bg-accent/15 text-accent-dark dark:bg-accent/20 dark:text-accent' => $status === \App\Enums\PostStatus::Published,
]) }}>
    {{ $status->label() }}
</span>
