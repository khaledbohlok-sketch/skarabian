<?php
use App\Core\Session;

$path = current_path();
$other = lang() === 'ar' ? 'en' : 'ar';
$switchPath = preg_replace('#^/(en|ar)#', '/' . $other, $path);
$switchPath = $switchPath === $path ? '/' . $other : $switchPath;
$canonicalPath = preg_replace('#^/(en|ar)#', '', $path);
$metaTitle = isset($title) && $title !== '' ? $title . ' | SK Arabians' : 'SK Arabians — ' . __('site.tagline');
$metaDesc = $description ?? __('site.meta_description');
$ogImage = $ogImage ?? absolute_url('/assets/img/og-default.jpg');
$solidHeader = $solidHeader ?? true;
$nav = [
    ['site.nav_horses', 'horses'], ['site.nav_champions', 'champions'], ['site.nav_breeding', 'breeding'],
    ['site.nav_for_sale', 'for-sale'], ['site.nav_news', 'news'], ['site.nav_about', 'about'], ['site.nav_contact', 'contact'],
];
$wa = preg_replace('/\D/', '', (string) setting('company.whatsapp', ''));
$flashes = Session::takeFlash();
?><!doctype html>
<html lang="<?= e(lang()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($metaTitle) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<meta name="theme-color" content="#0f1f45">
<link rel="canonical" href="<?= e(absolute_url('/' . lang() . $canonicalPath)) ?>">
<link rel="alternate" hreflang="en" href="<?= e(absolute_url('/en' . $canonicalPath)) ?>">
<link rel="alternate" hreflang="ar" href="<?= e(absolute_url('/ar' . $canonicalPath)) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e(absolute_url('/en' . $canonicalPath)) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="SK Arabians">
<meta property="og:title" content="<?= e($metaTitle) ?>">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<meta property="og:url" content="<?= e(absolute_url($path)) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:locale" content="<?= lang() === 'ar' ? 'ar_QA' : 'en_US' ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="<?= e(asset('img/icon-192.png')) ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Inter:wght@400;500;600&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<?php if (!empty($jsonLd)): ?><script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script><?php endif; ?>
</head>
<body>
<a class="skip" href="#main"><?= e(__('common.skip_to_content')) ?></a>
<header class="site-header<?= $solidHeader ? ' solid' : '' ?>" data-header>
  <div class="container nav-bar">
    <a class="logo" href="<?= e(site_url()) ?>" aria-label="SK Arabians — <?= e(__('site.home')) ?>">
      <img src="<?= e(asset('img/sk-logo-white.png')) ?>" alt="Sk.Arabian — اس كي ارابيان للتجارة" width="132" height="60">
    </a>
    <nav class="main-nav" aria-label="<?= e(__('common.menu')) ?>">
      <?php foreach ($nav as [$label, $slug]): ?><a href="<?= e(site_url($slug)) ?>" class="<?= str_starts_with($path, '/' . lang() . '/' . $slug) ? 'on' : '' ?>"><?= e(__($label)) ?></a><?php endforeach; ?>
    </nav>
    <div class="nav-tools">
      <a class="lang-switch" href="<?= e(url($switchPath)) ?>" hreflang="<?= $other ?>" lang="<?= $other ?>"><?= $other === 'ar' ? 'العربية' : 'English' ?></a>
      <a class="staff-link" href="<?= e(url('/portal/login')) ?>" rel="nofollow"><?= e(__('site.staff_login')) ?></a>
      <button class="menu-btn" type="button" data-mobile-nav aria-label="<?= e(__('common.menu')) ?>" aria-expanded="false"><?= icon('menu') ?></button>
    </div>
  </div>
  <nav class="mobile-nav" data-mobile-menu>
    <?php foreach ($nav as [$label, $slug]): ?><a href="<?= e(site_url($slug)) ?>"><?= e(__($label)) ?></a><?php endforeach; ?>
    <a href="<?= e(url('/portal/login')) ?>" rel="nofollow"><?= e(__('site.staff_login')) ?></a>
  </nav>
</header>
<main id="main">
<?php if ($flashes): ?><div class="container" style="padding-top:100px"><?php foreach ($flashes as $f): ?><div class="notice notice-<?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach; ?></div><?php endif; ?>
<?= $content ?>
</main>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <a class="logo" href="<?= e(site_url()) ?>"><img src="<?= e(asset('img/sk-logo-white.png')) ?>" alt="Sk.Arabian" width="176" height="80"></a>
        <p><?= e(__('site.footer_about')) ?></p>
        <div class="socials">
          <?php foreach (['instagram' => 'instagram', 'x' => 'xlogo', 'youtube' => 'youtube', 'facebook' => 'facebook'] as $k => $ic): if ($u = setting('company.' . $k)): ?>
            <a href="<?= e($u) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($k)) ?>"><?= icon($ic) ?></a>
          <?php endif; endforeach; ?>
        </div>
      </div>
      <div><h4><?= e(__('site.explore')) ?></h4><ul><?php foreach ($nav as [$label, $slug]): ?><li><a href="<?= e(site_url($slug)) ?>"><?= e(__($label)) ?></a></li><?php endforeach; ?></ul></div>
      <div><h4><?= e(__('site.nav_contact')) ?></h4>
        <ul>
          <li><?= e(lang() === 'ar' ? setting('company.address_ar') : setting('company.address_en')) ?></li>
          <li><a href="tel:<?= e(setting('company.phone_intl')) ?>" dir="ltr"><?= e(setting('company.mobile')) ?></a></li>
          <li><a href="mailto:<?= e(setting('company.email')) ?>"><?= e(setting('company.email')) ?></a></li>
          <li>P.O. Box <?= e(setting('company.po_box')) ?>, <?= e(lang() === 'ar' ? setting('company.city_ar') : setting('company.city_en')) ?></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> SK Arabians · C.R. <?= e(setting('company.cr')) ?></span><a href="<?= e(url('/portal/login')) ?>" rel="nofollow"><?= e(__('site.staff_login')) ?></a></div>
  </div>
</footer>
<?php if ($wa): ?><a class="wa-float" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><?= icon('whatsapp') ?></a><?php endif; ?>
<script src="<?= e(asset('js/site.js')) ?>" defer></script>
</body>
</html>
