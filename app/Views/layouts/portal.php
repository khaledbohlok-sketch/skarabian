<?php
use App\Core\Auth;
use App\Core\DB;
use App\Core\Session;
use App\Services\Nav;
use App\Services\Notifier;

$user = Auth::user();
$uid = (int) ($user['id'] ?? 0);
$unread = $user ? Notifier::unreadCount($uid) : 0;
// the bell's menu: the latest notifications in the reader's language (read only — opening the full list marks them read, as before)
$latest = $user ? array_map([Notifier::class, 'localize'], DB::all('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 6', [$uid])) : [];
$flashes = Session::takeFlash();
$theme = ui_theme();
$collapsed = ($_COOKIE['sk_nav'] ?? '') === 'collapsed';
$menu = Nav::items();
$here = Nav::current($menu); // exactly one group, at most one sub-page
$userName = (string) ($user['name'] ?? '');
$roleName = loc(Auth::role(), 'name');
$initial = mb_strtoupper(mb_substr($userName !== '' ? $userName : '?', 0, 1));
// "New…": the quick-create pages this user may use (existing pages and permissions only)
$quick = array_values(array_filter([
    can('finance', 'create') ? ['/portal/bills/create', 'wallet', __('dashboard.add_bill')] : null,
    can('horses', 'create') ? ['/portal/horses/create', 'horse', __('dashboard.add_horse')] : null,
    can('embryos', 'create') ? ['/portal/embryos/create', 'embryo', __('dashboard.add_embryo')] : null,
    can('horse_health', 'create') ? ['/portal/health-records/create', 'check', __('dashboard.add_health')] : null,
    can('horse_diet', 'create') ? ['/portal/diet-logs/create', 'feed', __('dashboard.add_feed')] : null,
    can('hr', 'create') ? ['/portal/employees/create', 'user-plus', __('employees.singular')] : null,
    can('documents', 'create') ? ['modal:/portal/documents/upload-form', 'upload', __('ui.q_document')] : null,
]));
$navItem = function (int $i, array $item) use ($here): string {
    $active = $i === $here['group'];
    $label = __($item['label']);
    $h = '<li class="' . ($active ? 'active' : '') . '" style="--n:' . $i . '"><a href="' . e(url($item['url'])) . '"' . ($active ? ' aria-current="page"' : '') . ' data-tip="' . e($label) . '" data-tip-when="collapsed">'
        . icon($item['icon']) . '<span>' . e($label) . '</span></a>';
    if ($item['children'] && $active) {
        $h .= '<ul class="sub">';
        foreach ($item['children'] as [$l, $u]) {
            $h .= '<li class="' . ($u === $here['child'] ? 'current' : '') . '"><a href="' . e(url($u)) . '"' . ($u === $here['child'] ? ' aria-current="page"' : '') . '>' . e(__($l)) . '</a></li>';
        }
        $h .= '</ul>';
    }
    return $h . '</li>';
};
?><!doctype html>
<html lang="<?= e(lang()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>" data-theme="<?= e($theme) ?>" data-motion="<?= e(ui_motion()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<?php if ($theme === 'system'): ?><meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)"><meta name="theme-color" content="#0f1a33" media="(prefers-color-scheme: dark)">
<?php else: ?><meta name="theme-color" content="<?= $theme === 'light' ? '#ffffff' : '#0f1a33' ?>">
<?php endif; ?>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<script src="<?= e(asset('js/portal-head.js')) ?>"></script>
<title><?= e($title ?? '') ?> · <?= e(__('app.portal_name')) ?></title>
<link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(asset('img/icon-192.png')) ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= e(asset('img/icon-192.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/brand.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/portal.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/modal.css')) ?>">
<?php if (\App\Services\Ai::enabled()): ?><link rel="stylesheet" href="<?= e(asset('css/ai.css')) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('vendor/flatpickr/flatpickr.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/flatpickr-theme.css')) ?>">
<?php foreach (($styles ?? []) as $css): ?><link rel="stylesheet" href="<?= e(asset($css)) ?>">
<?php endforeach; ?>
<link rel="stylesheet" href="<?= e(asset('css/portal-cinematic.css')) ?>" media="<?= e(theme_media()) ?>" id="theme-dark">
<?php if (str_starts_with(current_path(), '/portal/studio')): // the Studio screens' own dark layer (same theme switch) ?><link rel="stylesheet" href="<?= e(asset('css/studio-dark.css')) ?>" media="<?= e(theme_media()) ?>" data-theme-dark><?php endif; ?>
<noscript><style>.filters.is-collapsed { display: flex; }</style></noscript>
</head>
<body class="portal<?= !empty($bodyClass) ? ' ' . e($bodyClass) : '' ?><?= $collapsed ? ' nav-collapsed' : '' ?>" data-base="<?= e(url('/')) ?>">
<div class="curtain" aria-hidden="true"><img src="<?= e(asset('img/sk-mark-white.png')) ?>" alt="" width="44" height="68"></div>
<a class="skip" href="#main"><?= e(__('common.skip_to_content')) ?></a>
<div class="shell">
  <nav class="sidebar" id="sidebar" aria-label="<?= e(__('common.menu')) ?>">
    <a class="side-brand" href="<?= e(url('/portal')) ?>" data-tip="SK Arabians" data-tip-when="collapsed">
      <img src="<?= e(asset('img/sk-mark-white.png')) ?>" alt="" width="38" height="38">
      <span><strong>SK Arabians</strong><small><?= e(__('app.portal_short')) ?></small></span>
    </a>
    <div class="side-scroll">
      <?php $top = array_filter($menu, fn ($it) => $it['group'] === null); ?>
      <?php if ($top): ?><ul class="nav-list nav-top">
        <?php foreach ($top as $i => $item): ?><?= $navItem($i, $item) ?><?php endforeach; ?>
      </ul><?php endif; ?>
      <?php foreach (Nav::GROUP_LABELS as $g => $gLabel): $items = array_filter($menu, fn ($it) => $it['group'] === $g); if (!$items) { continue; } ?>
      <div class="nav-group" role="group" aria-labelledby="ng-<?= e($g) ?>">
        <span class="nav-heading" id="ng-<?= e($g) ?>"><?= e(__($gLabel)) ?></span>
        <ul class="nav-list">
          <?php foreach ($items as $i => $item): ?><?= $navItem($i, $item) ?><?php endforeach; ?>
        </ul>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="side-foot">
      <a class="side-link" href="<?= e(site_url()) ?>" target="_blank" rel="noopener" data-tip="<?= e(__('nav.view_website')) ?>" data-tip-when="collapsed"><?= icon('globe', 'i-20') ?><span><?= e(__('nav.view_website')) ?></span></a>
      <?php if ($user): ?>
      <div class="side-user">
        <a href="<?= e(url('/portal/account')) ?>" data-tip="<?= e($userName) ?>" data-tip-when="collapsed"><span class="avatar" aria-hidden="true"><?= e($initial) ?></span><span class="side-user-text"><b><?= e($userName) ?></b><small><?= e($roleName) ?></small></span></a>
        <form method="post" action="<?= e(url('/portal/logout')) ?>"><?= csrf_field() ?><button class="icon-btn" type="submit" aria-label="<?= e(__('auth.logout')) ?>" data-tip="<?= e(__('auth.logout')) ?>"><?= icon('log-out') ?></button></form>
      </div>
      <?php endif; ?>
    </div>
  </nav>
  <div class="nav-overlay" data-nav-close></div>
  <div class="main-col">
    <header class="topbar">
      <button class="icon-btn nav-toggle" type="button" data-toggle="nav" aria-controls="sidebar" aria-expanded="false" aria-label="<?= e(__('common.menu')) ?>"><?= icon('menu') ?></button>
      <button class="icon-btn nav-collapse" type="button" data-nav-collapse aria-controls="sidebar" aria-pressed="<?= $collapsed ? 'true' : 'false' ?>" aria-label="<?= e(__($collapsed ? 'ui.expand_menu' : 'ui.collapse_menu')) ?>" data-label-collapse="<?= e(__('ui.collapse_menu')) ?>" data-label-expand="<?= e(__('ui.expand_menu')) ?>" data-tip="<?= e(__($collapsed ? 'ui.expand_menu' : 'ui.collapse_menu')) ?>"><?= icon('panel-left', 'flip-rtl') ?></button>
      <a class="brand" href="<?= e(url('/portal')) ?>" aria-label="SK Arabians">
        <img class="only-light" src="<?= e(asset('img/sk-mark.png')) ?>" alt="" width="32" height="32"><img class="only-dark" src="<?= e(asset('img/sk-mark-white.png')) ?>" alt="" width="32" height="32">
      </a>
      <form class="global-search" action="<?= e(url('/portal/search')) ?>" method="get" role="search">
        <?= icon('search') ?>
        <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= e(__('common.global_search')) ?>" aria-label="<?= e(__('common.global_search')) ?>">
        <button class="cmdk-hint" type="button" data-cmdk aria-label="<?= e(__('ui.cmdk_open')) ?>" data-tip="<?= e(__('ui.cmdk_open')) ?>"><kbd data-mod>Ctrl</kbd><kbd>K</kbd></button>
      </form>
      <div class="topbar-actions">
        <a class="icon-btn" href="<?= e(url('/portal/search')) ?>" data-mobile-only aria-label="<?= e(__('common.global_search')) ?>"><?= icon('search') ?></a>
        <?php if ($quick): ?>
        <div class="dd">
          <button class="btn btn-primary btn-sm new-btn" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="new-menu" data-dd="new-menu" aria-label="<?= e(__('common.new')) ?>"><?= icon('plus') ?><span class="new-label"><?= e(__('common.new')) ?></span></button>
          <div class="dd-menu" id="new-menu" role="menu" hidden data-align="end">
            <?php foreach ($quick as [$href, $ic, $label]): ?>
              <?php if (str_starts_with($href, 'modal:')): ?><button type="button" role="menuitem" data-add-new="<?= e(url(substr($href, 6))) ?>" data-modal-title="<?= e($label) ?>"><?= icon($ic) ?><span><?= e($label) ?></span></button>
              <?php else: ?><a role="menuitem" href="<?= e(url($href)) ?>"><?= icon($ic) ?><span><?= e($label) ?></span></a><?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($user): ?>
        <div class="dd">
          <a class="icon-btn" href="<?= e(url('/portal/notifications')) ?>" role="button" aria-haspopup="menu" aria-expanded="false" aria-controls="notif-menu" data-dd="notif-menu" aria-label="<?= e(__('nav.notifications')) ?><?= $unread ? ' (' . (int) $unread . ')' : '' ?>">
            <?= icon($unread ? 'bell-dot' : 'bell') ?><?php if ($unread): ?><span class="badge-count" aria-hidden="true"><?= $unread > 99 ? '99+' : (int) $unread ?></span><?php endif; ?>
          </a>
          <div class="dd-menu wide" id="notif-menu" role="menu" hidden data-align="end" aria-label="<?= e(__('nav.notifications')) ?>">
            <div class="dd-head"><b><?= e(__('nav.notifications')) ?></b></div>
            <?php if ($latest): ?>
            <ul class="notif-list">
              <?php foreach ($latest as $n): ?>
                <li class="<?= $n['read_at'] ? '' : 'unread' ?>"><a role="menuitem" href="<?= e(url($n['url'] ?: '/portal/notifications')) ?>"><span class="notif-dot" aria-hidden="true"></span><b><?= e($n['title']) ?></b><small><?= e(fmt_date($n['created_at'], true)) ?></small></a></li>
              <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div class="notif-empty"><?= icon('check-circle') ?><span><?= e(__('ui.notifications_empty')) ?></span></div>
            <?php endif; ?>
            <div class="dd-foot">
              <a role="menuitem" href="<?= e(url('/portal/notifications')) ?>"><?= e(__('common.view_all')) ?></a>
              <?php if ($unread): ?><form method="post" action="<?= e(url('/portal/notifications/read')) ?>"><?= csrf_field() ?><button class="linklike" type="submit" role="menuitem"><?= e(__('ui.mark_all_read')) ?></button></form><?php endif; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/portal/account/lang')) ?>" class="inline" data-desktop-only>
          <?= csrf_field() ?>
          <input type="hidden" name="lang" value="<?= lang() === 'ar' ? 'en' : 'ar' ?>">
          <button class="lang-btn" type="submit" lang="<?= lang() === 'ar' ? 'en' : 'ar' ?>"><?= lang() === 'ar' ? 'English' : 'عربي' ?></button>
        </form>
        <?php if ($user): ?>
        <div class="dd">
          <button class="user-trigger" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="user-menu" data-dd="user-menu" aria-label="<?= e(__('ui.user_menu')) ?>">
            <span class="avatar" aria-hidden="true"><?= e($initial) ?></span><span class="user-name"><b><?= e($userName) ?></b><small><?= e($roleName) ?></small></span><?= icon('chevron-down', 'i-16') ?>
          </button>
          <div class="dd-menu" id="user-menu" role="menu" hidden data-align="end" aria-label="<?= e(__('ui.user_menu')) ?>">
            <div class="dd-head"><b><?= e($userName) ?></b><small><?= e($roleName) ?></small></div>
            <a role="menuitem" href="<?= e(url('/portal/account')) ?>"><?= icon('user') ?><span><?= e(__('account.title')) ?></span></a>
            <a role="menuitem" href="<?= e(url('/portal/account/password')) ?>"><?= icon('key') ?><span><?= e(__('auth.change_password')) ?></span></a>
            <a role="menuitem" href="<?= e(url('/portal/account/2fa')) ?>"><?= icon('shield') ?><span><?= e(__('auth.twofa')) ?></span></a>
            <div class="dd-sep" role="separator"></div>
            <span class="dd-label" id="theme-label"><?= e(__('theme.label')) ?></span>
            <div class="theme-switch" role="group" aria-labelledby="theme-label">
              <?php foreach (['system' => 'monitor', 'light' => 'sun', 'dark' => 'moon'] as $tk => $tic): ?>
                <button type="button" role="menuitemradio" aria-checked="<?= $theme === $tk ? 'true' : 'false' ?>" data-theme-set="<?= $tk ?>"><?= icon($tic) ?><span><?= e(__('theme.' . $tk)) ?></span></button>
              <?php endforeach; ?>
            </div>
            <form method="post" action="<?= e(url('/portal/account/lang')) ?>"><?= csrf_field() ?><input type="hidden" name="lang" value="<?= lang() === 'ar' ? 'en' : 'ar' ?>"><button type="submit" role="menuitem" lang="<?= lang() === 'ar' ? 'en' : 'ar' ?>"><?= icon('globe') ?><span><?= lang() === 'ar' ? 'English' : 'عربي' ?></span></button></form>
            <div class="dd-sep" role="separator"></div>
            <form method="post" action="<?= e(url('/portal/logout')) ?>"><?= csrf_field() ?><button type="submit" role="menuitem"><?= icon('log-out', 'flip-rtl') ?><span><?= e(__('auth.logout')) ?></span></button></form>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </header>
    <main id="main" class="content" tabindex="-1">
      <?php if ($flashes): ?><div class="flash-stack" data-flash>
      <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>" data-flash-type="<?= e($f['type']) ?>"><?= e($f['message']) ?><button type="button" class="icon-btn alert-x" data-dismiss aria-label="<?= e(__('common.close')) ?>"><?= icon('x', 'i-16') ?></button></div>
      <?php endforeach; ?>
      </div><?php endif; ?>
      <?php if (Auth::check() && ($graceUntil = \App\Services\AuthService::twofaGraceUntil(Auth::user())) && current_path() !== '/portal/account/2fa'): // 2FA required, grace period running ?>
        <div class="alert alert-warning" role="status"><?= e(__('auth.twofa_grace', ['date' => fmt_date($graceUntil, true)])) ?> <a href="<?= e(url('/portal/account/2fa')) ?>"><?= e(__('auth.twofa_setup_now')) ?></a></div>
      <?php endif; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<?= \App\Core\View::partial('partials/modal') ?>
