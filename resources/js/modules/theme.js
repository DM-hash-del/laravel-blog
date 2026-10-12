/**
 * The System / Light / Dark toggle. A choice is stored in localStorage and set as
 * data-theme on <html>; System removes both, so the system setting applies.
 * The head theme script applies the stored choice before first paint.
 */
const storageKey = 'theme';

function storedTheme() {
    try {
        const theme = localStorage.getItem(storageKey);

        return theme === 'light' || theme === 'dark' ? theme : 'system';
    } catch {
        return 'system';
    }
}

function apply(theme) {
    if (theme === 'system') {
        delete document.documentElement.dataset.theme;
    } else {
        document.documentElement.dataset.theme = theme;
    }
}

function store(theme) {
    try {
        if (theme === 'system') {
            localStorage.removeItem(storageKey);
        } else {
            localStorage.setItem(storageKey, theme);
        }
    } catch {
        // Storage is blocked: the choice lasts for this page only.
    }
}

export function init() {
    const toggles = document.querySelectorAll('[data-theme-toggle]');

    const tick = (theme) => {
        toggles.forEach((toggle) => {
            const option = toggle.querySelector(`input[value="${theme}"]`);

            if (option) {
                option.checked = true;
            }
        });
    };

    toggles.forEach((toggle) => {
        toggle.hidden = false;

        toggle.addEventListener('change', (event) => {
            const theme = event.target.value;
            store(theme);
            apply(theme);
        });
    });

    tick(storedTheme());

    window.addEventListener('storage', (event) => {
        if (event.key === storageKey || event.key === null) {
            const theme = storedTheme();
            apply(theme);
            tick(theme);
        }
    });
}
