import { isAbortError, postJson } from '../lib/http.js';

/**
 * Re-renders the editor's body preview as the admin types, through the server's
 * preview endpoint (the same Markdown component as the public post page).
 *
 * Anything that sets #body from code (such as AI suggestions or autosave) only
 * needs to dispatch an "input" event on it to refresh the preview.
 */
const debounceMilliseconds = 500;

export function init() {
    const preview = document.querySelector('[data-live-preview]');
    const body = document.getElementById('body');
    const toggle = document.getElementById('show-preview');

    if (!preview || !body) {
        return;
    }

    const content = preview.querySelector('[data-preview-content]');
    const note = preview.querySelector('[data-preview-note]');
    const url = preview.dataset.previewUrl;
    const liveNote = 'Updates as you type.';

    let timer = null;
    let controller = null;
    let renderedBody = body.value;

    note.textContent = liveNote;

    const isVisible = () => !toggle || toggle.checked;

    const refresh = async () => {
        if (!isVisible() || body.value === renderedBody) {
            return;
        }

        controller?.abort();
        const current = new AbortController();
        controller = current;
        const requestedBody = body.value;
        preview.setAttribute('aria-busy', 'true');

        try {
            const { html } = await postJson(url, { body: requestedBody }, { signal: current.signal });
            content.innerHTML = html;
            renderedBody = requestedBody;
            note.textContent = liveNote;
        } catch (error) {
            if (isAbortError(error)) {
                return;
            }

            note.textContent = 'Preview paused, it will update when you keep typing.';
        } finally {
            if (controller === current) {
                preview.removeAttribute('aria-busy');
            }
        }
    };

    body.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(refresh, debounceMilliseconds);
    });

    toggle?.addEventListener('change', () => {
        if (toggle.checked) {
            refresh();
        }
    });
}
