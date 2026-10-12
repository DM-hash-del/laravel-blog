import { postForm } from '../lib/http.js';

/**
 * Autosave for new posts and drafts: an automatic Save draft.
 *
 * Saves 3 seconds after editing stops, and at least every 20 seconds while editing
 * continues, but only when the draft fields changed. Skipped and refused saves are
 * logged with console.debug for local diagnosis only. Saves are skipped quietly
 * unless the title has a letter or number and an existing category is chosen
 * (a typed New category waits for a manual save). The server applies the Save
 * draft rules, never publishes or schedules, and never creates categories or tags.
 *
 * The first save of a new post creates the draft without leaving the page; the form
 * then points at that draft so later saves and the save buttons update it rather
 * than creating a duplicate. Autosave pauses while the AI overlay locks the form,
 * and after AI suggestions fill fields until the admin's own next edit.
 * A small toast appears only when a save succeeds.
 */
const quietMilliseconds = 3000;
const maximumWaitMilliseconds = 20000;
const toastMilliseconds = 2500;

export function init() {
    const form = document.querySelector('form[data-autosave]');

    if (!form) {
        return;
    }

    const toast = document.querySelector('[data-toast]');
    const toastMessage = toast?.querySelector('[data-toast-message]');
    const assistButton = form.querySelector('[data-ai-assist]');

    let url = form.dataset.autosaveUrl;
    let quietTimer = null;
    let firstChangeAt = null;
    let saving = null;
    let pendingAfterSave = false;
    let pausedForAi = false;
    let lastSaved = snapshot();
    let toastTimer = null;

    function snapshot() {
        const data = new FormData(form);
        const fields = ['title', 'slug', 'excerpt', 'body', 'category_id'];

        return JSON.stringify([...fields.map((name) => data.get(name) ?? ''), data.getAll('tags[]')]);
    }

    /**
     * Get why the form can't be autosaved yet, or null when it can.
     */
    function blockingReason() {
        const title = (form.elements.namedItem('title')?.value ?? '').trim();
        const categoryId = form.elements.namedItem('category_id')?.value ?? '';
        const newCategory = (form.elements.namedItem('new_category')?.value ?? '').trim();

        if (!/[\p{L}\p{N}]/u.test(title)) {
            return 'title has no letters or numbers';
        }

        if (title.length > 255) {
            return 'title is longer than 255 characters';
        }

        if (newCategory !== '') {
            return 'a typed new category waits for a manual save';
        }

        if (categoryId === '') {
            return 'choose a category from the list';
        }

        return null;
    }

    function showToast(message) {
        if (!toast || !toastMessage) {
            return;
        }

        clearTimeout(toastTimer);
        toastMessage.textContent = message;
        toast.hidden = false;
        requestAnimationFrame(() => toastMessage.classList.replace('opacity-0', 'opacity-100'));

        toastTimer = setTimeout(() => {
            toastMessage.classList.replace('opacity-100', 'opacity-0');
            toastTimer = setTimeout(() => {
                toast.hidden = true;
            }, 300);
        }, toastMilliseconds);
    }

    function pointFormAtDraft(reply) {
        // The save buttons are named "action", which hides the form's action property, so use the attribute.
        if (form.getAttribute('action') !== reply.form_action) {
            form.setAttribute('action', reply.form_action);
        }

        if (!form.querySelector('input[name="_method"]')) {
            const method = document.createElement('input');
            method.type = 'hidden';
            method.name = '_method';
            method.value = 'PUT';
            form.append(method);
        }

        if (assistButton) {
            assistButton.setAttribute('formaction', reply.assist_url);
            assistButton.dataset.assistUrl = reply.assist_url;
        }

        url = reply.autosave_url;
        form.dataset.autosaveUrl = reply.autosave_url;
    }

    async function save() {
        clearTimeout(quietTimer);
        quietTimer = null;

        if (saving) {
            pendingAfterSave = true;

            return;
        }

        if (form.inert) {
            schedule();

            return;
        }

        const current = snapshot();
        firstChangeAt = null;

        const reason = pausedForAi
            ? 'waiting for your own edit after AI suggestions'
            : (current === lastSaved ? 'nothing changed' : blockingReason());

        if (reason !== null) {
            // Quiet for the admin; visible in the browser console (and Boost's browser log) for diagnosis.
            console.debug(`[autosave] skipped: ${reason}`);

            return;
        }

        saving = postForm(url, new FormData(form)).then((reply) => {
            lastSaved = current;
            pointFormAtDraft(reply);
            showToast(`Draft autosaved at ${reply.saved_at}`);
        }).catch((error) => {
            // Quietly skipped for the admin: the next edit will try again.
            console.debug('[autosave] refused', error?.status ?? 'network error', error?.data?.errors ?? error?.message ?? '');
        });

        await saving;
        saving = null;

        if (pendingAfterSave) {
            pendingAfterSave = false;
            schedule();
        }
    }

    function schedule() {
        clearTimeout(quietTimer);
        firstChangeAt ??= Date.now();

        const waited = Date.now() - firstChangeAt;
        const delay = Math.min(quietMilliseconds, Math.max(0, maximumWaitMilliseconds - waited));

        quietTimer = setTimeout(save, delay);
    }

    const onEdit = (event) => {
        // Only the AI suggestions fill the form by script; wait for the admin's own edit before saving them.
        pausedForAi = !event.isTrusted;

        schedule();
    };

    form.addEventListener('input', onEdit);
    form.addEventListener('change', onEdit);

    form.addEventListener('submit', async (event) => {
        clearTimeout(quietTimer);
        quietTimer = null;

        if (saving) {
            event.preventDefault();
            const submitter = event.submitter ?? null;
            await saving;
            form.requestSubmit(submitter);
        }
    });
}
