<?php
/**
 * Printouts on the official letterhead. The content arrives in one long page (#print-src, also what prints when
 * scripts can't run); print.js then lays it out on A4 pages made from #print-page-tpl, each with the letterhead,
 * the printout's reference and QR code (when it has one), "Page n of N" and the footer.
 */
$lang = $docLang ?? lang();
$dir = $lang === 'ar' ? 'rtl' : 'ltr';
$stamp = $stamp ?? null;
$vq = $stamp ? '<div class="vq"><div class="meta"><span class="num">' . e($stamp['ref']) . '</span><br><span dir="' . $dir . '">' . e(fmt_date($stamp['at'], true)) . '</span>'
    . '<br><span class="vq-hint">' . e(__('print.scan_to_verify')) . '</span></div><div class="vqr">' . $stamp['qr'] . '</div></div>' : '';
// the letterhead follows the printout's language, as on the Studio documents: English = the SK mark with
// "Sk.Arabian" under it (top left); Arabic = the full logo with the Arabic name (top right)
$logo = $lang === 'ar'
    ? '<img class="lh-logo" src="' . e(asset('img/sk-logo.png')) . '" alt="اس كي ارابيان للتجارة — Sk.Arabian">'
    : '<div class="lh-v1"><img src="' . e(asset('img/sk-mark.png')) . '" alt="SK Arabian"><span>Sk.Arabian</span></div>';
$head = '<div class="lh-head' . ($lang === 'ar' ? ' rtl' : '') . '">' . $logo . $vq . '</div>';
// the DOCUMENT contact details (doc_contact), never the website's; the separators are drawn by studio-doc.css
$foot = '<div class="lh-foot" dir="ltr" lang="en"><span>C.R.: ' . e(doc_contact('cr')) . '</span><span>Mob.: ' . e(doc_contact('mobile')) . '</span><span>Email: '
    . e(doc_contact('email')) . '</span><span>P.O.Box: ' . e(doc_contact('po_box')) . ', ' . e(doc_contact('city')) . '</span></div>';
$note = e(($doc['title'] ?? $title ?? '') . (!empty($doc['by']) ? ' · ' . $doc['by'] : ''));
?><!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= $dir ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= e($title ?? 'SK Arabians') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/studio-doc.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/print.css')) ?>">
</head>
<body class="print-body">
<div class="print-toolbar no-print">
  <button type="button" class="pbtn" data-print><?= e(__('common.print_save_pdf')) ?></button>
  <button type="button" class="pbtn ghost" data-back><?= e(__('common.back')) ?></button>
  <?php if ($stamp): ?><span class="ptb-info" dir="ltr"><?= e($stamp['ref']) ?> <span id="print-npages"></span></span><?php endif; ?>
</div>
<?= $banner ?? '' ?>
<div class="print-fit"><div class="pages<?= !empty($voided) ? ' doc-void' : '' ?>" id="print-pages">
  <div class="page flowpage" dir="<?= $dir ?>" lang="<?= e($lang) ?>">
    <img class="lh-wm" src="<?= e(asset('img/sk-mark.png')) ?>" alt="">
    <?= $head ?>
    <main class="page-body" id="print-src"><?= $content ?></main>
    <?= $foot ?>
  </div>
</div></div>
<template id="print-page-tpl" data-page-label="<?= e(__('print.page_of')) ?>" data-cont-label="<?= e(__('print.continued')) ?>" data-pages-label="<?= e(__('print.n_pages')) ?>">
  <div class="page listpage" dir="<?= $dir ?>" lang="<?= e($lang) ?>">
    <img class="lh-wm" src="<?= e(asset('img/sk-mark.png')) ?>" alt="">
    <?= $head ?>
    <div class="pbody"><div class="flow"></div></div>
    <div class="lh-note"><span><?= $note ?></span><span class="pnum"></span></div>
    <?= $foot ?>
  </div>
</template>
<script src="<?= e(asset('js/print.js')) ?>" defer></script>
</body>
</html>
