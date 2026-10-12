/**
 * Keeps the editor's schedule date hidden until Schedule (or Update schedule) is
 * pressed, then shows it with Confirm and Cancel. Nothing is scheduled without
 * Confirm. Without JavaScript the date field shows and Schedule submits as before.
 */
export function init() {
    const panel = document.querySelector('[data-schedule-panel]');
    const form = panel?.closest('form');

    if (!panel || !form) {
        return;
    }

    const triggers = [...form.querySelectorAll('[data-schedule-trigger]')];
    const actions = panel.querySelector('[data-schedule-actions]');
    const input = panel.querySelector('input[type="datetime-local"]');
    const cancel = panel.querySelector('[data-schedule-cancel]');

    if (triggers.length === 0) {
        panel.hidden = true;

        return;
    }

    let openedBy = null;
    let valueBeforeOpening = input.value;

    const open = (trigger = null) => {
        openedBy = trigger;
        valueBeforeOpening = input.value;
        panel.hidden = false;
        actions.hidden = false;
        triggers.forEach((button) => (button.hidden = true));
        input.focus();
    };

    const close = () => {
        input.value = valueBeforeOpening;
        panel.hidden = true;
        actions.hidden = true;
        triggers.forEach((button) => (button.hidden = false));
        (openedBy ?? triggers[0]).focus();
    };

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            open(trigger);
        });
    });

    cancel.addEventListener('click', close);

    if (panel.hasAttribute('data-schedule-open')) {
        open();
    } else {
        panel.hidden = true;
    }
}
