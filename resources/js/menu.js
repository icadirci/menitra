// QR menu bundle: Alpine only — no axios, no marketing code. Keep it small;
// it is loaded on every public menu page. Never loaded on the public site or panels.
import Alpine from 'alpinejs';
import { startAlpine } from './lib/alpine';

/**
 * Menu page state: client-side search over the rendered items and the
 * product detail sheet (deep-linkable as #i-{publicId}). No API calls.
 */
Alpine.data('menuPage', () => ({
    query: '',
    open: null,

    init() {
        const match = window.location.hash.match(/^#i-([0-9A-Za-z]+)$/);
        if (match) {
            this.show(match[1]);
        }

        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.close();
            }
        });
    },

    show(id) {
        this.open = id;
        document.documentElement.classList.add('menu-lock');
        history.replaceState(null, '', `#i-${id}`);
        this.$nextTick(() => this.$refs[`dlg-${id}`]?.focus());
    },

    close() {
        if (this.open === null) {
            return;
        }

        const id = this.open;
        this.open = null;
        document.documentElement.classList.remove('menu-lock');
        history.replaceState(null, '', window.location.pathname + window.location.search);
        document.getElementById(`row-${id}`)?.focus();
    },

    matches(text) {
        const query = this.query.trim().toLocaleLowerCase(document.documentElement.lang || undefined);

        return query === '' || (text || '').includes(query);
    },
}));

startAlpine();
