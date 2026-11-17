// QR menu bundle: Alpine only — no axios, no marketing code. Keep it small;
// it is loaded on every public menu page. Never loaded on the public site or panels.
import Alpine from 'alpinejs';
import { startAlpine } from './lib/alpine';

const CATEGORY_HASH = /^#(c[0-9]+)$/;
const ITEM_HASH = /^#i-([0-9A-Za-z]+)$/;
const CART_HASH = '#cart';
const CART = 'cart';

// The bottom dock (back / order list / to top) appears after this much scrolling.
const DOCK_OFFSET = 320;

// An order list is a reminder for one visit: it is forgotten after this long without changes.
const CART_TTL = 12 * 60 * 60 * 1000;

const hasItem = (id) => document.querySelector(`[x-ref="dlg-${id}"]`) !== null;

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Order list ("cart", theme layout option `cart`): kept only in this browser
 * (localStorage, one list per branch), never sent anywhere. Guests show it to
 * the staff and clear it with "I've ordered".
 */
Alpine.store('cart', {
    key: null,
    lines: [],
    currency: 'TRY',
    undo: null,

    load(key, currency) {
        this.key = `restano:cart:${key}`;
        this.currency = currency || 'TRY';

        try {
            const saved = JSON.parse(window.localStorage.getItem(this.key) || 'null');

            if (saved && Array.isArray(saved.lines) && Date.now() - (saved.at || 0) < CART_TTL) {
                this.lines = saved.lines.filter((line) => line && typeof line.key === 'string' && line.qty > 0);
            } else if (saved) {
                window.localStorage.removeItem(this.key);
            }
        } catch {
            this.lines = [];
        }
    },

    save() {
        try {
            if (this.lines.length === 0) {
                window.localStorage.removeItem(this.key);
            } else {
                window.localStorage.setItem(this.key, JSON.stringify({ at: Date.now(), lines: this.lines }));
            }
        } catch {
            // Private mode or storage full: the list still works for this page view.
        }
    },

    get count() {
        return this.lines.reduce((sum, line) => sum + line.qty, 0);
    },

    get total() {
        return this.lines.reduce((sum, line) => sum + (line.unit === null ? 0 : line.unit * line.qty), 0);
    },

    get hasUnpriced() {
        return this.lines.some((line) => line.unit === null);
    },

    add(line) {
        const existing = this.lines.find((candidate) => candidate.key === line.key);

        if (existing) {
            existing.qty += line.qty;
        } else {
            this.lines.push(line);
        }

        this.undo = null;
        this.save();
    },

    change(key, delta) {
        const line = this.lines.find((candidate) => candidate.key === key);

        if (!line) {
            return;
        }

        line.qty += delta;
        this.lines = this.lines.filter((candidate) => candidate.qty > 0);
        this.save();
    },

    clear() {
        this.undo = this.lines;
        this.lines = [];
        this.save();
    },

    restore() {
        if (this.undo) {
            this.lines = this.undo;
            this.undo = null;
            this.save();
        }
    },

    format(minor) {
        const whole = minor % 100 === 0;

        try {
            return new Intl.NumberFormat(document.documentElement.lang || undefined, {
                style: 'currency',
                currency: this.currency,
                numberingSystem: 'latn',
                minimumFractionDigits: whole ? 0 : 2,
                maximumFractionDigits: whole ? 0 : 2,
            }).format(minor / 100);
        } catch {
            return (minor / 100).toFixed(whole ? 0 : 2);
        }
    },
});

/**
 * Menu page state: client-side search over the rendered items, the product
 * detail sheet (deep-linkable as #i-{publicId}), the bottom dock and the
 * order list sheet (#cart). No API calls.
 *
 * Every full-screen layer (category, product sheet, order list) is a history
 * entry, so the phone's back button closes the top layer instead of leaving
 * the menu; the on-screen back buttons go through history.back() for the
 * same reason. Jumps between categories in list mode replace the hash, so
 * they never pile up entries.
 *
 * With `tiles` (layout option category_layout = tiles) the page starts with
 * category cards; openCategory() shows one category (#c{id}). A search
 * always looks through every category.
 */
