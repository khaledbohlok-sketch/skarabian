# Babel Stud — website redesign

A static, bilingual redesign of https://babelstud-jo.com (Babel Stud, Arabian horse training centre, Amman).

- `index.html` — English edition (LTR); `ar/index.html` — Arabic edition (RTL). Both are generated.
- `content/strings.json` — every string of the site in English and Arabic (the source of truth for text).
- `content/photos.md` — the photographs, what each shows, where it is used.
- `tools/build.py` — renders both editions from one markup: `python3 tools/build.py`.
- `tools/serve.sh` — a local preview on http://127.0.0.1:8090/ (English) and `/ar/` (Arabic).
- `assets/css/babel.css` — tokens (colours, faces, scales, motifs), base, components, the page, motion, phone, print.
- `assets/css/fonts.css`, `assets/fonts/` — self-hosted faces (SIL OFL): Marcellus, Cormorant Garamond, Inter, Amiri, IBM Plex Sans Arabic.
- `assets/js/site-head.js` (runs in the head: js / reduce / intro flags), `assets/js/site.js` (menu, header, reveals, counters, hero lean, gallery viewer, swipe counter, chapter line, phone dock).
- `assets/img/` — the stud's own photographs (`photos/`, ≤1800px and ≤720px thumbs), the logo and emblem, a favicon.

Deploy: upload the folder as is to any static host (the current host serves plain HTML). To change text, edit
`content/strings.json` and run the build; to add a photograph, drop it in `assets/img/photos/` in both sizes and
reference it in `tools/build.py`.
