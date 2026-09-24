import { $, $$, reduceMotion } from './util.js';

// Home hero: crossfading slides with Ken Burns (CSS), headline swap per slide, dots with progress, pause, swipe.
export function initHero() {
    const hero = $('[data-hero]');
    if (!hero) return;
    const slides = $$('[data-slide]', hero);
    const copies = $$('[data-slide-copy]', hero);
    const dots = $$('[data-hero-dot]', hero);
    if (slides.length < 2) return;
    const MS = 7000;
    hero.style.setProperty('--slide-ms', MS + 'ms');
    let i = 0, timer = null, paused = reduceMotion(), hover = false;
    const pauseBtn = $('[data-hero-pause]', hero);

    const show = (n) => {
        i = (n + slides.length) % slides.length;
        slides.forEach((s, k) => { s.classList.toggle('is-active', k === i); s.setAttribute('aria-hidden', k === i ? 'false' : 'true'); });
        copies.forEach((c, k) => {
            c.classList.toggle('is-active', k === i);
            c.setAttribute('aria-hidden', k === i ? 'false' : 'true');
            $$('a', c).forEach((a) => (k === i ? a.removeAttribute('tabindex') : a.setAttribute('tabindex', '-1')));
        });
        dots.forEach((d, k) => {
            d.classList.remove('is-active');
            if (k === i) { void d.offsetWidth; d.classList.add('is-active'); d.setAttribute('aria-current', 'true'); } else d.removeAttribute('aria-current');
        });
        schedule();
    };
    function schedule() {
        clearTimeout(timer);
        if (!paused && !hover && !document.hidden) timer = setTimeout(() => show(i + 1), MS);
    }
    dots.forEach((d, k) => d.addEventListener('click', () => show(k)));
    const setPaused = (p) => {
        paused = p;
        hero.classList.toggle('is-paused', p);
        if (pauseBtn) {
            pauseBtn.setAttribute('aria-label', p ? 'Play slideshow' : 'Pause slideshow');
            pauseBtn.innerHTML = p ? '<svg viewBox="0 0 12 12" aria-hidden="true"><path d="M2 1l9 5-9 5z"/></svg>' : '<svg viewBox="0 0 12 12" aria-hidden="true"><rect x="1" y="1" width="3.5" height="10"/><rect x="7.5" y="1" width="3.5" height="10"/></svg>';
        }
        schedule();
    };
    pauseBtn?.addEventListener('click', () => setPaused(!paused));
    hero.addEventListener('pointerenter', () => { hover = true; schedule(); });
    hero.addEventListener('pointerleave', () => { hover = false; schedule(); });
    document.addEventListener('visibilitychange', schedule);
    hero.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight') show(i + 1);
        if (e.key === 'ArrowLeft') show(i - 1);
    });

    // swipe
    let x0 = null;
    hero.addEventListener('pointerdown', (e) => { if (e.pointerType !== 'mouse') x0 = e.clientX; });
    hero.addEventListener('pointerup', (e) => {
        if (x0 === null) return;
        const dx = e.clientX - x0; x0 = null;
        if (Math.abs(dx) > 50) show(i + (dx < 0 ? 1 : -1));
    });
    if (paused) hero.classList.add('is-paused');
    if (paused && pauseBtn) setPaused(true);
    dots[0]?.classList.add('is-active');
    schedule();
}
