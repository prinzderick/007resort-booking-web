import { $, $$ } from './util.js';

// Money as integer kobo (BigInt): never floating point.
const naira = (kobo) => {
    const whole = (kobo / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const frac = (kobo % 100n).toString().padStart(2, '0');
    return '₦' + whole + (frac === '00' ? '' : '.' + frac);
};

export function initBooking() {
    // Hold countdown. The server sends seconds remaining (no client-clock trust);
    // at zero we reload so the server renders the authoritative "expired" state.
    $$('[data-countdown]').forEach((el) => {
        let left = parseInt(el.dataset.countdown, 10);
        const announce = $('[data-countdown-announce]');
        const tick = () => {
            el.textContent = `${String(Math.floor(left / 60)).padStart(2, '0')}:${String(left % 60).padStart(2, '0')}`;
            if (announce && [60, 30, 10].includes(left)) announce.textContent = `${left} seconds left to pay`;
            if (left <= 0) { clearInterval(timer); window.setTimeout(() => window.location.reload(), 800); }
            left -= 1;
        };
        const timer = window.setInterval(tick, 1000);
        tick();
    });

    // Quantity steppers (- / +) around a number input.
    $$('[data-qty]').forEach((box) => {
        const input = $('input', box);
        const step = (d) => {
            const min = parseInt(input.min || '0', 10), max = parseInt(input.max || '999', 10);
            input.value = Math.max(min, Math.min(max, (parseInt(input.value || '0', 10) || 0) + d));
            input.dispatchEvent(new Event('input', { bubbles: true }));
        };
        $('[data-qty-dec]', box)?.addEventListener('click', () => step(-1));
        $('[data-qty-inc]', box)?.addEventListener('click', () => step(1));
    });

    // Pool ticket estimate.
    $$('[data-ticket-estimate]').forEach((form) => {
        const out = $('[data-estimate]', form);
        const inputs = $$('input[data-unit-minor]', form);
        const update = () => {
            let total = 0n;
            inputs.forEach((i) => { total += BigInt(Math.max(0, parseInt(i.value || '0', 10) || 0)) * BigInt(i.dataset.unitMinor || '0'); });
            out.textContent = naira(total);
        };
        inputs.forEach((i) => i.addEventListener('input', update));
        update();
    });

    // Slot picker: the sticky summary follows the selected slot and quantity.
    $$('[data-slot-form]').forEach((form) => {
        const time = $('[data-sum-time]', form), total = $('[data-sum-total]', form), submit = $('[data-sum-submit]', form) || $('button[type=submit]', form);
        const qty = $('input[name=quantity]', form);
        let price = null;
        const render = () => {
            if (submit) submit.setAttribute('aria-disabled', price === null ? 'true' : 'false');
            if (submit) submit.classList.toggle('is-busy', price === null && form.dataset.submitted !== '1');
            if (!total) return;
            const unit = price ?? BigInt(total.dataset.unit || '0');
            const q = BigInt(Math.max(1, parseInt(qty?.value || '1', 10) || 1));
            total.textContent = naira(unit * (total.dataset.qtySync === '1' ? q : 1n));
        };
        $$('input[name=slot]', form).forEach((r) => r.addEventListener('change', () => {
            price = BigInt(r.dataset.price || '0');
            if (time) time.textContent = r.dataset.time;
            render();
        }));
        qty?.addEventListener('input', render);
        form.addEventListener('submit', (e) => { if (price === null && $('input[name=slot]:not([disabled])', form)) { e.preventDefault(); time?.focus?.(); $('.slot input:not([disabled])', form)?.focus(); } });
        const pre = $('input[name=slot]:checked', form);
        if (pre) pre.dispatchEvent(new Event('change'));
        else render();
    });

    // keep the active day visible in the date strip (scroll the strip only, never the page)
    $$('[data-datestrip]').forEach((strip) => {
        const el = $('[aria-current]', strip);
        if (el) strip.scrollLeft = Math.max(0, el.offsetLeft - strip.offsetLeft - (strip.clientWidth - el.offsetWidth) / 2);
    });
}
