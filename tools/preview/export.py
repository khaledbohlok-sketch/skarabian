#!/usr/bin/env python3
"""Static export of the database-free preview: every public page of the running preview server, with links and asset
addresses rewritten so the pages work as plain files next to each other (for a shared review build).

    python3 tools/preview/export.py http://127.0.0.1:8080 /path/to/out /path/to/photos

Writes <out>/<page>.html (en.html, en-horses.html, en-horses-d-samed.html …), <out>/assets/…, <out>/photos/…
"""
import json
import os
import re
import shutil
import subprocess
import sys
import urllib.request

base, out, photos = sys.argv[1], sys.argv[2], sys.argv[3]
root = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
os.makedirs(out, exist_ok=True)

# the pages: the menu pages, every horse, every news item, in both languages
fx = json.loads(subprocess.check_output(['php', '-r', 'echo json_encode(require "' + root + '/tools/preview/fixtures.php");']))
paths = []
for lang in ('en', 'ar'):
    paths += ['/' + lang, '/' + lang + '/horses', '/' + lang + '/champions', '/' + lang + '/news', '/' + lang + '/about', '/' + lang + '/contact']
    paths += ['/' + lang + '/horses/' + h['slug'] for h in fx['horses'].values()]
    paths += ['/' + lang + '/news/' + n['slug'] for n in fx['news']]


def page_name(path: str) -> str:
    return path.strip('/').replace('/', '-') + '.html'


def rewrite(html: str) -> str:
    html = html.replace(base, '')
    # assets and photos
    html = re.sub(r'(["(])/assets/([^"?)#]+)(\?v=[0-9a-f]+)?', r'\1assets/\2', html)
    html = re.sub(r'"/preview-media/([^"]+)"', r'"photos/\1"', html)
    html = html.replace('"/favicon.ico"', '"assets/img/icon-192.png"')
    # pages: /en, /en/horses?category=mare, /ar/horses/d-samed#results …
    def link(m):
        path, query, frag = m.group(1), m.group(2) or '', m.group(3) or ''
        if path.startswith('/portal') or path.startswith('/verify'):
            return 'href="#"'
        return 'href="' + page_name(path) + frag + '"'
    html = re.sub(r'href="(/(?:en|ar)(?:/[^"?#]*)?|/portal[^"]*|/verify[^"]*)(\?[^"#]*)?(#[^"]*)?"', link, html)
    html = re.sub(r'action="/(?:en|ar)/inquiry"', 'action="#" onsubmit="return false"', html)
    html = re.sub(r'<div class="map-frame">.*?</div>', '', html, flags=re.S)
    html = re.sub(r'<link rel="(canonical|alternate)"[^>]*>\n?', '', html)
    return html


for p in paths:
    with urllib.request.urlopen(base + p) as r:
        html = r.read().decode('utf-8')
    with open(os.path.join(out, page_name(p)), 'w', encoding='utf-8') as f:
        f.write(rewrite(html))
    print(p, '->', page_name(p))

# assets: the stylesheets, scripts, fonts and images the pages ask for; the photos
for sub in ('css', 'js', 'fonts', 'img', 'vendor/gsap'):
    dst = os.path.join(out, 'assets', sub)
    os.makedirs(dst, exist_ok=True)
    for f in os.listdir(os.path.join(root, 'public/assets', sub)):
        src = os.path.join(root, 'public/assets', sub, f)
        if os.path.isfile(src) and (sub != 'css' or f in ('tokens.css', 'brand.css', 'fonts.css', 'site.css', 'site-pages.css')) and (sub != 'js' or f in ('site.js', 'site-head.js')):
            shutil.copy(src, dst)
os.makedirs(os.path.join(out, 'photos'), exist_ok=True)
for f in os.listdir(photos):
    if f.endswith('.jpg'):
        shutil.copy(os.path.join(photos, f), os.path.join(out, 'photos', f))
print('exported', len(paths), 'pages to', out)
