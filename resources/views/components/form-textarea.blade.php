@props(['name', 'label', 'value' => null, 'hint' => null])

@php
    $describedBy = collect([
        $hint ? $name.'-hint' : null,
        $errors->has($name) ? $name.'-error' : null,
    ])->filter()->implode(' ');
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $name }}" class="text-sm font-medium">{{ $label }}</label>
    @if ($hint)
        <p id="{{ $name }}-hint" class="text-sm text-gray-600 dark:text-gray-400">{{ $hint }}</p>
    @endif
    <textarea id="{{ $name }}" name="{{ $name }}"
        @error($name) aria-invalid="true" @enderror
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->merge(['class' => 'rounded-md border border-gray-300 bg-white px-3 py-2 text-base focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent dark:border-gray-700 dark:bg-gray-900']) }}>{{ old($name, $value) }}</textarea>
    @error($name)
        <p id="{{ $name }}-error" class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
