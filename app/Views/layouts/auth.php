<?php use App\Core\Session; $flashes = Session::takeFlash(); ?><!doctype html>
<html lang="<?= e(lang()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0f1f45">
<title><?= e($title ?? '') ?> · <?= e(__('app.portal_name')) ?></title>
<link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(asset('img/icon-192.png')) ?>" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/portal.css')) ?>">
</head>
<body class="auth-page">
<main class="auth-card">
  <div class="auth-logo">
    <img src="<?= e(asset('img/sk-logo.png')) ?>" alt="SK Arabians" width="220" height="100">
    <h1>SK Arabians</h1>
    <p><?= e(__('app.portal_name')) ?></p>
  </div>
  <?php foreach ($flashes as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
  <div class="auth-foot">
    <a href="<?= e(site_url()) ?>">← <?= e(__('nav.view_website')) ?></a>
    <form method="post" action="<?= e(url('/portal/account/lang')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="lang" value="<?= lang() === 'ar' ? 'en' : 'ar' ?>"><button class="linklike" type="submit"><?= lang() === 'ar' ? 'English' : 'العربية' ?></button></form>
  </div>
</main>
<script src="<?= e(asset('js/portal.js')) ?>" defer></script>
</body>
</html>
