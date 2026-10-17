# SK Arabians — Brand Guidelines v3.0

> Last updated: 2026-10-17
> Status: Active (website version 3)
> Source of truth for the public website (`public/assets/css/tokens.css`, `brand.css`, `site.css`) and the Management System.

## Quick Reference

| Element | Value |
|---------|-------|
| Primary Colour | Navy `#213562` (deep `#0f1a33`, ink `#13213f`) |
| Accent Colour | Gold `#b08d57` (light `#c9a46a`, soft `#e3cfa8`) |
| Paper | Ivory `#f8f5ef` |
| Display Font | Marcellus (Latin) · Amiri (Arabic) |
| Accent Font | Cormorant Garamond italic (editorial lines, numerals, pull quotes) |
| Text Font | Inter (Latin) · IBM Plex Sans Arabic (Arabic) |
| Voice | Quiet, assured, precise, hospitable |

---

## 1. Colour Palette

One brand, one family: navy and gold, decided October 2026 so that the website and the staff system feel like one world. Every tint, glow and gradient is a `color-mix()` of these values. No other hue.

### Primary (Navy)

| Name | Hex | Usage |
|------|-----|-------|
| Navy deep | `#0f1a33` | Page background of the dark chapters, hero veils, footer |
| Navy ink | `#13213f` | Cards and panels on dark chapters |
| Navy | `#213562` | Edges, rules, the portal's brand colour, theme-color on light pages |
| Navy 2 | `#2c4478` | Lifted surfaces, hover edges |

### Accent (Gold)

| Name | Hex | Usage |
|------|-----|-------|
| Gold | `#b08d57` | Hairlines, eyebrows, small-caps labels, medals |
| Gold 2 | `#c9a46a` | Links on dark, metallic gradient mid-tone |
| Gold soft | `#e3cfa8` | Metallic highlight, text on navy when gold must be readable |

The metallic gold (`--metal`) is a brushed gradient of the three golds. It is reserved for: the one gold word of a headline, the primary button, medals and the preloader. Never for large fills.

### Paper and neutrals

| Name | Hex | Usage |
|------|-----|-------|
| Ivory | `#f8f5ef` | Page background of the light chapters ("paper") |
| Mist | `#f4f6fa` | Secondary light surface |
| Soft | `#c3cbe0` | Muted text on navy |
| Slate | `#5c6782` | Muted text on paper |
| Line | `#e3e7ef` | Hairlines on paper |
| Line warm | `#e8dfcd` | Hairlines on ivory panels |
| White | `#ffffff` | Cards on paper |

### Accessibility

- Ivory on navy deep: 15.9:1 (AAA). Soft on navy deep: 10.3:1.
- Gold 2 on navy deep: 7.2:1 (AA for small text, AAA for large). Gold on ivory: 3.4:1 — large text and decorative lines only; body text on paper uses navy ink.
- Focus ring: 2px gold 2 outline with 3px offset, on every interactive element.

---

## 2. Typography

### Font stack

```css
--font-display: "Marcellus", "Amiri", Georgia, serif;             /* titles: caps, Trajan-like, the SK voice */
--font-accent:  "Cormorant Garamond", "EB Garamond", Georgia, serif; /* italic editorial lines, large numerals, quotes */
--font-text:    "Inter", "IBM Plex Sans Arabic", system-ui, sans-serif;
```

Arabic pages swap display to Amiri and text to IBM Plex Sans Arabic; the accent face is Latin-only and is never applied to Arabic words (Amiri regular, larger, takes its place).

### Type scale (fluid, website)

| Role | Size | Face | Line height | Tracking |
|------|------|------|-------------|----------|
| Display (hero) | clamp(3.5rem, 2rem + 8vw, 10rem) | Marcellus | 0.95 | −0.02em |
| H1 (page titles) | clamp(2.75rem, 1.6rem + 5vw, 6.5rem) | Marcellus | 1.0 | −0.015em |
| H2 (chapters) | clamp(2.25rem, 1.5rem + 3.4vw, 4.75rem) | Marcellus | 1.04 | −0.01em |
| H3 (blocks) | clamp(1.5rem, 1.25rem + 1.1vw, 2.25rem) | Marcellus | 1.1 | 0 |
| Accent line | clamp(1.5rem, 1.2rem + 1.6vw, 2.75rem) | Cormorant italic 300 | 1.2 | 0 |
| Lead | clamp(1.125rem, 1rem + .5vw, 1.4rem) | Inter 400 | 1.55 | 0 |
| Body | 1rem (16px) | Inter 400 | 1.6 | 0 |
| Small caps label | .75rem | Inter 600 uppercase | 1 | .22em |

All fonts are self-hosted (SIL OFL). The website loads them with `font-display: optional` so headlines never jump.

---

## 3. Logo Usage

| Variant | File | Use |
|---------|------|-----|
| Wordmark, white | `public/assets/img/sk-logo-white.png` | Header and footer on navy |
| Wordmark, navy | `public/assets/img/sk-logo.png` | Light surfaces, documents, Open Graph |
| Mark, white | `public/assets/img/sk-mark-white.png` | Preloader, placeholder tiles, favicon family |

Clear space: the height of the mark on every side. Minimum width 96px on screen. Never recolour, rotate, outline or shadow the logo; on photography always place it over the navy veil, never directly on a busy area.

---

## 4. Voice and Tone

| Trait | We are | We are not |
|-------|--------|------------|
| Quiet | Confident, understated, few words | Loud, salesy, exclamatory |
| Precise | Names, dates, titles, pedigrees exactly as recorded | Vague ("world-class"), superlatives |
| Hospitable | "Visit us", "Our team will gladly…" | Transactional ("Buy now") |
| Rooted | Qatar, the desert, the show ring, the bloodlines | Generic luxury clichés |

Prohibited: "world-class", "best-in-class", "luxury" (show it, do not say it), "for sale" (the stud does not sell horses on the website), any prices, medical or breeding details.

Tone by context: homepage — editorial and calm; horse profile — factual, proud; enquiry form — warm and brief; errors — plain and helpful.

---

## 5. Imagery

- Photography leads every page: full-bleed, side-on conformation shots for cards (4:5), cinematic wide frames for heroes (16:9 to 21:9), detail studies (eye, mane, dished profile) as texture.
- Grade: warm highlights, deep navy shadows; never a cold blue cast. Grain is applied by CSS at low opacity, never baked in.
- A horse without a photo gets the brand tile (SK mark on navy with the horse's initials in gold), never a stock image.
- Icons: 1.6px outlined strokes from `public/assets/img/icons.svg`; no emoji.

---

## 6. Components (website)

| Component | Default | Hover | Notes |
|-----------|---------|-------|-------|
| Primary button | metallic gold pill, navy text | sheen passes, lifts 1px | one per view |
| Secondary button | hairline pill, current colour | hairline turns gold | |
| Text link | underline hairline in gold, arrow | arrow slides 4px | "Discover ↗" |
| Horse card | 4:5 photo, name in Marcellus, small-caps meta, hairline | photo scales 1.04, hairline grows gold | no boxes, no glass |
| Chapter header | small-caps index "01 — The collection", H2, accent line | — | left-aligned on a 12-column grid |
| Hairline | 1px gold at 35% opacity | — | the rule between everything |

Motion: entrance reveals (mask rise, 700ms, expo-out), image wipes (clip-path, 900ms), parallax ≤ 8%, counters once; all removed under `prefers-reduced-motion`.
