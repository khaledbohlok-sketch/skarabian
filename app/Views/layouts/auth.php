<?php
/**
 * Sign-in, two-step code and password reset: the cinematic entrance. Full-screen horse photos (only the ones the public
 * website shows) drift and cross-fade under a navy vignette with gold light and grain; the SK mark appears inside the
 * gold star as it is drawn; the frosted card arrives line by line. Night in both themes (the dark layer always on).
 * Everything works without JavaScript; with reduced motion only the still photo and the card remain.
 */
use App\Core\Session;

$flashes = Session::takeFlash();
$theme = ui_theme();
$photos = [];
try { $photos = \App\Services\SiteData::heroPhotos(4); } catch (\Throwable $e) { $photos = []; } // the entrance never fails for want of a photo
?><!doctype html>
<html lang="<?= e(lang()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>" data-theme="<?= e($theme) ?>" data-motion="<?= e(ui_motion()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0f1a33">
<title><?= e($title ?? '') ?> · <?= e(__('app.portal_name')) ?></title>
<link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(asset('img/icon-192.png')) ?>" type="image/png">
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/brand.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/portal.css')) ?>">
<?php // the dark layer always on here (no id: the theme switch never turns it off on the sign-in pages) ?>
<link rel="stylesheet" href="<?= e(asset('css/portal-cinematic.css')) ?>" media="screen">
</head>
<body class="auth-page">
<div class="auth-bg" aria-hidden="true">
  <div class="auth-media">
    <?php foreach ($photos as $i => $ph): $src = \App\Services\SiteData::img((int) $ph['main_photo_id']); ?>
      <?php if ($i === 0): ?><img class="auth-slide on" src="<?= e($src) ?>" alt="" fetchpriority="high" decoding="async" width="1600" height="1000">
      <?php else: ?><img class="auth-slide" data-src="<?= e($src) ?>" alt="" decoding="async" width="1600" height="1000"><?php endif; ?>
    <?php endforeach; ?>
  </div>
  <div class="auth-aurora"><i></i><i></i></div>
  <div class="auth-grain"></div>
</div>
<div class="auth-stage">
<main class="auth-card">
  <div class="auth-logo">
    <span class="auth-mark" aria-hidden="true">
      <svg viewBox="0 0 100 100"><defs><linearGradient id="am-gold" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#e3cfa8"/><stop offset=".5" stop-color="#c9a46a"/><stop offset="1" stop-color="#b08d57"/></linearGradient></defs>
        <path d="M22 22H78V78H22Z" pathLength="1"/><path d="M50 10.4 89.6 50 50 89.6 10.4 50Z" pathLength="1"/></svg>
      <img src="<?= e(asset('img/sk-mark-white.png')) ?>" alt="" width="26" height="40">
    </span>
    <div><h1>SK Arabians</h1>
    <p><?= e(__('app.portal_name')) ?></p></div>
  </div>
  <div class="auth-alerts">
  <?php foreach ($flashes as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></div>
  <?php endforeach; ?>
  </div>
  <div class="auth-body"><?= $content ?></div>
  <div class="auth-foot">
    <a href="<?= e(site_url()) ?>"><?= icon('arrow-left', 'i-16 flip-rtl') ?> <?= e(__('nav.view_website')) ?></a>
    <form method="post" action="<?= e(url('/portal/account/lang')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="lang" value="<?= lang() === 'ar' ? 'en' : 'ar' ?>"><button class="linklike" type="submit" lang="<?= lang() === 'ar' ? 'en' : 'ar' ?>"><?= icon('globe', 'i-16') ?> <?= lang() === 'ar' ? 'English' : 'العربية' ?></button></form>
  </div>
</main>
<aside class="auth-aside" aria-hidden="true"><span><?= e(__('auth.aside_tag')) ?></span><strong><?= e(__('auth.aside_title')) ?></strong></aside>
</div>
<script src="<?= e(asset('js/portal.js')) ?>" defer></script>
<script src="<?= e(asset('js/portal-motion.js')) ?>" defer></script>
</body>
</html>
