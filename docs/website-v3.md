# SK Arabians website, version 3 ("Maison")

Version 3 is a complete redesign of the public website's presentation layer. The data layer, the routes, the
content switches in the staff system (Website → Homepage & texts, Horses on website, Show results) and the
bilingual URL structure are unchanged, so the site keeps working from the same database and the Owner's existing
settings. Nothing in the Management System (`/portal`) changes.

## What changed

| Area | Version 2 ("Heritage Noir") | Version 3 ("Maison") |
|------|-----------------------------|----------------------|
| Mood | Dark glass panels everywhere, aurora, gold dust, cursor ring, spotlight, 3D tilts | Calm alternation of ivory "paper" and midnight chapters; photography leads; gold only as hairlines and small caps |
| Type | Marcellus + Inter | Marcellus titles, **Cormorant Garamond italic** for editorial lines, numerals and quotes (new, self-hosted), Inter body; Amiri + IBM Plex Sans Arabic for Arabic |
| Hero | Slideshow with glass pill, counters in glass cards | Full-bleed photograph, title low on the frame, the horse named in a capsule, a ledger strip (horses · titles · latest title) beneath |
| Homepage | Sections | Numbered chapters (01 The collection … 10 Location), each with an index, title and lead |
| Horse card | Glass "collector card" with spotlight and tilt | A print: 4:5 photo, name, other-language name, sire × dam, small-caps meta, hairline that turns gold |
| Results | Medal bento cards | Ledgers: rows with hairlines, the year in italic numerals |
| Header | Glass capsule | Transparent over the hero, frosted navy bar when scrolled; full-screen numbered menu on phones |
| Footer | Glass strip + wordmark | Address block with coordinates and live Doha time, explore and contact columns, outlined wordmark |
| Motion | Many effects | Word-mask rises, clip-path photo unveils, light parallax, counters, pinned film-strip gallery (GSAP, computers only); everything off under reduced motion |

## The richness layer (`site-ornament.css`)

The first cut of version 3 was judged "too simple and dry". A panel of six design lenses (art director, motion,
heritage, typography, equestrian, phone) proposed enrichments and two judges merged them into a ranked plan; the
plan was built in two passes on top of the calm base, all of it in one additive stylesheet
(`public/assets/css/site-ornament.css`, loaded last) plus small view changes. Three motifs only: the pointed arch,
the eight-pointed star (rosette, threshold, seal) and gold foil. Everything reads without JavaScript and under
reduced motion.

