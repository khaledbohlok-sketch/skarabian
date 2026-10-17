<?php $code = http_response_code(); $code = in_array($code, [403, 404, 500], true) ? $code : 500; ?><!doctype html>
<html lang="<?= e(lang()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>" data-motion="<?= e(ui_motion()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#0f1a33">
<title><?= e($code === 403 ? __('common.access_denied') : ($code === 404 ? __('site.not_found_title') : __('common.error_title'))) ?> | SK Arabians</title>
<link rel="icon" href="<?= e(url('/favicon.ico')) ?>" sizes="any">
<link rel="icon" href="<?= e(asset('img/icon-192.png')) ?>" type="image/png" sizes="192x192">
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/brand.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<style>
/* A 403 / 404 / 500 page of the staff system (and errors before a page could be drawn): the same night, glass and gold
   as the website — a soft gold light, grain and the star lattice behind one frosted card with the code in metallic gold. */
*, *::before, *::after { box-sizing: border-box; }
html { -webkit-text-size-adjust: 100%; }
body { position: relative; margin: 0; min-height: 100svh; display: grid; place-items: center; padding: var(--s-5) var(--s-4); overflow-x: hidden; font-family: var(--font-text); font-size: var(--fs-16); line-height: var(--lh-body); color: var(--c-ivory);
  background: radial-gradient(70% 70% at 50% 0%, var(--c-navy) 0%, var(--c-navy-ink) 45%, var(--c-navy-deep) 100%) fixed var(--c-navy-deep); }
body::before { content: ""; position: fixed; inset: 0; background: var(--c-gold-2); opacity: .05; -webkit-mask: var(--pattern) 0 0 / 72px 72px repeat; mask: var(--pattern) 0 0 / 72px 72px repeat; pointer-events: none; }
body::after { content: ""; position: fixed; inset: 0; background: var(--grain); opacity: .06; mix-blend-mode: overlay; pointer-events: none; }
.err-glow { position: fixed; top: -30vmax; left: 50%; width: 70vmax; height: 70vmax; margin-left: -35vmax; border-radius: 50%; background: radial-gradient(closest-side, var(--aurora-gold), transparent 70%); opacity: .5; pointer-events: none; }
@media (prefers-reduced-motion: no-preference) { html:not([data-motion="reduce"]) .err-glow { animation: drift-a var(--dur-ambient) var(--ease-in-out) infinite alternate; } }
.err-card { position: relative; z-index: 1; width: 100%; max-width: 560px; padding: var(--s-7) var(--s-6) var(--s-6); text-align: center; border-radius: var(--r-2xl);
  background: color-mix(in srgb, var(--c-navy-ink) 62%, transparent); border: 1px solid var(--glass-edge); box-shadow: var(--glass-highlight), var(--sh-3); -webkit-backdrop-filter: var(--glass-blur); backdrop-filter: var(--glass-blur); }
@supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) { .err-card { background: var(--c-navy-ink); } }
.err-card::before { content: ""; position: absolute; inset-inline: 40px; top: 0; height: 1px; background: var(--metal-line); opacity: .7; }
@media (prefers-reduced-motion: no-preference) { html:not([data-motion="reduce"]) .err-card { animation: rise-in 800ms var(--ease-out-expo) both; } }
.err-mark { position: relative; display: grid; place-items: center; width: 64px; height: 64px; margin: 0 auto var(--s-4); }
.err-mark svg { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
.err-mark path { fill: none; stroke: var(--c-gold-2); stroke-width: 1.6; stroke-dasharray: 1; stroke-dashoffset: 0; }
@media (prefers-reduced-motion: no-preference) { html:not([data-motion="reduce"]) .err-mark path { stroke-dashoffset: 1; animation: draw 1.1s var(--ease-in-out-quint) 200ms forwards; } html:not([data-motion="reduce"]) .err-mark path + path { animation-delay: 350ms; } }
.err-mark img { width: 24px; height: auto; }
.err-code { display: block; margin: 0 0 var(--s-2); font-family: "Marcellus", var(--font-display); font-size: clamp(72px, 16vw, 132px); line-height: .9; letter-spacing: -.02em; }
.err-card h1 { font-family: var(--font-display); font-weight: 400; font-size: clamp(26px, 4vw, 34px); line-height: var(--lh-tight); margin: 0 0 var(--s-3); color: var(--c-white); }
html[dir="rtl"] .err-card h1 { font-weight: 700; line-height: var(--lh-snug); }
.err-card p { margin: 0 0 var(--s-4); color: var(--c-soft); }
.err-card .muted { font-size: var(--fs-14); color: var(--c-soft); opacity: .85; }
.err-card .btn, .err-card a.btn { position: relative; overflow: hidden; display: inline-flex; align-items: center; justify-content: center; min-height: 46px; padding: 0 var(--s-6); margin: var(--s-2) var(--s-1) 0;
  border-radius: var(--r-pill); border: 1px solid color-mix(in srgb, var(--c-gold-soft) 55%, var(--c-gold)); background: var(--metal); background-size: 180% 100%; color: var(--c-navy-deep); font-weight: var(--fw-semibold); text-decoration: none;
  box-shadow: inset 0 1px 0 color-mix(in srgb, var(--c-white) 45%, transparent), 0 10px 26px -12px color-mix(in srgb, var(--c-gold) 70%, transparent); transition: box-shadow var(--dur) var(--ease-out); }
.err-card .btn:hover { box-shadow: inset 0 1px 0 color-mix(in srgb, var(--c-white) 45%, transparent), var(--sh-gold); }
.err-card .btn-ghost { background: transparent; color: var(--c-ivory); border-color: color-mix(in srgb, var(--c-ivory) 30%, transparent); box-shadow: none; }
.err-card .btn-ghost:hover { border-color: var(--c-gold-2); box-shadow: none; }
.err-card a:focus-visible { outline: 2px solid var(--c-gold-2); outline-offset: 3px; }
.err-card .error-page > p:last-child { margin-bottom: 0; }
/* the Owner's details of what went wrong (500) */
.error-detail { display: grid; gap: var(--s-2); margin: 0 0 var(--s-4); padding: var(--s-4); text-align: start; border-radius: var(--r-md);
  background: color-mix(in srgb, var(--c-navy-deep) 70%, transparent); border: 1px solid var(--c-navy-2); font-size: var(--fs-14); color: var(--c-soft); }
.error-detail b { color: var(--c-ivory); font-weight: var(--fw-semibold); }
.error-detail code { display: block; white-space: pre-wrap; overflow-wrap: anywhere; font-size: var(--fs-12); color: var(--c-gold-soft); }
.error-detail a { color: var(--c-gold-2); }
@media (max-width: 480px) { .err-card { padding: var(--s-6) var(--s-5) var(--s-5); } }
@media print { body { background: none; color: #000; } body::before, body::after, .err-glow { display: none; } .err-card { box-shadow: none; border-color: #999; background: none; } .err-card h1, .err-card p { color: #000; } }
</style>
</head>
<body>
<div class="err-glow" aria-hidden="true"></div>
<main class="err-card">
  <span class="err-mark" aria-hidden="true"><svg viewBox="0 0 100 100"><path d="M22 22H78V78H22Z" pathLength="1"/><path d="M50 10.4 89.6 50 50 89.6 10.4 50Z" pathLength="1"/></svg><img src="<?= e(asset('img/sk-mark-white.png')) ?>" alt="SK Arabians" width="24" height="37"></span>
  <span class="err-code gold-text" aria-hidden="true"><?= (int) $code ?></span>
  <?= $content ?>
</main>
</body>
</html>
