import { $, $$, store } from './util.js';

const POP_KEY = 'r007.subpop';

// Subscribe forms: progressive enhancement of a normal POST (works without JS). Double opt-in happens by email.
export function initSubscribe() {
    $$('[data-subscribe-form]').forEach((form) => {
        form.addEventListener('submit', async (e) => {
            const email = $('input[type=email]', form);
            const msg = $('[data-subscribe-msg]', form);
            if (!window.fetch) return;
            e.preventDefault();
            if (!email.checkValidity()) { msg.textContent = 'Please enter a valid email address.'; msg.className = 'sub-msg bad'; email.focus(); return; }
            const btn = $('button[type=submit]', form);
            btn.disabled = true;
            msg.textContent = 'Sending...'; msg.className = 'sub-msg';
            try {
                const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                const data = await res.json().catch(() => ({}));
                msg.textContent = data.message || (res.ok ? 'Check your inbox to confirm.' : 'Something went wrong. Please try again.');
                msg.className = 'sub-msg ' + (data.ok ? 'ok' : 'bad');
                if (data.ok) { email.value = ''; store.set(POP_KEY, String(Date.now() + 365 * 864e5)); document.dispatchEvent(new CustomEvent('r007:subscribed')); }
            } catch {
                msg.textContent = 'We could not reach the server. Please try again.'; msg.className = 'sub-msg bad';
            } finally { btn.disabled = false; }
        });
    });

    // Optional slide-in after real engagement (scrolled > 50% and 20s on the page); dismissal is remembered for 30 days.
    const pop = $('[data-subpop]');
    if (!pop) return;
    const until = parseInt(store.get(POP_KEY) || '0', 10);
    if (until > Date.now()) return;
    let timeOk = false, scrollOk = false, shown = false;
    const maybe = () => {
        if (shown || !timeOk || !scrollOk) return;
        if (document.querySelector('.drawer.is-open, .lb.is-open')) return;
        shown = true;
        pop.classList.add('is-open'); pop.setAttribute('aria-hidden', 'false');
    };
    const hide = (days) => {
        pop.classList.remove('is-open'); pop.setAttribute('aria-hidden', 'true');
        store.set(POP_KEY, String(Date.now() + days * 864e5));
    };
    setTimeout(() => { timeOk = true; maybe(); }, 20000);
    window.addEventListener('scroll', () => {
        const h = document.documentElement;
        if ((h.scrollTop + window.innerHeight) / h.scrollHeight > 0.5) { scrollOk = true; maybe(); }
    }, { passive: true });
    $('[data-subpop-close]', pop)?.addEventListener('click', () => hide(30));
    pop.addEventListener('keydown', (e) => { if (e.key === 'Escape') hide(30); });
    document.addEventListener('r007:subscribed', () => setTimeout(() => { pop.classList.remove('is-open'); pop.setAttribute('aria-hidden', 'true'); }, 2500));
}
