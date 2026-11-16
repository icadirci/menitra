// Scroll-triggered entrances for the public site, using animate.css classes.
//
// Markup: data-animate="fadeInUp|fadeIn" and an optional data-animate-delay (ms).
// Only elements that start below the fold are hidden and animated, so nothing
// already painted flickers; without JS, or with reduced motion, content simply shows.

const ANIMATIONS = ['fadeIn', 'fadeInUp'];
const MAX_DELAY_MS = 600;

export function startReveal() {
    const elements = document.querySelectorAll('[data-animate]');

    if (
        elements.length === 0 ||
        !('IntersectionObserver' in window) ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
        return;
    }

    const reveal = (element) => {
        const name = ANIMATIONS.includes(element.dataset.animate) ? element.dataset.animate : 'fadeInUp';
        const delay = Math.min(Number.parseInt(element.dataset.animateDelay ?? '0', 10) || 0, MAX_DELAY_MS);

        if (delay > 0) {
            element.style.animationDelay = `${delay}ms`;
        }

        element.addEventListener(
            'animationend',
            () => {
                // Drop the classes so the final transform doesn't linger on the element.
                element.classList.remove('animated', name);
                element.style.animationDelay = '';
            },
            { once: true },
        );

        element.classList.remove('reveal-pending');
        element.classList.add('animated', name);
    };

    const observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    observer.unobserve(entry.target);
                    reveal(entry.target);
                }
            }
        },
        { rootMargin: '0px 0px -10% 0px', threshold: 0.1 },
    );

    const fold = window.innerHeight;

    for (const element of elements) {
        if (element.getBoundingClientRect().top < fold) {
            continue;
        }

        element.classList.add('reveal-pending');
        observer.observe(element);
    }
}
