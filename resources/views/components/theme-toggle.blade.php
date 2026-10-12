{{-- Hidden until resources/js/modules/theme.js runs; without JavaScript the site follows the system setting. --}}
<fieldset data-theme-toggle hidden {{ $attributes->class('flex rounded-md border border-gray-300 p-0.5 text-sm dark:border-gray-700') }}>
    <legend class="sr-only">Colour theme</legend>
    @foreach (['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'] as $value => $label)
        <label class="flex min-h-11 cursor-pointer items-center rounded px-2.5 font-medium text-gray-700 hover:bg-gray-100 has-checked:bg-gray-200 has-checked:text-gray-900 has-focus-visible:ring-2 has-focus-visible:ring-accent dark:text-gray-300 dark:hover:bg-gray-800 dark:has-checked:bg-gray-700 dark:has-checked:text-gray-100">
            <input type="radio" name="theme" value="{{ $value }}" class="sr-only">
            {{ $label }}
        </label>
    @endforeach
</fieldset>
