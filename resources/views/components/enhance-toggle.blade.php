@props(['field', 'isEmpty'])

@php
    // Ticked by default for an empty field and unticked for a filled one; a submitted choice wins.
    $isTicked = old('enhance') !== null ? (bool) old("enhance.{$field}") : $isEmpty;
@endphp

<label class="flex min-h-11 cursor-pointer items-center gap-2 self-start text-sm text-gray-700 dark:text-gray-300">
    <input type="hidden" name="enhance[{{ $field }}]" value="0">
    <input type="checkbox" name="enhance[{{ $field }}]" value="1" @checked($isTicked)
        class="size-4 accent-accent-dark dark:accent-accent">
    Enhance with AI <span class="text-gray-500 dark:text-gray-400">(empty fields are always generated)</span>
</label>
