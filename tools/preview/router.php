<?php
/**
 * Router for PHP's built-in server: php -S 127.0.0.1:8080 -t public tools/preview/router.php
 * Serves the public assets as files, the preview photos from $PREVIEW_PHOTOS, and every other address through the
 * real front controller with the database replaced by fixtures (stubs/DB.php, stubs/SiteData.php).
 */
declare(strict_types=1);

$uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$public = dirname(__DIR__, 2) . '/public';

if (str_starts_with($uri, '/preview-media/')) {
    $dir = getenv('PREVIEW_PHOTOS') ?: __DIR__ . '/photos';
    $file = $dir . '/' . basename(rawurldecode(substr($uri, 15)));
    if (!preg_match('/^[\w.-]+\.(jpe?g|png|webp|svg)$/i', basename($file)) || !is_file($file)) {
        http_response_code(404);
        exit('not found');
    }
    header('Content-Type: ' . (str_ends_with(strtolower($file), '.png') ? 'image/png' : (str_ends_with(strtolower($file), '.webp') ? 'image/webp' : (str_ends_with(strtolower($file), '.svg') ? 'image/svg+xml' : 'image/jpeg'))));
    header('Cache-Control: public, max-age=86400');
    readfile($file);
    exit;
}
if ($uri !== '/' && is_file($public . $uri)) {
    return false; // a real file under public/: the server sends it
}

$_SERVER['SCRIPT_NAME'] = '/index.php'; // links are built from the site root, not from tools/preview/
putenv('SKA_CONFIG=' . __DIR__ . '/config.php');
require __DIR__ . '/stubs/DB.php';
require __DIR__ . '/stubs/SiteData.php';
require $public . '/index.php';
