<?php
/**
 * Lists translation keys used in the code that are missing from lang/en.php or lang/ar.php.
 *   php tests/lang_check.php            report
 *   php tests/lang_check.php --keys     print every static key used
 */
$root = dirname(__DIR__);
$keys = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app'));
foreach ($it as $f) {
    if ($f->getExtension() !== 'php' || in_array($f->getFilename(), ['Seeder.php', 'DefaultRoles.php'], true)) {
        continue;
    }
    $src = file_get_contents($f->getPathname());
    // settings / config keys are not translations
    $src = preg_replace("/(setting|config|Settings::get|Settings::set|Config::get|Settings::float)\\(\\s*'[^']+'/", '$1(', $src);
    preg_match_all("/(?:__|Lang::get|ValidationException::one\\([^,]+,)\\s*\\(?\\s*'([a-z_]+\\.[a-z0-9_]+)'/", $src, $m);
    foreach ($m[1] as $k) {
        if (!str_ends_with($k, '_')) {
            $keys[$k] = true;
        }
    }
    // keys passed as 'label' => 'x.y', options, nav entries etc.
    preg_match_all("/'((?:common|nav|auth|site|horse|horses|health|diet|training|shows|breeding|embryos|hr|employees|attendance|leave|loans|finance|bills|accounts|parties|invoices|po|payroll|budgets|inventory|items|studio|cms|inbox|users|roles|settings|activity|approvals|validation|files|status|notify|timeline|ownership|account|app|verify|dashboard|reports|lookups|categories|news|gallery|currencies|trash|notes|security|backups|search|migration)\\.[a-z0-9_]+)'/", $src, $m2);
    foreach ($m2[1] as $k) {
        if (!str_ends_with($k, '_')) {
            $keys[$k] = true;
        }
    }
}
// Permission strings and cache keys that look like translation keys
$ignore = '/^(?:[a-z_]+\.(?:view|create|edit|delete|approve|export|print|sensitive|main|upload|public|scope)|site\.counters|(?:approvals|horse|ownership|payroll|breeding|po|invoices|inbox|leave|attendance|embryos)\.(?:status|cat|result)|health\.type|shows\.medal_prefix)$/';
// Prefix strings passed to status_badge()/enum columns (e.g. 'horse.status') are families, not keys
$keys = array_filter($keys, fn ($v, $k) => !preg_match($ignore, $k), ARRAY_FILTER_USE_BOTH);
ksort($keys);
if (in_array('--keys', $argv, true)) {
    echo implode("\n", array_keys($keys)), "\n";
    exit;
}
$en = require $root . '/lang/en.php';
$ar = require $root . '/lang/ar.php';
$missEn = array_diff(array_keys($keys), array_keys($en));
$missAr = array_diff(array_keys($keys), array_keys($ar));
$extraAr = array_diff(array_keys($en), array_keys($ar));
echo 'Keys used: ' . count($keys) . "\nMissing EN: " . count($missEn) . "\n" . implode("\n", $missEn) . "\nMissing AR: " . count($missAr) . "\n" . implode("\n", array_slice($missAr, 0, 400)) . "\nIn EN but not AR: " . count($extraAr) . "\n";
exit($missEn || $missAr ? 1 : 0);