Alpine.data('menuPage', (tiles = false) => ({
    query: '',
    open: null,
    tiles,
    cat: null,
    pushed: false,
    layerPushed: false,
    scrolled: false,
    toast: null,
    toastTimer: null,
    cartEnabled: false,
    homeAfterPop: false,

    init() {
        const cartKey = this.$root.dataset.cart;

        if (cartKey) {
            this.cartEnabled = true;
            this.$store.cart.load(cartKey, this.$root.dataset.currency);
        }

        const item = window.location.hash.match(ITEM_HASH);

        if (this.tiles) {
            const category = window.location.hash.match(CATEGORY_HASH);
            this.cat = category
                ? category[1]
                : (item && document.getElementById(`row-${item[1]}`)?.closest('section[id]')?.id) || null;
        }

        if (item && hasItem(item[1])) {
            // Deep link to a product: put the menu underneath it in history,
            // so "back" shows the menu instead of leaving the page.
            history.replaceState(null, '', this.baseUrl());
            this.show(item[1]);
        } else if (window.location.hash === CART_HASH) {
            history.replaceState(null, '', this.baseUrl());
        }

        window.addEventListener('popstate', () => this.onHistory());
        window.addEventListener('keydown', (event) => this.onKey(event));

        let ticking = false;
        window.addEventListener('scroll', () => {
            if (!ticking) {
                ticking = true;
                window.requestAnimationFrame(() => {
                    this.onScroll();
                    ticking = false;
                });
            }
        }, { passive: true });
        this.onScroll();
    },

    // --- Layers and history ---------------------------------------------

    baseUrl() {
        return window.location.pathname + window.location.search + (this.cat ? `#${this.cat}` : '');
    },

    onHistory() {
        const hash = window.location.hash;
        const item = hash.match(ITEM_HASH);

        // Forward navigation onto a layer (the menu is the entry beneath it).
        if (item && hasItem(item[1])) {
            if (this.open !== item[1]) {
                this.openLayer(item[1]);
            }

            this.layerPushed = true;

            return;
        }

        if (hash === CART_HASH && this.cartEnabled) {
            this.openLayer(CART);
            this.layerPushed = true;

            return;
        }

        if (this.open !== null) {
            this.closeLayer();
        }

        if (this.tiles) {
            this.syncCategory();
        }
    },

    onKey(event) {
        if (event.key !== 'Escape') {
            return;
        }

        if (this.open !== null) {
            this.close();
        } else if (this.cat !== null) {
            this.closeCategory();
        } else if (this.query !== '') {
            this.query = '';
        }
    },

    openLayer(id) {
        this.open = id;
        document.documentElement.classList.add('menu-lock');
        this.$nextTick(() => this.$refs[`dlg-${id}`]?.focus());
    },

    closeLayer() {
        const id = this.open;
        this.open = null;
        this.layerPushed = false;
        document.documentElement.classList.remove('menu-lock');
        this.$nextTick(() => (id === CART ? document.getElementById('menu-cart-button') : document.getElementById(`row-${id}`))?.focus({ preventScroll: true }));
    },

    show(id) {
        if (this.open === id) {
            return;
        }

        const replace = this.open !== null;
        this.openLayer(id);
        this.pushLayer(`#i-${id}`, replace);
    },

    showCart() {
        if (!this.cartEnabled) {
            return;
        }

        const replace = this.open !== null;
        this.openLayer(CART);
        this.pushLayer(CART_HASH, replace);
    },

    pushLayer(hash, replace) {
        if (replace && this.layerPushed) {
            history.replaceState(null, '', hash);
        } else {
            history.pushState(null, '', hash);
            this.layerPushed = true;
        }
    },

    close() {
        if (this.open === null) {
            return;
        }

        if (this.layerPushed) {
            history.back();

            return;
        }

        this.closeLayer();
        history.replaceState(null, '', this.baseUrl());
    },

    // --- Categories -----------------------------------------------------

    visible(el) {
        if (this.query.trim() !== '') {
            return this.matches(el.dataset.search);
        }

        return !this.tiles || this.cat === el.id;
    },

    // List mode: scroll to a category without adding a history entry.
    jump(anchor) {
        const section = document.getElementById(anchor);

        if (!section) {
            return;
        }

        history.replaceState(null, '', `#${anchor}`);
        section.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'start' });
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

        const previous = this.cat;
        this.cat = null;
        history.replaceState(null, '', this.baseUrl());
        this.leaveCategory(previous);
    },

    syncCategory() {
        const match = window.location.hash.match(CATEGORY_HASH);
        const previous = this.cat;

        this.cat = match ? match[1] : null;

        if (this.cat === null && previous !== null) {
            this.leaveCategory(previous);
        }
    },

    leaveCategory(previous) {
        this.cat = null;
        this.pushed = false;

        if (this.homeAfterPop) {
            this.homeAfterPop = false;
            this.toTop();

            return;
        }

        this.$nextTick(() => {
            const tile = document.querySelector(`[data-tile="${previous}"]`);
            tile?.scrollIntoView({ block: 'center' });
            tile?.focus({ preventScroll: true });
        });
    },

    // The logo: on this page it resets the start view instead of reloading.
    goHome(event) {
        const target = new URL(event.currentTarget.href, window.location.href);

        if (target.pathname !== window.location.pathname) {
            return;
        }

        event.preventDefault();
        this.query = '';

        if (this.cat !== null && this.pushed) {
            this.homeAfterPop = true;
            history.back();

            return;
        }

        this.cat = null;
        this.pushed = false;
        history.replaceState(null, '', window.location.pathname + window.location.search);
        this.toTop();
    },

    // --- Dock -----------------------------------------------------------

    onScroll() {
        this.scrolled = window.scrollY > DOCK_OFFSET;
    },

    clearSearch() {
        this.query = '';
        this.toTop();
    },

    toTop() {
        window.scrollTo({ top: 0, behavior: reducedMotion() ? 'auto' : 'smooth' });
    },

    // --- Order list -----------------------------------------------------

    notify(message, undo = false) {
        window.clearTimeout(this.toastTimer);
        this.toast = { message, undo };
        this.toastTimer = window.setTimeout(() => {
            this.toast = null;
            this.$store.cart.undo = null;
        }, undo ? 6000 : 2400);
    },

    ordered(message) {
        this.$store.cart.clear();
        this.close();
        this.notify(message, true);
    },

    undoOrdered() {
        this.$store.cart.restore();
        window.clearTimeout(this.toastTimer);
        this.toast = null;
    },

    matches(text) {
        const query = this.query.trim().toLocaleLowerCase(document.documentElement.lang || undefined);

        return query === '' || (text || '').includes(query);
    },
}));

