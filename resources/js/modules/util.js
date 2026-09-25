export const $ = (sel, root = document) => root.querySelector(sel);
export const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
export const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
export const finePointer = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;
export const store = {
    get(k) { try { return window.localStorage.getItem(k); } catch { return null; } },
    set(k, v) { try { window.localStorage.setItem(k, v); } catch { /* private mode */ } },
    remove(k) { try { window.localStorage.removeItem(k); } catch { /* private mode */ } },
};
export const raf = (fn) => {
    let queued = false;
    return (...a) => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => { queued = false; fn(...a); });
    };
};
export const focusable = (root) => $$('a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])', root).filter((el) => el.offsetParent !== null || el === document.activeElement);
export function trapFocus(container, e) {
    if (e.key !== 'Tab') return;
    const f = focusable(container);
    if (!f.length) return;
    const first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
}
