/**
 * Google Ads tag with cookie consent (KVKK). Only pages that render
 * <x-google-tag /> (marketing, /register, /email/verify) have the config
 * element; everywhere else this does nothing.
 *
 * Nothing is loaded from Google before the visitor accepts: the choice is
 * stored in localStorage for a year, a rejection keeps the tag off. A sign-up
 * conversion the server flagged is sent once, right away when consent exists,
 * or later in this tab if the visitor accepts afterwards.
 */
const STORAGE_KEY = 'menitra.consent';
const PENDING_KEY = 'menitra.pending-conversion';
const MAX_AGE_MS = 365 * 24 * 60 * 60 * 1000;

function read(storage, key) {
    try {
        return storage.getItem(key);
    } catch {
        return null;
    }
}

function write(storage, key, value) {
    try {
        value === null ? storage.removeItem(key) : storage.setItem(key, value);
    } catch {
        // Storage blocked: the banner simply asks again next time.
    }
}

function storedChoice() {
    try {
        const { choice, at } = JSON.parse(read(localStorage, STORAGE_KEY) ?? '');

        return ['granted', 'denied'].includes(choice) && Date.now() - at < MAX_AGE_MS ? choice : null;
    } catch {
        return null;
    }
}

export function startGoogleTag() {
    const config = document.querySelector('[data-google-tag]');
    const banner = document.querySelector('[data-consent-banner]');

    if (!config || !/^(AW|G|GT)-[A-Z0-9]+$/.test(config.dataset.googleTag ?? '')) {
        return;
    }

    const tagId = config.dataset.googleTag;
    let loaded = false;

    if (config.dataset.signupConversion) {
        write(sessionStorage, PENDING_KEY, config.dataset.signupConversion);
    }

    window.dataLayer = window.dataLayer || [];
    window.gtag = function gtag() {
        window.dataLayer.push(arguments);
    };
    window.gtag('consent', 'default', {
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
        analytics_storage: 'denied',
    });

    const load = () => {
        window.gtag('consent', 'update', { ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted' });

        if (loaded) {
            return;
        }

        loaded = true;
        window.gtag('js', new Date());

        const script = document.createElement('script');
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(tagId)}`;
        document.head.appendChild(script);

        const pending = read(sessionStorage, PENDING_KEY);

        if (pending) {
            window.gtag('event', 'conversion', { send_to: pending });
            write(sessionStorage, PENDING_KEY, null);
        }
    };

    const choose = (choice) => {
        write(localStorage, STORAGE_KEY, JSON.stringify({ choice, at: Date.now() }));
        banner?.setAttribute('hidden', '');

        if (choice === 'granted') {
            load();
        } else if (loaded) {
            window.gtag('consent', 'update', { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
        }
    };

    banner?.querySelectorAll('[data-consent]').forEach((button) => {
        button.addEventListener('click', () => choose(button.dataset.consent));
    });

    // "Cookie settings" links (footer) reopen the banner.
    document.querySelectorAll('[data-consent-open]').forEach((button) => {
        button.removeAttribute('hidden');
        button.addEventListener('click', () => banner?.removeAttribute('hidden'));
    });

    const choice = storedChoice();

    if (choice === 'granted') {
        load();
    } else if (choice === null) {
        banner?.removeAttribute('hidden');
    }
}
