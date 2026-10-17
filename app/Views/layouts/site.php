<?php
/**
 * The public website's frame (version 3, "Maison"): head (SEO, fonts, styles), the first-visit preloader, the header
 * (transparent over the hero, then a frosted navy bar), the phone menu, the page, the footer, the WhatsApp button and
 * the scripts. Everything the page sends: $title, $seoTitle, $description, $ogImage, $ogAlt, $ogType, $jsonLd.
 */
use App\Core\Session;
use App\Services\SiteData;

$path = current_path();
$other = lang() === 'ar' ? 'en' : 'ar';
$switchPath = preg_replace('#^/(en|ar)#', '/' . $other, $path);
if ($switchPath === $path) {
    $switchPath = '/' . $other;                       // a page outside /en and /ar (document check): the other language's home
} elseif ((string) ($_SERVER['QUERY_STRING'] ?? '') !== '') {
    $switchPath .= '?' . $_SERVER['QUERY_STRING'];    // keep the visitor's filters ("?category=mare")
}
$canonicalPath = preg_replace('#^/(en|ar)#', '', $path);
$notFound = in_array(http_response_code(), [404, 410], true);
$isVerify = str_starts_with($path, '/verify');
if ($notFound) {
    $title = __('site.not_found_title');
    $description = __('common.not_found');
}
if ($isVerify) {
    $description = $description ?? __('site.meta_verify');
}
$metaTitle = !empty($seoTitle) ? $seoTitle : (isset($title) && $title !== '' ? $title . ' | ' . __('site.brand') : __('site.seo_home_title'));
$metaDesc = $description ?? __('site.meta_description');
$ogImage = $ogImage ?? absolute_url('/assets/img/og-default.jpg');
$ogAlt = $ogAlt ?? (isset($title) && $title !== '' ? $title : __('site.brand'));
$isHome = rtrim($path, '/') === '/' . lang();
// Home and Horses always; the other pages as switched on in Website → Homepage & texts
$nav = [['site.home', ''], ['site.nav_horses', 'horses']];
foreach (['champions' => 'site.nav_champions', 'news' => 'site.nav_news', 'about' => 'site.nav_about', 'contact' => 'site.nav_contact'] as $slug => $label) {
    if (SiteData::pageOn($slug)) {
        $nav[] = [$label, $slug];
    }
}
$isOn = fn (string $slug) => $slug === '' ? $isHome : str_starts_with($path, '/' . lang() . '/' . $slug);
$wa = site_contact('whatsapp');
// "Enquire": the contact page's form, or WhatsApp when the contact page is switched off
$enquireUrl = SiteData::pageOn('contact') ? site_url('contact') . '#inquiry' : 'https://wa.me/' . $wa;
$enquireExt = !SiteData::pageOn('contact');
$chatOn = (bool) preg_match('#^/(en|ar)(/|$)#', $path) && \App\Services\SiteAssistant::on();
$flashes = Session::takeFlash();
$socials = [];
foreach (['instagram' => ['instagram', 'Instagram'], 'x' => ['xlogo', 'X'], 'youtube' => ['youtube', 'YouTube'], 'facebook' => ['facebook', 'Facebook'], 'tiktok' => ['tiktok', 'TikTok']] as $k => [$ic, $name]) {
    if ($u = (string) setting('company.' . $k, '')) {
        $socials[] = [$u, $ic, $name];
    }
}
$navLink = function (string $label, string $slug) use ($isOn): string {
    return '<a href="' . e(site_url($slug)) . '"' . ($isOn($slug) ? ' aria-current="page"' : '') . '>' . e(__($label)) . '</a>';
};
$est = (string) setting('site.established', '2025');
?><!doctype html>
<html lang="<?= e(lang()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($metaTitle) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<meta name="theme-color" content="#0f1a33">
<?php if ($notFound || $isVerify): ?>
<meta name="robots" content="noindex">
<?php else: ?>
<link rel="canonical" href="<?= e(absolute_url('/' . lang() . $canonicalPath)) ?>">
<link rel="alternate" hreflang="en" href="<?= e(absolute_url('/en' . $canonicalPath)) ?>">
<link rel="alternate" hreflang="ar" href="<?= e(absolute_url('/ar' . $canonicalPath)) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e(absolute_url('/en' . $canonicalPath)) ?>">
<?php endif; ?>
<meta property="og:type" content="<?= isset($ogType) ? e($ogType) : 'website' ?>">
<meta property="og:site_name" content="<?= e(__('site.brand')) ?>">
<meta property="og:title" content="<?= e($metaTitle) ?>">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<meta property="og:url" content="<?= e(absolute_url($path)) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:image:alt" content="<?= e($ogAlt) ?>">
<meta property="og:locale" content="<?= lang() === 'ar' ? 'ar_QA' : 'en_GB' ?>">
<meta property="og:locale:alternate" content="<?= lang() === 'ar' ? 'en_GB' : 'ar_QA' ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($metaTitle) ?>">
<meta name="twitter:description" content="<?= e($metaDesc) ?>">
<meta name="twitter:image" content="<?= e($ogImage) ?>">
<link rel="icon" href="<?= e(url('/favicon.ico')) ?>" sizes="any">
<link rel="icon" href="<?= e(asset('img/icon-192.png')) ?>" type="image/png" sizes="192x192">
<link rel="apple-touch-icon" href="<?= e(asset('img/icon-192.png')) ?>">
<?php // fonts: the very address the stylesheets ask for (no ?v=), else the browser fetches each font twice ?>
<link rel="preload" href="<?= e(url('/assets/fonts/Marcellus-400.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(url('/assets/fonts/Inter-var-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(url('/assets/fonts/CormorantGaramond-300i.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<?php if (is_rtl()): ?>
<link rel="preload" href="<?= e(url('/assets/fonts/Amiri-700-12.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(url('/assets/fonts/IBMPlexSansArabic-400-4.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<?php endif; ?>
<script src="<?= e(asset('js/site-head.js')) ?>"></script>
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/brand.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/site-pages.css')) ?>">
<?php if ($chatOn): ?><link rel="stylesheet" href="<?= e(asset('css/ai-chat.css')) ?>" media="screen"><?php endif; ?>
<?php if (!empty($jsonLd)): ?><script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script><?php endif; ?>
</head>
<body>
<?php // first visit of the session only (site-head.js sets html.intro): the mark, the name and a gold line, then the
      // curtain lifts; decorative, skipped by a click or any key (site.js) ?>
