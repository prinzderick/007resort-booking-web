import { $, $$, raf, trapFocus } from './util.js';

export function initHeader() {
    const header = $('[data-header]');
    if (!header) return;

    // shrink + theme change on scroll
    const onScroll = raf(() => header.classList.toggle('is-scrolled', window.scrollY > 24));
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // announcement bar: dismissible, remembered per message
    const ann = $('[data-ann]', header);
    if (ann) {
        const key = 'r007.ann.' + ann.dataset.ann;
        try { if (window.localStorage.getItem(key)) ann.remove(); } catch { /* ignore */ }
        $('[data-ann-close]', ann)?.addEventListener('click', () => {
            try { window.localStorage.setItem(key, '1'); } catch { /* ignore */ }
            ann.remove();
        });
    }

    // mobile drawer (bottom sheet)
    const drawer = $('[data-drawer]');
    const burger = $('[data-burger]');
    if (!drawer || !burger) return;
    let opener = null;
    const main = $('main'), footer = $('[data-footer]');
    const setOpen = (open) => {
        drawer.classList.toggle('is-open', open);
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        burger.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        document.documentElement.style.overflow = open ? 'hidden' : '';
        [main, footer].forEach((el) => el && (open ? el.setAttribute('inert', '') : el.removeAttribute('inert')));
        if (open) { opener = document.activeElement; $('[data-drawer-close]', drawer)?.focus(); }
        else if (opener) { opener.focus(); }
    };
    burger.addEventListener('click', () => setOpen(!drawer.classList.contains('is-open')));
    $('[data-drawer-close]', drawer)?.addEventListener('click', () => setOpen(false));
    drawer.addEventListener('click', (e) => { if (e.target === drawer) setOpen(false); });
    drawer.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); trapFocus(drawer, e); });
    $$('a', drawer).forEach((a) => a.addEventListener('click', () => setOpen(false)));
    window.matchMedia('(min-width: 1081px)').addEventListener('change', (e) => e.matches && setOpen(false));
}
