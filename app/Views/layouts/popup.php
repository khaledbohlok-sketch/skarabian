<?php use App\Core\Session; $flashes = Session::takeFlash(); ?><!doctype html>
<html lang="<?= e(lang()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($title ?? '') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/portal.css')) ?>">
</head>
<body class="popup" data-base="<?= e(url('/')) ?>">
<main class="content">
<?php foreach ($flashes as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach; ?>
<?= $content ?>
</main>
<script src="<?= e(asset('js/portal.js')) ?>" defer></script>
</body>
</html>
