<?php
use App\Core\Auth;
use App\Core\Session;
use App\Services\Nav;
use App\Services\Notifier;

$user = Auth::user();
$unread = $user ? Notifier::unreadCount((int) $user['id']) : 0;
$flashes = Session::takeFlash();
?><!doctype html>
<html lang="<?= e(lang()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0f1f45">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($title ?? '') ?> · <?= e(__('app.portal_name')) ?></title>
<link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(asset('img/icon-192.png')) ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= e(asset('img/icon-192.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/portal.css')) ?>">
</head>
<body class="portal" data-base="<?= e(url('/')) ?>">
<a class="skip" href="#main"><?= e(__('common.skip_to_content')) ?></a>
<header class="topbar">
  <button class="icon-btn nav-toggle" type="button" data-toggle="nav" aria-label="<?= e(__('common.menu')) ?>"><?= icon('menu') ?></button>
  <a class="brand" href="<?= e(url('/portal')) ?>">
    <img src="<?= e(asset('img/sk-mark.png')) ?>" alt="" width="34" height="34">
    <span class="brand-text"><strong>SK Arabians</strong><small><?= e(__('app.portal_short')) ?></small></span>
  </a>
  <form class="global-search" action="<?= e(url('/portal/search')) ?>" method="get" role="search">
    <?= icon('search') ?>
    <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= e(__('common.global_search')) ?>" aria-label="<?= e(__('common.global_search')) ?>">
  </form>
  <div class="topbar-actions">
    <a class="icon-btn" href="<?= e(url('/portal/notifications')) ?>" aria-label="<?= e(__('nav.notifications')) ?>">
      <?= icon('bell') ?><?php if ($unread): ?><span class="dot-count"><?= $unread > 99 ? '99+' : (int) $unread ?></span><?php endif; ?>
    </a>
    <form method="post" action="<?= e(url('/portal/account/lang')) ?>" class="inline">
      <?= csrf_field() ?>
      <input type="hidden" name="lang" value="<?= lang() === 'ar' ? 'en' : 'ar' ?>">
      <button class="lang-btn" type="submit"><?= lang() === 'ar' ? 'EN' : 'عربي' ?></button>
    </form>
    <details class="user-menu">
      <summary><span class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'] ?? '?', 0, 1))) ?></span><span class="user-name"><?= e($user['name'] ?? '') ?></span></summary>
      <div class="menu">
        <div class="menu-head"><?= e($user['name'] ?? '') ?><small><?= e(loc(Auth::role(), 'name')) ?></small></div>
        <a href="<?= e(url('/portal/account')) ?>"><?= e(__('account.title')) ?></a>
        <a href="<?= e(url('/portal/account/password')) ?>"><?= e(__('auth.change_password')) ?></a>
        <a href="<?= e(url('/portal/account/2fa')) ?>"><?= e(__('auth.twofa')) ?></a>
        <form method="post" action="<?= e(url('/portal/logout')) ?>"><?= csrf_field() ?><button type="submit"><?= e(__('auth.logout')) ?></button></form>
      </div>
    </details>
  </div>
</header>
<div class="shell">
  <nav class="sidebar" id="sidebar" aria-label="<?= e(__('common.menu')) ?>">
    <ul>
    <?php foreach (Nav::items() as $item): $active = Nav::isActive($item['url']) || array_filter($item['children'], fn ($c) => Nav::isActive($c[1])); ?>
      <li class="<?= $active ? 'active' : '' ?>">
        <a href="<?= e(url($item['url'])) ?>"><?= icon($item['icon']) ?><span><?= e(__($item['label'])) ?></span></a>
        <?php if ($item['children'] && $active): ?>
          <ul class="sub">
            <?php foreach ($item['children'] as [$l, $u]): ?>
              <li class="<?= current_path() === $u ? 'current' : '' ?>"><a href="<?= e(url($u)) ?>"><?= e(__($l)) ?></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
    </ul>
    <div class="sidebar-foot"><a href="<?= e(site_url()) ?>" target="_blank" rel="noopener"><?= icon('globe') ?> <?= e(__('nav.view_website')) ?></a></div>
  </nav>
  <main id="main" class="content">
    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </main>
</div>
<div class="modal" id="modal" hidden>
  <div class="modal-box"><button class="modal-close icon-btn" type="button" data-close-modal aria-label="<?= e(__('common.close')) ?>"><?= icon('x') ?></button><iframe title="form"></iframe></div>
</div>
<script src="<?= e(asset('js/portal.js')) ?>" defer></script>
</body>
</html>
