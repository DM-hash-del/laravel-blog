@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }} admin</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
        <header class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
                <a href="{{ route('admin.dashboard') }}" class="flex min-h-11 items-center font-semibold text-gray-900 dark:text-gray-100">
                    {{ config('app.name') }}
                </a>

                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="min-h-11 rounded-md border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                            Log out
                        </button>
                    </form>
                @endauth
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-6 sm:py-10">
            {{ $slot }}
        </main>
    </body>
</html>
