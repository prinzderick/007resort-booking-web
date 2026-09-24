import { $, $$, raf, reduceMotion, finePointer } from './util.js';

export function initMisc() {
    // magnetic buttons (fine pointers only)
    if (finePointer() && !reduceMotion()) {
        $$('[data-magnetic]').forEach((b) => {
            b.addEventListener('pointermove', (e) => {
                const r = b.getBoundingClientRect();
                const x = (e.clientX - (r.left + r.width / 2)) * 0.18, y = (e.clientY - (r.top + r.height / 2)) * 0.28;
                b.style.transform = `translate3d(${x.toFixed(1)}px, ${y.toFixed(1)}px, 0)`;
            });
            b.addEventListener('pointerleave', () => { b.style.transform = ''; });
        });
    }

    // reading progress on blog posts
    const bar = $('[data-reading-bar]'), target = $('[data-reading-target]');
    if (bar && target) {
        const upd = raf(() => {
            const r = target.getBoundingClientRect();
            const total = r.height - window.innerHeight * 0.6;
            const p = Math.max(0, Math.min(1, (-r.top + window.innerHeight * 0.2) / Math.max(1, total)));
            bar.style.transform = `scaleX(${p.toFixed(3)})`;
        });
        window.addEventListener('scroll', upd, { passive: true });
        window.addEventListener('resize', upd);
        upd();
    }

    // copy link
    $$('[data-copy-link]').forEach((b) => b.addEventListener('click', async () => {
        const label = b.textContent;
        try { await navigator.clipboard.writeText(window.location.href.split('#')[0]); b.textContent = 'Link copied'; }
        catch { window.prompt('Copy this link', window.location.href); }
        setTimeout(() => { b.textContent = label; }, 2000);
    }));

    // sticky mobile CTA: appears after the first screen, hides near the footer or the booking bar
    const cta = $('[data-mobile-cta]');
    if (cta) {
        const foot = $('[data-footer]'), bookbar = $('[data-bookbar]');
        let footIn = false, barIn = false;
        const upd = () => cta.classList.toggle('is-on', window.scrollY > 360 && !footIn && !barIn);
        if ('IntersectionObserver' in window) {
            if (foot) new IntersectionObserver((e) => { footIn = e[0].isIntersecting; upd(); }).observe(foot);
            if (bookbar) new IntersectionObserver((e) => { barIn = e[0].isIntersecting; upd(); }).observe(bookbar);
        }
        window.addEventListener('scroll', raf(upd), { passive: true });
        upd();
    }
}
