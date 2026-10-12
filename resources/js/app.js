/**
 * Loads each page module only when the page has an element with its data- hook.
 *
 * Every page works without JavaScript; modules only enhance it. Add a module by
 * mapping its hook selector to a dynamic import of resources/js/modules/<name>.js,
 * which exports init().
 */
const modules = {
    '[data-confirm]': () => import('./modules/confirm.js'),
    '[data-theme-toggle]': () => import('./modules/theme.js'),
};

for (const [selector, load] of Object.entries(modules)) {
    if (document.querySelector(selector)) {
        load().then(({ init }) => init());
    }
}
