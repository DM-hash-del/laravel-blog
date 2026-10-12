@props(['name', 'label', 'type' => 'text', 'value' => null])

<div class="flex flex-col gap-1.5">
    <label for="{{ $name }}" class="text-sm font-medium">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}"
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        {{ $attributes->merge(['class' => 'min-h-11 rounded-md border border-gray-300 bg-white px-3 text-base focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent dark:border-gray-700 dark:bg-gray-900']) }}>
    @error($name)
        <p id="{{ $name }}-error" class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
