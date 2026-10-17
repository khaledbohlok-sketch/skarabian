<!doctype html>
<?php $pl = ($docLang ?? null) === 'ar' || ($docLang ?? null) === 'en' ? $docLang : lang(); // a printed document: its own language ?>
<html lang="<?= e($pl) ?>" dir="<?= $pl === 'ar' ? 'rtl' : 'ltr' ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= e($title ?? 'SK Arabians') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/brand.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/studio-doc.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/print.css')) ?>"></head>
<body class="print-body"><div class="print-toolbar no-print"><button type="button" class="pbtn" data-print><?= e(__('common.print')) ?></button><button type="button" class="pbtn ghost" data-back><?= e(__('common.back')) ?></button></div>
<div class="print-fit"><?= $content ?></div>
<script src="<?= e(asset('js/print.js')) ?>" defer></script></body></html>
