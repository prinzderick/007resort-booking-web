import { $$ } from './util.js';

// Double-click safety: lock submit buttons once a form is submitted. The server also de-duplicates
// (per-form submission id + API Idempotency-Key).
export function initForms() {
    $$('form[data-once]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { e.preventDefault(); return; }
            if (form.dataset.submitted === '1') { e.preventDefault(); return; }
            form.dataset.submitted = '1';
            window.setTimeout(() => {
                $$('button[type=submit], button:not([type])', form).forEach((b) => {
                    if (b.dataset.busy) b.textContent = b.dataset.busy;
                    b.setAttribute('aria-disabled', 'true');
                    b.classList.add('is-busy');
                });
            }, 0);
        });
    });
    // Back/forward cache: re-enable forms.
    window.addEventListener('pageshow', (e) => {
        if (!e.persisted) return;
        $$('form[data-once]').forEach((f) => {
            f.dataset.submitted = '0';
            $$('.is-busy', f).forEach((b) => { b.classList.remove('is-busy'); b.removeAttribute('aria-disabled'); });
        });
    });
    $$('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));
}
