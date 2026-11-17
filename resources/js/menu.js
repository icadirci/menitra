// QR menu bundle: Alpine only — no axios, no marketing code. Keep it small;
// it is loaded on every public menu page. Never loaded on the public site or panels.
import Alpine from 'alpinejs';
import { startAlpine } from './lib/alpine';

const CATEGORY_HASH = /^#(c[0-9]+)$/;

/**
 * Menu page state: client-side search over the rendered items and the
 * product detail sheet (deep-linkable as #i-{publicId}). No API calls.
 *
 * With `tiles` (layout option category_layout = tiles) the page starts with
 * category cards; openCategory() shows one category (#c{id}, a history entry,
 * so the phone's back button returns to the cards). A search always looks
 * through every category.
 */
Alpine.data('menuPage', (tiles = false) => ({
    query: '',
    open: null,
    tiles,
    cat: null,
    pushed: false,

    init() {
        const match = window.location.hash.match(/^#i-([0-9A-Za-z]+)$/);

        if (this.tiles) {
            const category = window.location.hash.match(CATEGORY_HASH);
            this.cat = category
                ? category[1]
                : (match && document.getElementById(`row-${match[1]}`)?.closest('section[id]')?.id) || null;

            window.addEventListener('popstate', () => this.syncCategory());
        }

        if (match) {
            this.show(match[1]);
        }

        window.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            if (this.open !== null) {
                this.close();
            } else if (this.cat !== null) {
                this.closeCategory();
            }
        });
    },

    visible(el) {
        if (this.query.trim() !== '') {
            return this.matches(el.dataset.search);
        }

        return !this.tiles || this.cat === el.id;
    },

    openCategory(anchor) {
        this.cat = anchor;
        this.pushed = true;
        history.pushState(null, '', `#${anchor}`);
        this.$nextTick(() => {
            const back = document.getElementById('menu-tiles-back');
            back?.scrollIntoView({ block: 'start' });
            back?.focus({ preventScroll: true });
        });
    },

    closeCategory() {
        if (this.pushed) {
            history.back();

            return;
        }

        history.replaceState(null, '', window.location.pathname + window.location.search);
        this.leaveCategory(this.cat);
    },

    syncCategory() {
        const match = window.location.hash.match(CATEGORY_HASH);
        const previous = this.cat;

        if (this.open !== null) {
            this.open = null;
            document.documentElement.classList.remove('menu-lock');
        }

        this.cat = match ? match[1] : null;

        if (this.cat === null && previous !== null) {
            this.leaveCategory(previous);
        }
    },

    leaveCategory(previous) {
        this.cat = null;
        this.pushed = false;
        this.$nextTick(() => {
            const tile = document.querySelector(`[data-tile="${previous}"]`);
            tile?.scrollIntoView({ block: 'center' });
            tile?.focus({ preventScroll: true });
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
        history.replaceState(null, '', window.location.pathname + window.location.search + (this.cat ? `#${this.cat}` : ''));
        document.getElementById(`row-${id}`)?.focus();
    },

    matches(text) {
        const query = this.query.trim().toLocaleLowerCase(document.documentElement.lang || undefined);

        return query === '' || (text || '').includes(query);
    },
}));

startAlpine();
