<?php
/** Configuration of the database-free preview (tools/preview/router.php). */
$storage = getenv('PREVIEW_STORAGE') ?: sys_get_temp_dir() . '/skarabian-preview';
foreach (['sessions', 'logs', 'cache'] as $d) {
    if (!is_dir($storage . '/' . $d)) {
        @mkdir($storage . '/' . $d, 0770, true);
    }
}
return [
    'app' => [
        'url' => getenv('PREVIEW_URL') ?: 'http://127.0.0.1:8080',
        'timezone' => 'Asia/Qatar',
        'default_lang' => 'en',
        'debug' => true,
        'key' => str_repeat('0', 64),
    ],
    'storage_path' => $storage,
    'security' => ['hsts' => false, 'session_timeout' => 1800],
    'db' => [],
];
