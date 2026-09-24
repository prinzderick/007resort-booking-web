import { $, $$, raf, reduceMotion, finePointer } from './util.js';

// A media variant that 404s must never leave a broken-image icon: fall back to the original URL once, then hide the image
// (the wrapper keeps the dominant-colour placeholder). Error events do not bubble, hence the capture listener.
function initImageFallback() {
    document.addEventListener('error', (e) => {
        const img = e.target;
        if (!(img instanceof HTMLImageElement)) return;
        const fb = img.dataset.fb;
        if (fb && !img.dataset.fbTried && img.currentSrc !== fb) {
            img.dataset.fbTried = '1';
            img.removeAttribute('srcset');
            img.removeAttribute('sizes');
            img.src = fb;
        } else {
            img.style.visibility = 'hidden';
        }
    }, true);
    // images that failed before this script ran
    $$('img').forEach((img) => { if (img.complete && img.naturalWidth === 0 && img.currentSrc) img.dispatchEvent(new Event('error')); });
}

export function initMisc() {
    initImageFallback();

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
