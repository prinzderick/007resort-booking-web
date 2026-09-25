import { $$ } from './util.js';

// Social sign-in: a "connecting" state on provider buttons (they are plain links, so it works without JS too),
// initials fallback when an avatar image fails, and opening the set-password panel from its link.
export function initSocial() {
    const reset = () => $$('[data-social].is-loading').forEach((a) => {
        a.classList.remove('is-loading');
        a.removeAttribute('aria-busy');
        a.removeAttribute('aria-disabled');
        const t = a.querySelector('.btn-social__text');
        if (t && a.dataset.label) t.textContent = a.dataset.label;
    });

    $$('[data-social]').forEach((a) => {
        a.addEventListener('click', (e) => {
            if (a.classList.contains('is-loading')) { e.preventDefault(); return; }
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return; // opening in a new tab: leave as is
            const t = a.querySelector('.btn-social__text');
            if (t) { a.dataset.label = t.textContent; t.textContent = `Connecting to ${a.dataset.provider || 'provider'}…`; }
            a.classList.add('is-loading');
            a.setAttribute('aria-busy', 'true');
            a.setAttribute('aria-disabled', 'true');
        });
    });
    // Back button from the provider: bfcache would otherwise show the spinner forever.
    window.addEventListener('pageshow', (e) => { if (e.persisted) reset(); });

    $$('[data-avatar] img').forEach((img) => {
        const drop = () => img.remove();
        img.addEventListener('error', drop, { once: true });
        if (img.complete && img.naturalWidth === 0) drop();
    });

    $$('[data-open]').forEach((a) => a.addEventListener('click', () => {
        const d = document.getElementById(a.dataset.open);
        if (d && d.tagName === 'DETAILS') { d.open = true; d.querySelector('input')?.focus(); }
    }));
}
