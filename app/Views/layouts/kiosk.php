<!doctype html>
<html lang="<?= e(lang()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0f1a33">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title><?= e($title ?? '') ?> · <?= e(__('app.portal_name')) ?></title>
<link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(asset('img/icon-192.png')) ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= e(asset('img/icon-192.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/brand.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/attendance-scan.css')) ?>">
</head>
<body class="kiosk">
<?= $content ?>
<script src="<?= e(asset('js/attendance-scan.js')) ?>" defer></script>
</body>
</html>
