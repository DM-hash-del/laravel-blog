/**
 * Asks for confirmation before submitting any form with a data-confirm message.
 *
 * Uses the shared #confirm-dialog from the admin layout. Without JavaScript the
 * form simply submits. The message is set as text, never as HTML.
 */
export function init() {
    const dialog = document.getElementById('confirm-dialog');

    if (!dialog) {
        return;
    }

    const message = dialog.querySelector('[data-confirm-message]');
    const confirmButton = dialog.querySelector('[data-confirm-accept]');
    const cancelButton = dialog.querySelector('[data-confirm-cancel]');
    let pending = null;

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.matches('[data-confirm]')) {
            return;
        }

        if (form.dataset.confirmed === 'true') {
            delete form.dataset.confirmed;

            return;
        }

        event.preventDefault();
        pending = { form, submitter: event.submitter ?? null };
        message.textContent = form.dataset.confirm;
        confirmButton.textContent = form.dataset.confirmLabel || 'Confirm';
        dialog.showModal();
        cancelButton.focus();
    });

    confirmButton.addEventListener('click', () => {
        const { form, submitter } = pending ?? {};
        pending = null;
        dialog.close();

        if (form) {
            form.dataset.confirmed = 'true';
            form.requestSubmit(submitter);
        }
    });

    cancelButton.addEventListener('click', () => dialog.close());

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    dialog.addEventListener('close', () => {
        pending = null;
    });
}
