<!doctype html>
<html lang="<?= e(lang()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= e($title ?? 'SK Arabians') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/studio-doc.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/print.css')) ?>"></head>
<body class="print-body"><div class="print-toolbar no-print"><button type="button" class="pbtn" data-print><?= e(__('common.print')) ?></button><button type="button" class="pbtn ghost" data-back><?= e(__('common.back')) ?></button></div>
<?= $content ?>
<script src="<?= e(asset('js/print.js')) ?>" defer></script></body></html>
