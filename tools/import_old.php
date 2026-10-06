<?php
/**
 * Imports the old SK Arabians portal database (read only) into the new system.
 *
 *   php tools/import_old.php                 PREVIEW: does the whole import, then undoes it; writes the migration report
 *   php tools/import_old.php --commit        real import (run only after the Owner has reviewed a preview)
 *   php tools/import_old.php --schema        only show which old tables / columns were found
 *
 * The old database connection is read from config.php → 'old_db', or given on the command line:
 *   --host=localhost --db=cpaneluser_oldportal --user=cpaneluser_old --pass=secret
 * The report is in the portal: Administration → Migration report (Owner only).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Services\OldImport;

$opt = getopt('', ['commit', 'schema', 'host::', 'db::', 'user::', 'pass::', 'port::']);
$c = Config::get('old_db') ?: [];
foreach (['host' => 'host', 'db' => 'name', 'user' => 'user', 'pass' => 'pass', 'port' => 'port'] as $k => $f) {
    if (isset($opt[$k]) && $opt[$k] !== false) {
        $c[$f] = $opt[$k];
    }
}
if (empty($c['name']) || empty($c['user'])) {
    fwrite(STDERR, "Old database not set. Add 'old_db' => ['host' => 'localhost', 'name' => '...', 'user' => '...', 'pass' => '...'] to config.php or pass --db= --user= --pass=\n");
    exit(1);
}
try {
    $src = OldImport::connect($c);
} catch (PDOException $e) {
    fwrite(STDERR, 'Cannot connect to the old database: ' . $e->getMessage() . "\n");
    exit(1);
}
$commit = isset($opt['commit']);
$import = new OldImport($src, $commit, OldImport::overridesFile());
if (isset($opt['schema'])) {
    $r = (new ReflectionMethod($import, 'discover'));
    $r->setAccessible(true);
    $r->invoke($import);
    $p = (new ReflectionProperty($import, 'found'));
    foreach ($p->getValue($import) as $entity => [$table, $cols]) {
        echo str_pad($entity, 12), $table, "\n";
        foreach ($cols as $f => $col) {
            echo '             ', str_pad($f, 16), '← ', $col, "\n";
        }
    }
    exit(0);
}
echo $commit ? "IMPORTING (this changes the new database)...\n" : "PREVIEW (nothing is changed)...\n";
$res = $import->run();
foreach ($res['counts'] as $entity => $n) {
    echo str_pad($entity, 14), $n, "\n";
}
echo "\nReport: {$res['report']} lines, run {$res['run']}. Open Administration → Migration report in the portal.\n";
if (!$commit) {
    echo "When the Owner has reviewed it, run again with --commit.\n";
}
