{{-- A small message fixed to the bottom of the screen, faded in and out by script (used for autosave). --}}
<div data-toast hidden role="status" aria-live="polite"
    class="pointer-events-none fixed inset-x-0 bottom-0 z-40 flex justify-center px-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
    <p data-toast-message
        class="rounded-full bg-gray-900 px-4 py-2 text-sm font-medium text-white opacity-0 shadow-lg transition-opacity duration-300 motion-reduce:transition-none dark:bg-gray-100 dark:text-gray-900"></p>
</div>
