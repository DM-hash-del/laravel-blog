{{-- The body preview, rendered on the editor page and by PostPreviewController for the live update. --}}
@if (filled($body))
    <x-markdown :source="$body" class="mt-4" />
@else
    <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">Nothing to preview yet.</p>
@endif
