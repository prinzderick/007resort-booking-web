import { $$, reduceMotion } from './util.js';

const format = (n, raw) => {
    const dec = (raw.split('.')[1] || '').length;
    const s = n.toFixed(dec);
    return raw.includes(',') ? Number(s).toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec }) : s;
};

function countUp(el) {
    const target = parseFloat(el.dataset.count);
    const raw = el.dataset.raw || String(target);
    if (Number.isNaN(target) || reduceMotion()) { el.textContent = raw; return; }
    const t0 = performance.now(), dur = 1400;
    const tick = (t) => {
        const p = Math.min(1, (t - t0) / dur);
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = format(target * eased, raw);
        if (p < 1) requestAnimationFrame(tick); else el.textContent = raw;
    };
    requestAnimationFrame(tick);
}

export function initReveal() {
    const items = $$('.reveal');
    const counters = $$('[data-count]');
    if (!('IntersectionObserver' in window) || reduceMotion()) {
        items.forEach((el) => el.classList.add('in'));
        counters.forEach((el) => { el.textContent = el.dataset.raw || el.dataset.count; });
        return;
    }
    // zero the counters only once JS is confirmed to run
    counters.forEach((el) => { el.textContent = '0'; });
    const io = new IntersectionObserver((entries) => {
        entries.forEach((en) => {
            if (!en.isIntersecting) return;
            en.target.classList.add('in');
            io.unobserve(en.target);
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    items.forEach((el) => io.observe(el));

    const io2 = new IntersectionObserver((entries) => {
        entries.forEach((en) => {
            if (!en.isIntersecting) return;
            countUp(en.target);
            io2.unobserve(en.target);
        });
    }, { threshold: 0.5 });
    counters.forEach((el) => io2.observe(el));
}
