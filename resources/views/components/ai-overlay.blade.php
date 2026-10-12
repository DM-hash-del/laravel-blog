{{-- Shown by resources/js/modules/ai-assist.js while an AI request runs; the form behind it is inert. --}}
<div data-ai-overlay hidden role="dialog" aria-modal="true" aria-labelledby="ai-overlay-title"
    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/60 p-4">
    <div class="flex w-full max-w-sm flex-col items-center gap-4 rounded-lg border border-gray-200 bg-white p-6 text-center text-gray-900 shadow-xl dark:border-gray-800 dark:bg-gray-900 dark:text-gray-100">
        <span class="size-8 animate-spin rounded-full border-4 border-gray-200 border-t-accent dark:border-gray-700 dark:border-t-accent" aria-hidden="true"></span>
        <p id="ai-overlay-title" class="font-medium">Generating suggestions…</p>
        <p class="text-sm text-gray-600 dark:text-gray-400">Your text is safe. Nothing is saved until you save.</p>
        <button type="button" data-ai-cancel
            class="min-h-11 rounded-md border border-gray-300 px-4 text-sm font-medium text-gray-800 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
            Cancel
        </button>
    </div>
</div>
