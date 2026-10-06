<?php
/**
 * Applies pending database migrations and seeds reference data.
 *   php tools/migrate.php            migrate + seed
 *   php tools/migrate.php --status   list pending migrations
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Migrator;
use App\Services\Seeder;

if (in_array('--status', $argv, true)) {
    foreach (Migrator::pending() as $f) {
        echo 'pending  ', basename($f), PHP_EOL;
    }
    exit(0);
}
foreach (Migrator::run() as $line) {
    echo $line, PHP_EOL;
}
foreach (Seeder::run() as $line) {
    echo 'seeded   ', $line, PHP_EOL;
}
echo "Done.\n";
