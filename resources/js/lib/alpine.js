import Alpine from 'alpinejs';

/**
 * Starts Alpine for the Blade bundles (public site, auth pages, QR menus).
 *
 * The Filament panels never load these bundles: Livewire ships and starts its
 * own Alpine there. The guard below keeps a second instance from starting if
 * an Alpine is ever already present on the page.
 */
export function startAlpine() {
    if (window.Alpine) {
        return window.Alpine;
    }

    window.Alpine = Alpine;
    Alpine.start();

    return Alpine;
}
