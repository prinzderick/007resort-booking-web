// Light progressive enhancement. The site works without JavaScript.

// Hold countdown. The server sends seconds remaining (no client-clock trust);
// at zero we reload so the server renders the authoritative "expired" state.
document.querySelectorAll('[data-countdown]').forEach((el) => {
    let left = parseInt(el.dataset.countdown, 10);
    const announce = document.querySelector('[data-countdown-announce]');
    const tick = () => {
        const m = String(Math.floor(left / 60)).padStart(2, '0');
        const s = String(left % 60).padStart(2, '0');
        el.textContent = `${m}:${s}`;
        if (announce && [60, 30, 10].includes(left)) {
            announce.textContent = `${left} seconds left to pay`;
        }
        if (left <= 0) {
            clearInterval(timer);
            window.setTimeout(() => window.location.reload(), 800);
        }
        left -= 1;
    };
    const timer = window.setInterval(tick, 1000);
    tick();
});

// Double-click safety: lock submit buttons once a form is submitted. The server
// also de-duplicates (per-form submission id + API Idempotency-Key).
document.querySelectorAll('form[data-once]').forEach((form) => {
    form.addEventListener('submit', (e) => {
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            e.preventDefault();
            return;
        }
        if (form.dataset.submitted === '1') {
            e.preventDefault();
            return;
        }
        form.dataset.submitted = '1';
        window.setTimeout(() => {
            form.querySelectorAll('button[type=submit], button:not([type])').forEach((b) => {
                if (b.dataset.busy) {
                    b.textContent = b.dataset.busy;
                }
                b.setAttribute('aria-disabled', 'true');
                b.classList.add('opacity-60', 'pointer-events-none');
            });
        }, 0);
    });
});
// Back/forward cache: re-enable forms.
window.addEventListener('pageshow', (e) => {
    if (e.persisted) {
        document.querySelectorAll('form[data-once]').forEach((f) => {
            f.dataset.submitted = '0';
            f.querySelectorAll('.pointer-events-none').forEach((b) => {
                b.classList.remove('opacity-60', 'pointer-events-none');
                b.removeAttribute('aria-disabled');
            });
        });
    }
});

// Pool ticket estimate, in integer kobo (BigInt): never floating point money.
document.querySelectorAll('[data-ticket-estimate]').forEach((form) => {
    const out = form.querySelector('[data-estimate]');
    const inputs = form.querySelectorAll('input[data-unit-minor]');
    const update = () => {
        let total = 0n;
        inputs.forEach((i) => {
            const q = BigInt(Math.max(0, parseInt(i.value || '0', 10) || 0));
            total += q * BigInt(i.dataset.unitMinor || '0');
        });
        const whole = (total / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        const frac = (total % 100n).toString().padStart(2, '0');
        out.textContent = '₦' + whole + (frac === '00' ? '' : '.' + frac);
    };
    inputs.forEach((i) => i.addEventListener('input', update));
    update();
});

document.querySelectorAll('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));