| Piece | What it is | Where |
|-------|------------|-------|
| The lit window and the gate | A gold hairline frame draws itself inside the hero; a pointed arch in gold hairline rises around the title after the doors part (computers); folios in the frame's corners; the stud's Arabic name above the eyebrow (the Latin name on the Arabic edition) | `home.php` hero, `.hero-frame`, `.hero-arch`, `.hero-folio`, `.hero-arabic` |
| The cover line | The hero's gold key word drops onto its own line and grows until it fills the column edge to edge (`--chars` + container units), in foil | `split_title(…, cover: true)`, `.w-key` |
| Doors preloader, sticky hero sink, ticker of titles, seal | First-visit doors part onto the hero; the hero stays while the ledger covers it; the latest titles pass along the hero's edge; the seal (two rings of text, Latin and Arabic from the language files) turns slowly in the corner, in the footer and on monogram tiles | `layouts/site.php`, `.preloader-leaf`, `.hero-sink`, `.ticker`, `#seal` |
| Chapter thresholds, cartouche index, finial, rail | A gilt hairline with the rosette on a disc opens every chapter; the index sits in a slim gold cartouche; a palmette finial ends every lead; a fixed rail of chapter numerals in the left gutter (wide screens) | `.threshold`, `.chapter-index`, `--finial`, `.chapter-rail` |
| Calligraphic ghosts | The chapter's name in the other language stands behind its title: Arabic in Amiri on the English edition, English in Marcellus capitals on the Arabic one; a horse's other name behind its facts. Always real strings from `lang/*.php` or the horse's registered name | `t_other()`, `[data-ghost]` |
| The collection as a folio | The first horse of each category is a plate twice the width, its name set large across the photograph's lower edge; every card's photograph under a shallow arch with a gold rim (a second arch draws on hover); the title count at the keystone; sire × dam in Cormorant italic with a rosette for the cross; horses without a photograph get a bookplate (inner arch hairline, the seal turned a little, monogram in foil, the other-language name, the category up the side) | `horse_card.php`, `.hgrid.editorial`, `.hcard-frame`, `.hcard-arch`, `.hcard-ph.plate` |
| The Portrait Wall | The featured champion full-bleed to the screen edge and taller than the screen under the tall arch, sticky while the record scrolls beside him; the name at cover size crosses the photograph's edge; his name ghosted in outline behind the record | `.feature.bleed` (≥1200px) |
| The judges' sheet | The scorecard as a certificate: double rule, rosettes at the corners, the seal stamped at an angle; each criterion's average as a gold bar with a reed-pen rhombus and the judges' range behind it, captioned in the other language; the full card adds the matrix (a column per judge, the name on hover, every 20 marked, the panel average) and the line show people repeat ("4 of 6 judges gave 20 for Head") | `partials/scorecard.php`, `.scorecard.cert`, `.sc-matrix`, `.sc-perfect` |
| Honours like a stud book | Double rule under table heads, the show in Marcellus, scores in Cormorant figures, placings as ordinals, dotted leaders running to the show | `ordinal()`, `.leader`, `.results-table` |
| Book typography | One word of each title in Cormorant italic against the Marcellus roman (Amiri regular against Amiri bold on the Arabic edition); the pull quote with its mark hung in the margin and an attribution; a gold drop cap and a small-caps lede opening every story (the Arabic edition opens with a gold star); foil that catches the light as the page scrolls where the browser drives animations from scroll | `split_title(…, italic)`, `lede()`, `.w-it`, `.quote-by`, `.dcap`, `@supports (animation-timeline: view())` |
| The profile masthead | The horse's name as a nameplate in Marcellus capitals fitted to the band, the Arabic name at parity on the same baseline, a registry line ("Stallion · Grey · 2017 · by X out of Y"); the facts as a passport page with a double rule and the mark as a watermark; the pedigree as a shajara with a root plate (portrait, names, registry line), generation heads I–V, sires and dams marked, gold connectors and the lit line back to the horse | `page_hero.php` (`nameplate`, `registry`), `.facts`, `pedigree.php` (`root`) |
| Inner title bands | A photograph behind every band (the collection's first photo, the lead story, the story plate), the page's name ghosted in the other language, the arch standing on the end side | `page_hero.php` (`bg`, `ghost`, `.page-arch`) |
| Champions page | The hall as navy plaques with the name in foil and the laurel, the most decorated horse on a double plaque | `.champ-card` |
| The phone | The hero as a framed print with the headline on its lower edge; a swipe brings the next horse and a gold progress line fills over each slide; the rows snap card by card with the centred horse at full size, a counter and a growing gold line; the concierge dock (chapter line · WhatsApp · Enquire) rises once the title band has gone and sinks over the form and the footer; every touch has a press | `.hero-progress`, `[data-parade]`, `.row-foot`, `.dock` |
| Viewer, menu, cursor, view transitions | Spotlight viewing room; the phone menu opens from the thumb; the cursor ring says what is under it (computers); a card's photograph travels to its page where the browser supports cross-document view transitions | `.lb`, `.nav-panel`, `.cursor-label`, `::view-transition-group` |

Dropped on the judges' advice after being tried: a giant turning khatam star behind chapters, woven bands between
chapters, Arabic-Indic figures, swaying medals, a radar chart on the scorecard, running heads and page folios.

## Files

| File | Role |
|------|------|
| `docs/brand-guidelines.md` | Brand source of truth (colours, type, voice, imagery, components) |
| `public/assets/css/tokens.css` | Primitive tokens (shared with the staff system); v3 adds `--font-accent` |
| `public/assets/css/brand.css` | Shared scales and motifs; v3 adds `--t-accent`, `--t-numeral` |
| `public/assets/css/site.css` | Website v3: semantic tokens, base, components, header, menu, footer |
| `public/assets/css/site-pages.css` | Website v3: pages, motion, reduced motion, print |
| `public/assets/css/site-ornament.css` | Website v3: the richness layer (see above), additive and loaded last |
| `public/assets/img/ornaments.svg` | Hand-drawn gold line work: rosette, star, divider, corner scroll, medal, laurel, khatam, finial, the arch clip paths |
| `app/Views/site/partials/scorecard.php` | The judges' sheet (bars, matrix, the perfect-marks line) |
| `public/assets/js/site-head.js`, `site.js` | Website behaviour (menu, slideshow, tabs, reveals, viewer, share, local time) |
| `public/assets/fonts/CormorantGaramond-*.woff2` | New accent face (SIL OFL, Latin subset) |
| `public/assets/img/icons.svg` | Adds arrow-up-right, arrow-down, play, star, compass, minus |
| `app/Views/layouts/site.php` | The website frame |
| `app/Views/site/*.php`, `app/Views/site/partials/*.php` | Every public page and partial |
| `lang/en.php`, `lang/ar.php` | New keys: site.visit, site.doha_qatar, site.local_time, site.plan_visit, site.est, site.by, site.out_of, site.discover, site.score_total, site.judges_n, site.judges_perfect, site.score_range, site.placed, site.crit_*, site.cursor_*, site.colophon |
| `app/helpers.php` | `split_title()` gains the italic word and the cover line; new `lede()`, `t_other()`, `ordinal()` |
| `app/Services/SiteData.php` | `results()` carries `judge_card` when the column exists (update 018) |
| `tools/preview/` | Database-free preview (fixtures from the horse cards), screenshot and static-export scripts |

## Token architecture (design-system skill)

1. **Primitive**: `tokens.css` (`--c-navy`, `--c-gold`, `--c-ivory`, `--font-display`, `--font-accent`, `--s-*`, `--r-*`, `--dur-*`).
2. **Semantic**: the `:root` block at the top of `site.css` (`--bg`, `--text`, `--text-2`, `--rule`, `--rule-gold`, `--gold-text`, `--tile`, `--section-y`, `--gutter`); `.night`, `.deep`, the header, hero and footer redefine them for the midnight family, so every component works on both surfaces without its own colour rules.
3. **Component**: buttons, cards, ledgers, tabs, forms read only the semantic layer.

The design-system validator (`node .claude/skills/design-system/scripts/validate-tokens.cjs --dir public/assets/css`) reports only intentional literal pixel values in v3 (media-query breakpoints, control heights, hairlines).

## Deployment (cPanel, as before)

1. Upload the contents of `public/` into `public_html/` and the rest of the project into the app folder outside `public_html` (the front controller finds `../app/bootstrap.php`).
2. No database migration is needed for version 3.
3. Clear the browser cache is not needed: every asset address carries a content hash.

## Preview without a database

See `tools/preview/README.md`. The preview renders the real views with the horses, parents, shows and results of
`database/data/horse_cards_2026-10-07.json`; the photographs there are placeholders from Unsplash for the review build
only and are not part of the repository.
