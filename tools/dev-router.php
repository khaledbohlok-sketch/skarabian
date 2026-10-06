<?php
// Router for PHP's built-in server (local development only):
//   php -S 127.0.0.1:8080 -t public tools/dev-router.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$public = dirname(__DIR__) . '/public';
if ($path !== '/' && is_file($public . $path)) {
    return false; // let the server send the static file
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $public . '/index.php';
