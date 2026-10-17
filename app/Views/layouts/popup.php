<?php use App\Core\Session; $flashes = Session::takeFlash(); ?><!doctype html>
<html lang="<?= e(lang()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>" data-theme="<?= e(ui_theme()) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($title ?? '') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/brand.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/portal.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/modal.css')) ?>">
<?php if (\App\Services\Ai::enabled()): ?><link rel="stylesheet" href="<?= e(asset('css/ai.css')) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('vendor/flatpickr/flatpickr.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/flatpickr-theme.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/portal-cinematic.css')) ?>" media="<?= e(theme_media()) ?>" id="theme-dark">
</head>
<body class="popup portal" data-base="<?= e(url('/')) ?>">
<main class="content">
<?php foreach ($flashes as $f): ?><div class="alert alert-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></div><?php endforeach; ?>
<?= $content ?>
</main>
<?= \App\Core\View::partial('partials/modal') ?>
<script src="<?= e(asset('vendor/flatpickr/flatpickr.min.js')) ?>" defer></script>
<script src="<?= e(asset('vendor/flatpickr/l10n/ar.js')) ?>" defer></script>
<script src="<?= e(asset('js/modal.js')) ?>" defer></script>
<script src="<?= e(asset('js/dropdown.js')) ?>" defer></script>
<script src="<?= e(asset('js/portal.js')) ?>" defer></script>
<?php if (\App\Services\Ai::enabled()): ?><script src="<?= e(asset('js/ai.js')) ?>" defer></script><?php endif; ?>
<script src="<?= e(asset('js/datepicker.js')) ?>" defer></script>
<?php foreach (($scripts ?? []) as $js): ?><script src="<?= e(asset($js)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
