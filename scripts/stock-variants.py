#!/usr/bin/env python3
"""Dev/fixtures only: turn the stock photo batch (manifest.json + jpgs) into responsive webp variants under
public/stock/ and write resources/cms-fixtures/stock.json (the same `media` shape the CMS API returns).
Usage: scripts/stock-variants.py <dir-with-manifest.json>   (needs `cwebp`)
Production images come from CMS media URLs; nothing here is used when CMS_FIXTURES=false."""
import json, os, subprocess, sys

src = sys.argv[1]
root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
out = os.path.join(root, 'public', 'stock')
os.makedirs(out, exist_ok=True)
manifest = json.load(open(os.path.join(src, 'manifest.json')))
result = {}
credits = []
for m in manifest:
    if m['category'] == 'texture':
        continue
    key = os.path.splitext(m['file'])[0]
    key = key.split('-', 2)[0] + '-' + key.split('-', 2)[1]  # e.g. hero-01
    slug = os.path.splitext(m['file'])[0]
    variants = []
    for w in (480, 960, 1600):
        if w > m['width'] and variants:
            break
        fn = f"{slug}-{w}.webp"
        target = os.path.join(out, fn)
        if not os.path.exists(target):
            subprocess.run(['cwebp', '-quiet', '-q', '72', '-resize', str(w), '0', os.path.join(src, m['file']), '-o', target], check=True)
        variants.append({'width': min(w, m['width']), 'format': 'webp', 'url': f'/stock/{fn}'})
    h = round(m['height'] * variants[-1]['width'] / m['width'])
    result[key] = {
        'id': slug, 'url': variants[-1]['url'], 'alt': m['alt'], 'width': variants[-1]['width'], 'height': h, 'mimeType': 'image/webp',
        'variants': variants, 'dominantColor': m.get('dominantColor'), 'credit': 'Photo: ' + m['credit'] + ' / Unsplash',
        'tags': m.get('tags', []), 'category': m['category'],
    }
    credits.append(f"- {slug}: {m['credit']} ({m['sourceUrl']}), {m['license']}")
json.dump(result, open(os.path.join(root, 'resources', 'cms-fixtures', 'stock.json'), 'w'), indent=1)
open(os.path.join(out, 'CREDITS.md'), 'w').write(
    "# Stock photo credits (development fixtures only)\n\nFree-licence photos used only when CMS_FIXTURES=true. Production images come from CMS media.\n\n" + "\n".join(credits) + "\n")
print(len(result), 'images')
