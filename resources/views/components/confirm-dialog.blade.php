{{-- One shared confirmation dialog, opened by resources/js/modules/confirm.js for forms with data-confirm. --}}
<dialog id="confirm-dialog" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message"
    class="m-auto w-[calc(100%-2rem)] max-w-md rounded-lg border border-gray-200 bg-white p-0 text-gray-900 shadow-xl backdrop:bg-gray-950/60 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-100">
    <div class="flex flex-col gap-4 p-6">
        <h2 id="confirm-dialog-title" class="text-lg font-semibold">Are you sure?</h2>
        <p id="confirm-dialog-message" data-confirm-message class="text-sm text-gray-700 dark:text-gray-300"></p>
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" data-confirm-cancel
                class="min-h-11 rounded-md border border-gray-300 px-4 text-sm font-medium text-gray-800 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                Cancel
            </button>
            <button type="button" data-confirm-accept
                class="min-h-11 rounded-md bg-red-700 px-4 text-sm font-medium text-white hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:bg-red-600 dark:hover:bg-red-500 dark:focus-visible:ring-offset-gray-900">
                Confirm
            </button>
        </div>
    </div>
</dialog>