<script src="<?= e(asset('vendor/flatpickr/flatpickr.min.js')) ?>" defer></script>
<script src="<?= e(asset('vendor/flatpickr/l10n/ar.js')) ?>" defer></script>
<script src="<?= e(asset('js/modal.js')) ?>" defer></script>
<script src="<?= e(asset('js/dropdown.js')) ?>" defer></script>
<script src="<?= e(asset('js/portal.js')) ?>" defer></script>
<script type="application/json" id="sk-portal-cfg"><?= json_encode([
    'search' => url('/portal/search'), 't' => [
        'cmdk' => __('ui.cmdk_open'), 'placeholder' => __('ui.cmdk_placeholder'), 'pages' => __('ui.cmdk_pages'), 'actions' => __('ui.cmdk_actions'), 'records' => __('ui.cmdk_records'),
        'searchAll' => __('ui.cmdk_search_all'), 'empty' => __('common.no_records'), 'loading' => __('ui.cmdk_loading'), 'nav' => __('ui.cmdk_nav'), 'open' => __('ui.cmdk_open_item'), 'close' => __('common.close'),
        'unsaved' => __('ui.unsaved'), 'theme' => __('theme.label'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= e(asset('js/portal-motion.js')) ?>" defer></script>
<?php if (\App\Services\Ai::enabled()): ?><script src="<?= e(asset('js/ai.js')) ?>" defer></script><?php endif; ?>
<script src="<?= e(asset('js/datepicker.js')) ?>" defer></script>
<script src="<?= e(asset('js/bill-report-picker.js')) ?>" defer></script>
<?php foreach (($scripts ?? []) as $js): ?><script src="<?= e(asset($js)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
