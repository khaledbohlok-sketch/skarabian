<?php
/**
 * Application bootstrap: autoloader, configuration, error handling.
 * Used by the web front controller (public/index.php), cron and CLI tools.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_START', microtime(true));

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = APP_ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

// Optional composer packages (e.g. mpdf/mpdf for server-side PDF). The app works without them.
if (is_file(APP_ROOT . '/vendor/autoload.php')) {
    require APP_ROOT . '/vendor/autoload.php';
}

require APP_ROOT . '/app/helpers.php';

App\Core\Config::load(getenv('SKA_CONFIG') ?: APP_ROOT . '/config/config.php');

date_default_timezone_set(App\Core\Config::get('app.timezone', 'Asia/Qatar'));
mb_internal_encoding('UTF-8');

App\Core\ErrorHandler::register();
