import { $, $$, store } from './util.js';

// Guest checkout "Your details" card. Progressive enhancement only: the form works (and validates
// on the server) without any of this.
const KEY = 'r007.guest.v1'; // name, email, phone: a per-viewer convenience, cleared by the visitor on request.

// Same rules as App\Support\Phone (PHP is the authority; this is only a preview).
export function normalizePhone(input) {
    const raw = String(input || '').trim();
    if (!raw || /[^\d\s()+.\-]/.test(raw)) return null;
    const intl = raw.startsWith('+') || raw.startsWith('00');
    let d = raw.replace(/\D/g, '');
    if (raw.startsWith('00')) d = d.slice(2);
    if (intl && !d.startsWith('234')) return /^[1-9]\d{7,14}$/.test(d) ? '+' + d : null;
    if (d.startsWith('234')) { d = d.slice(3); if (d.startsWith('0')) d = d.slice(1); }
    else if (d.startsWith('0')) d = d.slice(1);
    return /^[789]\d{9}$/.test(d) ? '+234' + d : null;
}
const pretty = (e164) => (/^\+234(\d{3})(\d{3})(\d{4})$/.exec(e164 || '') || []).slice(1).reduce((a, p) => a + ' ' + p, '+234') || e164;

const RULES = {
    name: (v) => (v.trim().length >= 2 && /\p{L}/u.test(v) ? '' : 'Please enter your full name.'),
    email: (v) => (/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()) ? '' : 'That email does not look right. Check for typos, for example name@gmail.com.'),
    phone: (v) => (normalizePhone(v) ? '' : 'Enter a Nigerian mobile number like 0803 123 4567 or +234 803 123 4567.'),
};

export function initCheckout() {
    // Focus the error summary the server rendered so screen-reader and keyboard users land on it.
    $('[data-error-summary]')?.focus();

    $$('form[data-guest-form]').forEach((form) => {
        const card = $('[data-guest-card]', form);
        if (!card) return;
        form.noValidate = true; // we show our own messages; the server still validates everything
        const fields = { name: $('[data-guest=name]', form), email: $('[data-guest=email]', form), phone: $('[data-guest=phone]', form) };
        const preview = $('[data-phone-preview]', form);
        const phoneBox = $('[data-phone]', form);
        const clearBtn = $('[data-guest-clear]', form);

        const setError = (key, msg) => {
            const el = fields[key];
            if (!el) return;
            let out = document.getElementById(el.id + '-error');
            if (!msg) { out?.remove(); el.removeAttribute('aria-invalid'); return; }
            if (!out) {
                out = document.createElement('p');
                out.id = el.id + '-error';
                out.className = 'err';
                el.closest('.field').appendChild(out);
                el.setAttribute('aria-describedby', ((el.getAttribute('aria-describedby') || '') + ' ' + out.id).trim());
            }
            out.textContent = msg;
            el.setAttribute('aria-invalid', 'true');
        };
        const check = (key) => { const m = RULES[key](fields[key].value); setError(key, m); return m; };

        // phone: live preview of what we will use, and hide the +234 chip when they type their own prefix
        const syncPhone = () => {
            const v = fields.phone.value;
            const n = normalizePhone(v);
            phoneBox?.classList.toggle('has-own-prefix', v.trim().startsWith('+') || v.trim().startsWith('00'));
            if (preview) preview.textContent = n ? 'We’ll use ' + pretty(n) + '.' : '';
        };
        fields.phone?.addEventListener('input', () => { syncPhone(); if (fields.phone.getAttribute('aria-invalid')) check('phone'); });

        Object.keys(fields).forEach((k) => {
            fields[k]?.addEventListener('blur', () => { if (fields[k].value !== '' || fields[k].getAttribute('aria-invalid')) check(k); });
            fields[k]?.addEventListener('input', () => { if (fields[k].getAttribute('aria-invalid')) check(k); });
        });

        // remembered details (localStorage, this device only)
        const saved = (() => { try { return JSON.parse(store.get(KEY) || 'null'); } catch { return null; } })();
        if (saved && typeof saved === 'object') {
            Object.keys(fields).forEach((k) => { if (fields[k] && !fields[k].value && typeof saved[k] === 'string') fields[k].value = saved[k]; });
        }
        const persist = () => {
            const v = { name: fields.name?.value.trim() || '', email: fields.email?.value.trim() || '', phone: fields.phone?.value.trim() || '' };
            if (v.name || v.email || v.phone) store.set(KEY, JSON.stringify(v));
            if (clearBtn) clearBtn.hidden = false;
        };
        Object.values(fields).forEach((el) => el?.addEventListener('change', persist));
        if (clearBtn) {
            clearBtn.hidden = !(saved && (saved.name || saved.email || saved.phone));
            clearBtn.addEventListener('click', () => {
                store.remove(KEY);
                Object.values(fields).forEach((el) => { if (el) { el.value = ''; el.removeAttribute('aria-invalid'); } });
                $$('.err', card).forEach((e) => e.remove());
                clearBtn.hidden = true;
                syncPhone();
                fields.name?.focus();
            });
        }
        syncPhone();

        // Validate before submit; on problems build an error summary and move focus to it.
        form.addEventListener('submit', (e) => {
            const bad = Object.keys(fields).filter((k) => fields[k] && check(k));
            if (bad.length === 0) { persist(); return; }
            e.preventDefault();
            e.stopImmediatePropagation(); // keep the double-submit lock (forms.js) from arming
            let box = $('[data-error-summary]');
            if (!box) {
                box = document.createElement('div');
                box.className = 'notice notice--error co-errors';
                box.setAttribute('role', 'alert');
                box.tabIndex = -1;
                box.dataset.errorSummary = '';
                form.parentElement.insertBefore(box, form);
            }
            box.innerHTML = '';
            const wrap = document.createElement('div');
            const h = document.createElement('p'); h.style.fontWeight = '600'; h.textContent = 'Please fix ' + (bad.length === 1 ? 'this' : 'these') + ' to continue';
            const ul = document.createElement('ul');
            bad.forEach((k) => {
                const li = document.createElement('li'); const a = document.createElement('a');
                a.href = '#' + fields[k].id; a.textContent = RULES[k](fields[k].value);
                a.addEventListener('click', (ev) => { ev.preventDefault(); fields[k].focus(); });
                li.appendChild(a); ul.appendChild(li);
            });
            wrap.append(h, ul); box.appendChild(wrap);
            box.focus();
        }, true);
    });
}