/**
 * Choices inside one product sheet: the variant, the extras per group and the
 * quantity. Without a payload (order list off) the extras stay display-only
 * choices, as before.
 *
 * @param {{id: string, name: string, price: ?number, variants: Array, groups: Array}|null} item
 */
Alpine.data('menuItem', (item = null) => ({
    item,
    variant: null,
    picked: {},
    qty: 1,
    missing: null,

    init() {
        const variants = (this.item?.variants || []).filter((variant) => variant.available);
        this.variant = (variants.find((variant) => variant.default) || variants[0])?.id || null;
    },

    pick(group, id, single, max = null) {
        const current = this.picked[group] || [];

        if (single) {
            this.picked[group] = current.includes(id) ? [] : [id];
        } else if (current.includes(id)) {
            this.picked[group] = current.filter((picked) => picked !== id);
        } else if (max === null || current.length < max) {
            this.picked[group] = [...current, id];
        }

        if (this.missing === group && this.groupComplete(group)) {
            this.missing = null;
        }
    },

    isPicked(group, id) {
        return (this.picked[group] || []).includes(id);
    },

    groupComplete(index) {
        return (this.picked[index] || []).length >= (this.item?.groups[index]?.min || 0);
    },

    get unit() {
        if (!this.item) {
            return null;
        }

        const variant = this.item.variants.find((candidate) => candidate.id === this.variant);
        const base = variant ? variant.price : this.item.price;

        if (base === null || base === undefined) {
            return null;
        }

        return this.item.groups.reduce((sum, group, index) => sum + group.extras
            .filter((extra) => this.isPicked(index, extra.id))
            .reduce((groupSum, extra) => groupSum + extra.delta, 0), base);
    },

    add(message) {
        if (!this.item) {
            return;
        }

        const missing = this.item.groups.findIndex((group, index) => !this.groupComplete(index));

        if (missing !== -1) {
            this.missing = missing;
            this.$el.closest('[role="dialog"]')?.querySelector(`[data-group="${missing}"]`)?.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'center' });

            return;
        }

        const variant = this.item.variants.find((candidate) => candidate.id === this.variant) || null;
        const extras = this.item.groups.flatMap((group, index) => group.extras.filter((extra) => this.isPicked(index, extra.id)));

        this.$store.cart.add({
            key: [this.item.id, variant?.id || '', ...extras.map((extra) => extra.id).sort()].join('|'),
            name: this.item.name,
            variant: variant?.name || null,
            extras: extras.map((extra) => extra.name),
            unit: this.unit,
            qty: this.qty,
        });

        this.qty = 1;
        this.close();
        this.notify(message);
    },
}));

startAlpine();