<div class="preloader" aria-hidden="true" data-preloader>
  <div class="preloader-mark">
    <img src="<?= e(asset('img/sk-mark-white.png')) ?>" alt="" width="34" height="51">
    <span class="preloader-word" dir="ltr">SK Arabians</span>
    <span class="preloader-line"></span>
  </div>
</div>
<a class="skip" href="#main"><?= e(__('common.skip_to_content')) ?></a>
<header class="site-header" data-header>
  <div class="container nav-bar">
    <a class="logo" href="<?= e(site_url()) ?>" aria-label="<?= e(__('site.brand')) ?> — <?= e(__('site.home')) ?>">
      <img src="<?= e(asset('img/sk-logo-white.png')) ?>" alt="SK Arabians" width="119" height="44">
    </a>
    <nav class="main-nav" aria-label="<?= e(__('common.menu')) ?>">
      <?php foreach ($nav as [$label, $slug]): ?><?= $navLink($label, $slug) ?><?php endforeach; ?>
    </nav>
    <div class="nav-tools">
      <a class="lang-switch" href="<?= e(url($switchPath)) ?>" hreflang="<?= $other ?>" lang="<?= $other ?>"><?= icon('globe') ?><span><?= $other === 'ar' ? 'العربية' : 'English' ?></span></a>
      <a class="btn btn-line btn-sm nav-staff" href="<?= e(url('/portal/login')) ?>" rel="nofollow"><?= icon('lock') ?><span><?= e(__('site.staff_login')) ?></span></a>
      <a class="btn btn-gold btn-sm nav-enquire" href="<?= e($enquireUrl) ?>"<?= $enquireExt ? ' target="_blank" rel="noopener"' : '' ?>><?= e(__('site.enquire')) ?></a>
      <button class="menu-btn" type="button" data-menu-open aria-controls="site-menu" aria-expanded="false" aria-label="<?= e(__('common.menu')) ?>"><?= icon('menu') ?></button>
    </div>
  </div>
</header>
<div class="nav-overlay" data-menu-overlay hidden></div>
<div class="nav-panel" id="site-menu" data-menu-panel role="dialog" aria-modal="true" aria-label="<?= e(__('common.menu')) ?>" hidden>
  <div class="nav-panel-head">
    <img src="<?= e(asset('img/sk-logo-white.png')) ?>" alt="" width="97" height="36">
    <button class="menu-close" type="button" data-menu-close aria-label="<?= e(__('site.menu_close')) ?>"><?= icon('x') ?></button>
  </div>
  <nav class="nav-panel-links" aria-label="<?= e(__('common.menu')) ?>">
    <?php foreach ($nav as [$label, $slug]): ?><?= $navLink($label, $slug) ?><?php endforeach; ?>
  </nav>
  <div class="nav-panel-actions">
    <a class="btn btn-gold" href="<?= e($enquireUrl) ?>"<?= $enquireExt ? ' target="_blank" rel="noopener"' : '' ?>><?= e(__('site.enquire')) ?></a>
    <a class="btn btn-line" href="<?= e(url('/portal/login')) ?>" rel="nofollow"><?= icon('lock') ?> <?= e(__('site.staff_login')) ?></a>
    <a class="lang-switch" href="<?= e(url($switchPath)) ?>" hreflang="<?= $other ?>" lang="<?= $other ?>"><?= icon('globe') ?><span><?= $other === 'ar' ? 'العربية' : 'English' ?></span></a>
  </div>
  <p class="nav-panel-foot"><?= e(__('site.hero_eyebrow', ['year' => $est])) ?></p>
