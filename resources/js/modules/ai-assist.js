import { HttpError, isAbortError, postForm } from '../lib/http.js';

/**
 * Generate / enhance with AI without reloading the page.
 *
 * This is the default whenever JavaScript runs; without JavaScript the button
 * submits the form and the page reloads with the suggestions. While a request runs the form is inert behind an overlay with a Cancel button.
 * Suggestions are filled in place and each changed field fires an input event,
 * so the live preview re-renders the new body. Nothing is saved.
 *
 * When the AI rate limit is hit, Generate stays disabled behind a live countdown.
 * The wait comes from the server: data-retry-after on page load (rendered fresh
 * every time, so a refresh or second tab stays in sync) or retry_after on a 429.
 * It counts against a fixed deadline, so slowed background tabs don't drift.
 */
const statusStyles = {
    success: ['text-gray-700', 'dark:text-gray-300'],
    error: ['text-red-700', 'dark:text-red-400'],
};

function setValue(element, value) {
    if (!element || element.value === value) {
        return;
    }

    element.value = value;
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
}

function fill(form, input) {
    for (const name of ['title', 'excerpt', 'body', 'new_category', 'new_tags']) {
        if (name in input) {
            setValue(form.elements.namedItem(name), input[name] ?? '');
        }
    }

    if ('category_id' in input) {
        setValue(form.elements.namedItem('category_id'), input.category_id === null ? '' : String(input.category_id));
    }

    if ('tags' in input) {
        const tagIds = (input.tags ?? []).map(String);

        form.querySelectorAll('input[name="tags[]"]').forEach((box) => {
            box.checked = tagIds.includes(box.value);
        });
    }

    const tagsChosen = form.querySelector('input[name="tags[]"]:checked') !== null
        || (form.elements.namedItem('new_tags')?.value ?? '').trim() !== '';
    const isEmpty = {
        title: (form.elements.namedItem('title')?.value ?? '').trim() === '',
        excerpt: (form.elements.namedItem('excerpt')?.value ?? '').trim() === '',
        body: (form.elements.namedItem('body')?.value ?? '').trim() === '',
        tags: !tagsChosen,
    };

    for (const [field, empty] of Object.entries(isEmpty)) {
        const box = form.querySelector(`input[type="checkbox"][name="enhance[${field}]"]`);

        if (box) {
            box.checked = empty;
        }
    }
}

export function init() {
    const button = document.querySelector('[data-ai-assist]');
    const form = button?.closest('form');
    const overlay = document.querySelector('[data-ai-overlay]');

    if (!button || !form || !overlay) {
        return;
    }

    const status = form.querySelector('[data-ai-status]');
    const cancel = overlay.querySelector('[data-ai-cancel]');
    let controller = null;

    const showStatus = (message, kind) => {
        status.classList.remove(...statusStyles.success, ...statusStyles.error);
        status.classList.add(...statusStyles[kind]);
        status.textContent = message;
        status.hidden = false;
    };

    const onEscape = (event) => {
        if (event.key === 'Escape') {
            controller?.abort();
        }
    };

    const lock = () => {
        form.inert = true;
        overlay.hidden = false;
        document.addEventListener('keydown', onEscape);
        cancel.focus();
    };

    const unlock = () => {
        form.inert = false;
        overlay.hidden = true;
        document.removeEventListener('keydown', onEscape);
        button.focus();
    };

    cancel.addEventListener('click', () => controller?.abort());

    let countdownTimer = null;

    const waitMessage = (seconds) => `Too many AI requests. Try again in ${seconds} ${seconds === 1 ? 'second' : 'seconds'}.`;

    const startCountdown = (seconds) => {
        clearInterval(countdownTimer);

        const deadline = Date.now() + seconds * 1000;
        let shown = null;

        button.disabled = true;

        const tick = () => {
            const remaining = Math.ceil((deadline - Date.now()) / 1000);

            if (remaining <= 0) {
                clearInterval(countdownTimer);
                countdownTimer = null;
                button.disabled = false;
                delete button.dataset.retryAfter;
                showStatus('You can use Generate / enhance again.', 'success');

                return;
            }

            if (remaining !== shown) {
                shown = remaining;
                showStatus(waitMessage(remaining), 'error');
            }
        };

        tick();
        countdownTimer = setInterval(tick, 250);
    };

    const initialWait = Number.parseInt(button.dataset.retryAfter ?? '', 10);

    if (initialWait > 0) {
        startCountdown(initialWait);
    }

    button.addEventListener('click', async (event) => {
        event.preventDefault();

        if (controller || countdownTimer) {
            return;
        }

        controller = new AbortController();
        const formData = new FormData(form);
        status.hidden = true;
        lock();

        try {
            const reply = await postForm(button.dataset.assistUrl, formData, { signal: controller.signal });
            fill(form, reply.input ?? {});
            showStatus(reply.status, 'success');
        } catch (error) {
            if (isAbortError(error)) {
                showStatus('Cancelled. Your text is unchanged.', 'success');
            } else if (error instanceof HttpError && error.status === 429 && (error.data?.retry_after ?? error.retryAfter) > 0) {
                startCountdown(error.data?.retry_after ?? error.retryAfter);
            } else if (error instanceof HttpError) {
                showStatus(error.data?.errors?.ai?.[0] ?? error.data?.message ?? 'Something went wrong. Your text is unchanged.', 'error');
            } else {
                showStatus("Couldn't reach the server. Your text is unchanged.", 'error');
            }
        } finally {
            controller = null;
            unlock();
        }
    });
}
