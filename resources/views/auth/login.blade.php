<x-layouts.admin title="Log in">
    <div class="mx-auto w-full max-w-sm">
        <h1 class="text-2xl font-semibold">Log in</h1>

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 flex flex-col gap-5">
            @csrf

            <div class="flex flex-col gap-1.5">
                <label for="email" class="text-sm font-medium">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                    class="min-h-11 rounded-md border border-gray-300 bg-white px-3 text-base focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent dark:border-gray-700 dark:bg-gray-900">
                @error('email')
                    <p class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="password" class="text-sm font-medium">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                    class="min-h-11 rounded-md border border-gray-300 bg-white px-3 text-base focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent dark:border-gray-700 dark:bg-gray-900">
                @error('password')
                    <p class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="min-h-11 rounded-md bg-accent-dark px-4 font-medium text-white hover:bg-accent-dark/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 dark:bg-accent dark:text-gray-950 dark:hover:bg-accent/90 dark:focus-visible:ring-offset-gray-950">
                Log in
            </button>
        </form>
    </div>
</x-layouts.admin>
