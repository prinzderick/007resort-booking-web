import { $$, raf, reduceMotion } from './util.js';

// transform-only parallax on [data-parallax="0.2"] wrappers (the image inside is oversized via CSS).
export function initParallax() {
    if (reduceMotion()) return;
    const els = $$('[data-parallax]');
    if (!els.length) return;
    const vh = () => window.innerHeight;
    const visible = new Set();
    const io = new IntersectionObserver((es) => es.forEach((e) => (e.isIntersecting ? visible.add(e.target) : visible.delete(e.target))), { rootMargin: '10% 0px' });
    els.forEach((el) => io.observe(el));
    const update = raf(() => {
        visible.forEach((el) => {
            const f = parseFloat(el.dataset.parallax) || 0.15;
            const parent = el.parentElement.getBoundingClientRect();
            const offset = (parent.top + parent.height / 2 - vh() / 2) * -f;
            el.style.transform = `translate3d(0, ${offset.toFixed(1)}px, 0)`;
        });
    });
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
}
