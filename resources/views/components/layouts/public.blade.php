@props(['title' => null, 'description' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif
        <x-theme-script />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-white font-sans text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
        <header class="border-b border-gray-200 dark:border-gray-800">
            <div class="mx-auto flex max-w-2xl flex-wrap items-center justify-between gap-2 px-4 py-3">
                <a href="{{ route('home') }}" class="flex min-h-11 items-center text-lg font-semibold text-gray-900 hover:text-accent-dark dark:text-gray-100 dark:hover:text-accent">
                    {{ config('app.name') }}
                </a>
                <x-theme-toggle />
            </div>
        </header>

        <main class="mx-auto w-full max-w-2xl flex-1 px-4 py-8 sm:py-12">
            {{ $slot }}
        </main>

        <footer class="border-t border-gray-200 dark:border-gray-800">
            <p class="mx-auto max-w-2xl px-4 py-6 text-sm text-gray-600 dark:text-gray-400">
                © {{ now()->year }} {{ config('app.name') }}
            </p>
        </footer>
    </body>
</html>
