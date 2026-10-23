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

## The identity, in short

The site re-enacts the stud's own emblem: a black horse against a setting sun between two gates. Every colour is
taken from it — the wine ground (`--c-wine*`), the gold ring (`--c-gold*`), the lapis of the gate (`--c-lapis*`),
the sunset (`--c-ember`, `--c-sun`) — and three motifs carry it through the page:

- **The sun** — a disc behind the hero headline that sinks as you scroll (CSS scroll timeline, JS fallback), then
  returns as the gold and silver medals, the knob on the language switch and the amber "details coming soon" dot.
- **The gate** — hairline pylons that draw themselves in beside the hero copy and close the page in the footer seal.
- **The step** — the ziggurat profile on every chapter threshold, the merlon caps on chapter indices and the stepped
  base of the lapis facility plates.

Type: Marcellus (display), Cormorant Garamond italic (accents), Inter (text); Amiri and IBM Plex Sans Arabic for the
Arabic edition, which is set as its own book (mirrored layout, re-set headlines, LTR islands for numbers and handles).

Preview: https://claude.ai/artifact/5GX4YRHkXBc4SuwJwSLuku (English; the Arabic edition is behind the العربية switch).
