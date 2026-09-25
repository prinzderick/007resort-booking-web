import { $, $$, store } from './util.js';

// Order / ticket page enhancements: save QR as an image, copy link, share, dismissible account prompt.
const PROMPT_KEY = 'r007.acct.dismissed';

const toPng = (img) => new Promise((resolve, reject) => {
    const pad = 48, size = 1024;
    const c = document.createElement('canvas');
    c.width = c.height = size + pad * 2;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, c.width, c.height);
    const i = new Image();
    i.onload = () => { ctx.imageSmoothingEnabled = false; ctx.drawImage(i, pad, pad, size, size); c.toBlob((b) => (b ? resolve(b) : reject(new Error('png'))), 'image/png'); };
    i.onerror = reject;
    i.src = img.src;
});

const download = (blob, name) => {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.setTimeout(() => URL.revokeObjectURL(a.href), 4000);
};

export function initOrders() {
    $('[data-focus-me]')?.focus({ preventScroll: false });

    $$('[data-save-qr]').forEach((btn) => {
        btn.hidden = false;
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const img = $('img[data-qr]', btn.closest('.tkt'));
            if (!img) return;
            try { download(await toPng(img), btn.dataset.name + '.png'); }
            catch { const svg = await (await fetch(img.src)).blob().catch(() => null); if (svg) download(svg, btn.dataset.name + '.svg'); }
        });
    });

    $$('[data-copy]').forEach((btn) => {
        btn.hidden = false;
        const src = $('[data-copy-src]', btn.parentElement);
        btn.addEventListener('click', async () => {
            const text = src.value;
            try { await navigator.clipboard.writeText(text); } catch { src.select(); document.execCommand?.('copy'); }
            const label = btn.textContent;
            btn.textContent = 'Copied';
            window.setTimeout(() => { btn.textContent = label; }, 2000);
        });
    });

    $$('[data-share]').forEach((btn) => {
        if (!navigator.share) return;
        btn.hidden = false;
        // Shares a public link only, never the private booking link.
        btn.addEventListener('click', () => navigator.share({ title: btn.dataset.title, text: btn.dataset.title, url: btn.dataset.url }).catch(() => {}));
    });

    $$('[data-acct-prompt]').forEach((box) => {
        if (store.get(PROMPT_KEY) === '1') { box.hidden = true; return; }
        const x = $('[data-acct-dismiss]', box);
        if (x) { x.hidden = false; x.addEventListener('click', () => { store.set(PROMPT_KEY, '1'); box.hidden = true; }); }
    });

    // close the header account menu on outside click / Escape
    $$('[data-acct-menu]').forEach((d) => {
        document.addEventListener('click', (e) => { if (d.open && !d.contains(e.target)) d.open = false; });
        d.addEventListener('keydown', (e) => { if (e.key === 'Escape') { d.open = false; $('summary', d)?.focus(); } });
    });
}
