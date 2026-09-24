import { $, $$, trapFocus } from './util.js';

// Gallery: album chips filter in place (real links still work without JS) + an accessible lightbox
// (keys, swipe, captions, focus trap) with deep links: /gallery#photo=<id>.
export function initGallery() {
    const root = $('[data-gallery]');
    if (!root) return;
    const figs = $$('[data-masonry] figure', root);
    const chips = $$('a[data-album]', root);

    const filter = (album, push) => {
        figs.forEach((f) => f.classList.toggle('is-hidden', album !== '' && f.dataset.album !== album));
        chips.forEach((c) => (c.dataset.album === album ? c.setAttribute('aria-current', 'true') : c.removeAttribute('aria-current')));
        if (push) {
            const u = new URL(window.location.href);
            album ? u.searchParams.set('album', album) : u.searchParams.delete('album');
            u.hash = '';
            history.replaceState(null, '', u);
        }
    };
    chips.forEach((c) => c.addEventListener('click', (e) => { e.preventDefault(); filter(c.dataset.album, true); }));

    const lb = $('[data-lightbox]');
    if (!lb) return;
    const img = $('[data-lb-img]', lb), cap = $('[data-lb-cap]', lb), count = $('[data-lb-count]', lb);
    let list = [], idx = 0, opener = null;
    const visible = () => $$('a[data-photo]', root).filter((a) => !a.closest('figure').classList.contains('is-hidden'));
    const render = () => {
        const a = list[idx];
        img.src = a.dataset.full;
        img.alt = a.dataset.caption || '';
        cap.textContent = [a.dataset.caption, a.dataset.credit].filter(Boolean).join('  ·  ');
        count.textContent = `${idx + 1} / ${list.length}`;
        history.replaceState(null, '', '#photo=' + a.dataset.photo);
        [list[idx + 1], list[idx - 1]].forEach((n) => { if (n) new Image().src = n.dataset.full; });
    };
    const open = (a) => {
        list = visible(); idx = Math.max(0, list.indexOf(a)); opener = document.activeElement;
        lb.classList.add('is-open'); lb.setAttribute('aria-hidden', 'false');
        document.documentElement.style.overflow = 'hidden';
        $('main')?.setAttribute('inert', '');
        render(); $('[data-lb-close]', lb).focus();
    };
    const close = () => {
        lb.classList.remove('is-open'); lb.setAttribute('aria-hidden', 'true');
        document.documentElement.style.overflow = '';
        $('main')?.removeAttribute('inert');
        history.replaceState(null, '', location.pathname + location.search);
        opener?.focus();
    };
    const go = (d) => { idx = (idx + d + list.length) % list.length; render(); };
    $$('a[data-photo]', root).forEach((a) => a.addEventListener('click', (e) => { e.preventDefault(); open(a); }));
    $('[data-lb-close]', lb).addEventListener('click', close);
    $('[data-lb-prev]', lb).addEventListener('click', () => go(-1));
    $('[data-lb-next]', lb).addEventListener('click', () => go(1));
    lb.addEventListener('click', (e) => { if (e.target === lb || e.target.classList.contains('lb-stage')) close(); });
    lb.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowRight') go(1);
        if (e.key === 'ArrowLeft') go(-1);
        trapFocus(lb, e);
    });
    let x0 = null;
    lb.addEventListener('pointerdown', (e) => { x0 = e.clientX; });
    lb.addEventListener('pointerup', (e) => {
        if (x0 === null) return;
        const dx = e.clientX - x0; x0 = null;
        if (Math.abs(dx) > 50) go(dx < 0 ? 1 : -1);
    });

    const fromHash = () => {
        const m = location.hash.match(/^#photo=(.+)$/);
        if (!m) return;
        const a = $$('a[data-photo]', root).find((x) => x.dataset.photo === decodeURIComponent(m[1]));
        if (a) { filter('', false); open(a); }
    };
    fromHash();
    window.addEventListener('hashchange', () => { if (!lb.classList.contains('is-open')) fromHash(); });
}
