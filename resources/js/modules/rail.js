import { $, $$, raf, reduceMotion } from './util.js';

const chev = (dir) => `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="${dir < 0 ? 'M15 5l-7 7 7 7' : 'M9 5l7 7-7 7'}"/></svg>`;

// Scroll-snap rails: native touch/trackpad scrolling, plus buttons, dots, keyboard and optional pausable autoplay.
export function initRails() {
    $$('[data-rail]').forEach((rail) => {
        const track = $('[data-rail-track]', rail);
        const nav = $('[data-rail-nav]', rail);
        if (!track || !nav) return;
        const items = Array.from(track.children);
        if (items.length < 2) return;

        const prev = document.createElement('button');
        const next = document.createElement('button');
        prev.type = next.type = 'button';
        prev.innerHTML = chev(-1); next.innerHTML = chev(1);
        prev.setAttribute('aria-label', 'Previous'); next.setAttribute('aria-label', 'Next');
        const ctrl = document.createElement('div');
        ctrl.className = 'rail-ctrl';
        const dots = document.createElement('div');
        dots.className = 'dots';
        dots.setAttribute('role', 'group'); dots.setAttribute('aria-label', 'Choose slide');
        const dotEls = items.length <= 10 ? items.map((_, i) => {
            const b = document.createElement('button');
            b.type = 'button'; b.setAttribute('aria-label', `Go to slide ${i + 1}`);
            b.addEventListener('click', () => goTo(i));
            dots.appendChild(b);
            return b;
        }) : [];
        let play = null;
        if (rail.dataset.autoplay && !reduceMotion()) {
            play = document.createElement('button');
            play.type = 'button'; play.setAttribute('aria-label', 'Pause autoplay'); play.dataset.state = 'on';
            play.innerHTML = '<svg viewBox="0 0 12 12" aria-hidden="true" width="14" height="14" fill="currentColor"><rect x="1" y="1" width="3.5" height="10"/><rect x="7.5" y="1" width="3.5" height="10"/></svg>';
            ctrl.appendChild(play);
        }
        ctrl.append(prev, next);
        nav.style.cssText = 'display:flex;justify-content:space-between;align-items:center;gap:16px;margin-top:6px';
        nav.append(dots, ctrl);

        const left = (el) => el.offsetLeft - track.offsetLeft - parseFloat(getComputedStyle(track).paddingLeft || 0);
        const current = () => {
            const x = track.scrollLeft;
            let best = 0, d = Infinity;
            items.forEach((el, i) => { const dd = Math.abs(left(el) - x); if (dd < d) { d = dd; best = i; } });
            return best;
        };
        function goTo(i) {
            i = Math.max(0, Math.min(items.length - 1, i));
            track.scrollTo({ left: left(items[i]), behavior: reduceMotion() ? 'auto' : 'smooth' });
        }
        const update = raf(() => {
            const i = current();
            const atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
            prev.disabled = track.scrollLeft <= 4;
            next.disabled = atEnd;
            dotEls.forEach((d, k) => (k === i || (atEnd && k === items.length - 1) ? d.setAttribute('aria-current', 'true') : d.removeAttribute('aria-current')));
            if (dotEls.length && atEnd) dotEls.forEach((d, k) => k !== items.length - 1 && d.removeAttribute('aria-current'));
        });
        prev.addEventListener('click', () => goTo(current() - 1));
        next.addEventListener('click', () => goTo(current() + 1));
        track.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        track.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight') { e.preventDefault(); goTo(current() + 1); }
            if (e.key === 'ArrowLeft') { e.preventDefault(); goTo(current() - 1); }
            if (e.key === 'Home') { e.preventDefault(); goTo(0); }
            if (e.key === 'End') { e.preventDefault(); goTo(items.length - 1); }
        });
        update();

        // autoplay (pausable; stops on interaction/hover/focus and when off-screen)
        if (play) {
            let timer = null, paused = false, hovered = false, onscreen = true;
            const step = () => {
                const atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
                goTo(atEnd ? 0 : current() + 1);
            };
            const sync = () => {
                clearInterval(timer);
                if (!paused && !hovered && onscreen) timer = setInterval(step, parseInt(rail.dataset.autoplay, 10) || 7000);
            };
            play.addEventListener('click', () => {
                paused = !paused;
                play.dataset.state = paused ? 'off' : 'on';
                play.setAttribute('aria-label', paused ? 'Start autoplay' : 'Pause autoplay');
                play.innerHTML = paused
                    ? '<svg viewBox="0 0 12 12" aria-hidden="true" width="14" height="14" fill="currentColor"><path d="M2 1l9 5-9 5z"/></svg>'
                    : '<svg viewBox="0 0 12 12" aria-hidden="true" width="14" height="14" fill="currentColor"><rect x="1" y="1" width="3.5" height="10"/><rect x="7.5" y="1" width="3.5" height="10"/></svg>';
                sync();
            });
            rail.addEventListener('pointerenter', () => { hovered = true; sync(); });
            rail.addEventListener('pointerleave', () => { hovered = false; sync(); });
            rail.addEventListener('focusin', () => { hovered = true; sync(); });
            rail.addEventListener('focusout', () => { hovered = false; sync(); });
            rail.addEventListener('touchstart', () => { paused = true; sync(); }, { passive: true });
            new IntersectionObserver((es) => { onscreen = es[0].isIntersecting; sync(); }).observe(rail);
            sync();
        }
    });
}