</div>
<main id="main" tabindex="-1">
<?php if ($flashes): ?><div class="container flash-box"><?php foreach ($flashes as $f): ?><div class="notice notice-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></div><?php endforeach; ?></div><?php endif; ?>
<?= $content ?>
</main>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a class="logo" href="<?= e(site_url()) ?>" aria-label="<?= e(__('site.brand')) ?> — <?= e(__('site.home')) ?>"><img src="<?= e(asset('img/sk-logo-white.png')) ?>" alt="SK Arabians" width="140" height="52" loading="lazy"></a>
        <p><?= e(__('site.footer_about')) ?></p>
        <?php if ($socials): ?>
        <div class="socials">
          <?php foreach ($socials as [$u, $ic, $name]): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener" aria-label="<?= e($name) ?>"><?= icon($ic) ?></a><?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div><h2 class="footer-h"><?= e(__('site.explore')) ?></h2><ul><?php foreach ($nav as [$label, $slug]): ?><li><a href="<?= e(site_url($slug)) ?>"><?= e(__($label)) ?></a></li><?php endforeach; ?></ul></div>
      <div><h2 class="footer-h"><?= e(__('site.nav_contact')) ?></h2>
        <ul class="footer-contact">
          <li><?= icon('phone') ?><a href="tel:<?= e(site_contact('tel')) ?>" dir="ltr"><?= e(site_contact('phone')) ?></a></li>
          <?php if ($wa): ?><li><?= icon('whatsapp') ?><a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><?= e(__('nav.whatsapp')) ?></a></li><?php endif; ?>
          <li><?= icon('mail') ?><a href="mailto:<?= e(site_contact('email')) ?>" dir="ltr"><?= e(site_contact('email')) ?></a></li>
          <li><?= icon('pin') ?><span><?= e(site_contact('po_box')) ?></span></li>
        </ul>
      </div>
      <div class="footer-visit"><h2 class="footer-h"><?= e(__('site.visit')) ?></h2>
        <b><?= e(__('site.doha_qatar')) ?></b>
        <span class="coords" dir="ltr">25.2854° N · 51.5310° E</span>
        <span><?= e(__('site.local_time')) ?> <span class="num" data-local-time dir="ltr">—</span></span>
        <a class="link-arrow" href="<?= e($enquireUrl) ?>"<?= $enquireExt ? ' target="_blank" rel="noopener"' : '' ?>><?= e(__('site.plan_visit')) ?> <?= icon('arrow-up-right') ?></a>
      </div>
    </div>
    <div class="footer-wordmark-wrap"><p class="footer-wordmark" aria-hidden="true" dir="ltr" lang="en">SK Arabians</p></div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> <?= e(__('site.brand')) ?> · <?= e(__('site.cr')) ?> <span dir="ltr"><?= e(setting('company.cr', '231961')) ?></span> · <?= e(__('site.est', ['year' => $est])) ?></span><a href="<?= e(url('/portal/login')) ?>" rel="nofollow"><?= e(__('site.staff_login')) ?></a></div>
  </div>
</footer>
<?php if ($wa): ?><a class="wa-float" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="<?= e(__('nav.whatsapp')) ?>"><?= icon('whatsapp') ?></a><?php endif; ?>
<script type="application/json" id="sk-site-cfg"><?= json_encode(['viewer' => __('site.photo_viewer'), 'close' => __('common.close'), 'prev' => __('site.previous'), 'next' => __('site.next'), 'of' => __('site.photo_of')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php if ($isHome): // the homepage's scroll scenes (pinned film strip, gold threads): GSAP 3.12.5, self-hosted ?>
<script src="<?= e(asset('vendor/gsap/gsap.min.js')) ?>" defer></script>
<script src="<?= e(asset('vendor/gsap/ScrollTrigger.min.js')) ?>" defer></script>
<?php endif; ?>
<script src="<?= e(asset('js/site.js')) ?>" defer></script>
<?php if ($chatOn): ?>
<script type="application/json" id="sk-chat-cfg"><?= json_encode([
    'url' => site_url('assistant'), 'token' => csrf_token(), 'max' => \App\Services\SiteAssistant::MAX_MESSAGE,
    'suggest' => [__('site.chat_q1'), __('site.chat_q2'), __('site.chat_q3')],
    't' => [
        'open' => __('site.chat_open'), 'title' => __('site.chat_title'), 'sub' => __('site.chat_sub'), 'hello' => __('site.chat_hello'),
        'input' => __('site.chat_input'), 'placeholder' => __('site.chat_placeholder'), 'send' => __('site.chat_send'), 'close' => __('common.close'),
        'fresh' => __('site.chat_new'), 'note' => __('site.chat_note'), 'thinking' => __('site.chat_thinking'), 'horse' => __('site.chat_horse_page'),
        'whatsapp' => __('site.chat_whatsapp'), 'inquiry' => __('site.chat_inquiry'), 'error' => __('site.chat_error'),
        'expired' => __('site.chat_expired'), 'offline' => __('site.chat_offline'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= e(asset('js/ai-chat.js')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
