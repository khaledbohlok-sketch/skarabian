<!doctype html>
<html lang="<?= e($docLang ?? lang()) ?>" dir="<?= ($docLang ?? lang()) === 'ar' ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= e($title ?? 'SK Arabians') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;600&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/print.css')) ?>">
</head>
<body class="print-body">
<div class="print-toolbar no-print">
  <button type="button" class="pbtn" data-print><?= e(__('common.print_save_pdf')) ?></button>
  <button type="button" class="pbtn ghost" data-back><?= e(__('common.back')) ?></button>
</div>
<div class="page">
  <?= \App\Core\View::partial('partials/letterhead', ['version' => $letterhead ?? 2]) ?>
  <main class="page-body"><?= $content ?></main>
  <?= \App\Core\View::partial('partials/letterfoot') ?>
</div>
<script src="<?= e(asset('js/print.js')) ?>" defer></script>
</body>
</html>
