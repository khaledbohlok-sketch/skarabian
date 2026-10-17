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

## Files

| File | Role |
|------|------|
| `docs/brand-guidelines.md` | Brand source of truth (colours, type, voice, imagery, components) |
| `public/assets/css/tokens.css` | Primitive tokens (shared with the staff system); v3 adds `--font-accent` |
| `public/assets/css/brand.css` | Shared scales and motifs; v3 adds `--t-accent`, `--t-numeral` |
| `public/assets/css/site.css` | Website v3: semantic tokens, base, components, header, menu, footer |
| `public/assets/css/site-pages.css` | Website v3: pages, motion, reduced motion, print |
| `public/assets/js/site-head.js`, `site.js` | Website behaviour (menu, slideshow, tabs, reveals, viewer, share, local time) |
| `public/assets/fonts/CormorantGaramond-*.woff2` | New accent face (SIL OFL, Latin subset) |
| `public/assets/img/icons.svg` | Adds arrow-up-right, arrow-down, play, star, compass, minus |
| `app/Views/layouts/site.php` | The website frame |
| `app/Views/site/*.php`, `app/Views/site/partials/*.php` | Every public page and partial |
| `lang/en.php`, `lang/ar.php` | New keys: site.visit, site.doha_qatar, site.local_time, site.plan_visit, site.est, site.by, site.out_of, site.discover |
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
