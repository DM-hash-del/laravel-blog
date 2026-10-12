@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }} admin</title>
        <x-theme-script />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
        <header class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
                <a href="{{ route('admin.posts.index') }}" class="flex min-h-11 items-center font-semibold text-gray-900 dark:text-gray-100">
                    {{ config('app.name') }}
                </a>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <x-theme-toggle />
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="min-h-11 rounded-md border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                                Log out
                            </button>
                        </form>
                    @endauth
                </div>
            </div>

            @can('access-admin')
                <nav aria-label="Admin" class="mx-auto flex max-w-5xl gap-1 overflow-x-auto px-4 pb-2">
                    @foreach (['admin.posts.*' => 'Posts', 'admin.categories.*' => 'Categories', 'admin.tags.*' => 'Tags'] as $routePattern => $navLabel)
                        @php($isCurrent = request()->routeIs($routePattern))
                        <a href="{{ route(str_replace('*', 'index', $routePattern)) }}" @if ($isCurrent) aria-current="page" @endif
                            @class([
                                'flex min-h-11 items-center rounded-md px-3 text-sm font-medium hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent dark:hover:bg-gray-800',
                                'bg-gray-100 text-accent-dark dark:bg-gray-800 dark:text-accent' => $isCurrent,
                                'text-gray-700 dark:text-gray-300' => ! $isCurrent,
                            ])>
                            {{ $navLabel }}
                        </a>
                    @endforeach
                </nav>
            @endcan
        </header>

        <main class="mx-auto max-w-5xl px-4 py-6 sm:py-10">
            @if (session('status'))
                <p role="status" class="mb-6 rounded-md border border-accent bg-accent/10 px-4 py-3 text-sm dark:bg-accent/15">
                    {{ session('status') }}
                </p>
            @endif

            @if (session('error'))
                <p role="alert" class="mb-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                    {{ session('error') }}
                </p>
            @endif

            {{ $slot }}
        </main>

        <x-confirm-dialog />
    </body>
</html>
