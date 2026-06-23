import { animate, stagger } from 'motion';

/**
 * Declarative motion layer, scanned once on load. Pages opt in via data-* attributes
 * (data-motion="fade-up", data-tilt, data-count-up) rather than each view shipping its
 * own animation code - same convention as the existing data-sidebar-open/data-chart
 * attributes in app.js. Bundled with esbuild (see package.json build:js) since
 * motion's ESM build has bare-specifier imports (motion-dom, motion-utils) a browser
 * can't resolve without either a bundler or an import map.
 */
(() => {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduceMotion) return;

    const EASE = [0.2, 0.8, 0.2, 1];

    // Entrance reveals - data-motion="fade-up" (optionally data-motion-stagger to animate children).
    // Runs once on page load rather than waiting for scroll-into-view: this is a traditional
    // multi-page app, not a scrolling landing page, and several pages (e.g. the dashboard) have
    // fade-up sections that sit below the fold on a normal-height screen - gating on inView left
    // those permanently invisible for anyone who never scrolled, which read as a broken page
    // rather than an animation. The index-based delay below still gives the page a brief top-to-
    // bottom cascade instead of every section popping in at once.
    document.querySelectorAll('[data-motion="fade-up"]').forEach((el, index) => {
        const staggered = el.hasAttribute('data-motion-stagger');
        const targets = staggered ? Array.from(el.children) : [el];
        if (targets.length === 0) return;

        animate(targets, { opacity: [0, 1], y: [16, 0] }, {
            duration: 0.5,
            delay: staggered ? stagger(0.06, { startDelay: Math.min(index * 0.04, 0.16) }) : Math.min(index * 0.04, 0.16),
            easing: EASE,
        });
    });

    // 3D pointer tilt - data-tilt. Mouse/pen only; no-ops on touch (no hover concept there).
    // Driven by direct style.transform writes (cheap, immediate) plus the CSS `transition:
    // transform` on [data-tilt] (see app.css) to smooth between them - calling animate() on
    // every pointermove instead would stack a new tween on top of whichever one from the
    // previous move hadn't finished yet, which is what made this feel jittery rather than smooth.
    document.querySelectorAll('[data-tilt]').forEach((card) => {
        const maxDeg = 7;

        card.addEventListener('pointermove', (event) => {
            if (event.pointerType === 'touch') return;
            const rect = card.getBoundingClientRect();
            const px = (event.clientX - rect.left) / rect.width;
            const py = (event.clientY - rect.top) / rect.height;
            const rotateX = (0.5 - py) * maxDeg * 2;
            const rotateY = (px - 0.5) * maxDeg * 2;
            card.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.015, 1.015, 1.015)`;
        });
        card.addEventListener('pointerleave', () => { card.style.transform = ''; });
    });

    // Animated number counters - data-count-up data-count-value="1234.56" [data-count-decimals].
    // Runs on load, same reasoning as the fade-up reveals above (no scroll-gating).
    document.querySelectorAll('[data-count-up]').forEach((el) => {
        const target = parseFloat(el.getAttribute('data-count-value') || '0');
        const decimals = parseInt(el.getAttribute('data-count-decimals') || '0', 10);
        const finalText = el.textContent;
        if (!Number.isFinite(target)) return;

        animate(0, target, {
            duration: 1.1,
            easing: EASE,
            onUpdate(latest) {
                el.textContent = latest.toLocaleString(undefined, {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals,
                });
            },
            // Snaps to the exact server-rendered string (currency symbol, locale grouping) once
            // the count finishes, since replicating that formatting in JS isn't worth the risk.
            onComplete() { el.textContent = finalText; },
        });
    });

    // Mobile sidebar backdrop fade-in. The sidebar panel itself already CSS-transitions its own
    // transform (see the `transition duration-200` class in layouts/app.php). Only the open
    // direction gets a JS fade here - app.js's closeSidebar() adds the `hidden` utility class
    // (display: none) synchronously before this would run, which a fade-out can't outrun, so a
    // close-fade would be a no-op rather than a real enhancement.
    const backdrop = document.querySelector('#sidebar-backdrop');
    if (backdrop) {
        document.querySelectorAll('[data-sidebar-open]').forEach((btn) => btn.addEventListener('click', () => {
            animate(backdrop, { opacity: [0, 1] }, { duration: 0.2 });
        }));
    }

    // Flash messages - enter animation for both, auto-dismiss only for success (errors stay put).
    document.querySelectorAll('[data-flash]').forEach((el) => {
        animate(el, { opacity: [0, 1], y: [-8, 0] }, { duration: 0.35, easing: EASE });
        if (el.getAttribute('data-flash') === 'success') {
            setTimeout(() => {
                animate(el, { opacity: [1, 0], y: [-8, -8] }, { duration: 0.3 }).finished.then(() => el.remove());
            }, 4000);
        }
    });

    // Guest split-pane entrance (login/forgot/reset).
    const guestAside = document.querySelector('[data-motion-guest-aside]');
    const guestForm = document.querySelector('[data-motion-guest-form]');
    if (guestAside && guestForm) {
        animate(guestAside, { opacity: [0, 1], x: [-12, 0] }, { duration: 0.5, easing: EASE });
        animate(guestForm, { opacity: [0, 1], x: [12, 0] }, { duration: 0.5, delay: 0.08, easing: EASE });
    }
})();
