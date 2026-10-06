<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Config;
use App\Core\DB;

/**
 * Backups without shell access (cPanel shared hosting): the database is dumped through PDO to a gzip SQL file
 * and the uploaded files are zipped. Kept for 30 days in storage/backups (outside the public folder).
 * The off-server copy (Google Drive) and the daily schedule are done by the cron job.
 */
final class BackupService
{
    public static function dir(): string
    {
        $d = Config::storagePath('backups');
        if (!is_dir($d)) {
            @mkdir($d, 0750, true);
        }
        return $d;
    }

    /** Runs a full backup. Returns the file names created. */
    public static function run(string $by = 'manual'): array
    {
        @set_time_limit(0);
        $stamp = date('Ymd-His');
        $files = [self::dumpDatabase("db-$stamp.sql.gz")];
        if (class_exists(\ZipArchive::class)) {
            $z = self::zipUploads("files-$stamp.zip");
            if ($z) {
                $files[] = $z;
            }
        }
        self::prune((int) Config::get('backup.keep_days', 30));
        Audit::log('backup', 'settings', null, null, null, ['files' => $files, 'by' => $by], 'Backup created');
        return $files;
    }

    public static function dumpDatabase(string $name): string
    {
        $path = self::dir() . '/' . $name;
        $gz = gzopen($path, 'wb6');
        gzwrite($gz, "-- SK Arabians database backup " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        $pdo = DB::pdo();
        foreach (DB::column('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'') as $table) {
            DB::assertIdent($table);
            $create = DB::row("SHOW CREATE TABLE `$table`");
            gzwrite($gz, "DROP TABLE IF EXISTS `$table`;\n" . $create['Create Table'] . ";\n\n");
            $stmt = $pdo->query("SELECT * FROM `$table`", \PDO::FETCH_ASSOC);
            $batch = [];
            foreach ($stmt as $row) {
                $batch[] = '(' . implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)) . ')';
                if (count($batch) >= 200) {
                    gzwrite($gz, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                gzwrite($gz, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            gzwrite($gz, "\n");
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
        @chmod($path, 0640);
        return $name;
    }

    public static function zipUploads(string $name): ?string
    {
        $src = Config::storagePath('uploads');
        if (!is_dir($src)) {
            return null;
        }
        $zip = new \ZipArchive();
        if ($zip->open(self::dir() . '/' . $name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $zip->addFile($f->getPathname(), 'uploads/' . substr($f->getPathname(), strlen($src) + 1));
        }
        $zip->close();
        return $name;
    }

    public static function list(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/*.{gz,zip}', GLOB_BRACE) ?: [] as $f) {
            $out[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
        }
        usort($out, fn ($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    public static function path(string $name): ?string
    {
        if (!preg_match('/^(db|files)-\d{8}-\d{6}\.(sql\.gz|zip)$/', $name)) {
            return null;
        }
        $p = self::dir() . '/' . $name;
        return is_file($p) ? $p : null;
    }

    public static function prune(int $days): int
    {
        $n = 0;
        foreach (self::list() as $f) {
            if ($f['time'] < time() - $days * 86400 && @unlink(self::dir() . '/' . $f['name'])) {
                $n++;
            }
        }
        return $n;
    }

    /** Restore test: the newest database dump can be read to the end and contains every table. */
    public static function test(): array
    {
        $db = array_values(array_filter(self::list(), fn ($f) => str_starts_with($f['name'], 'db-')))[0] ?? null;
        if (!$db) {
            return [false, 'no backup'];
        }
        $gz = gzopen(self::dir() . '/' . $db['name'], 'rb');
        $tables = [];
        while (!gzeof($gz)) {
            $line = gzgets($gz, 1 << 20);
            if (preg_match('/^CREATE TABLE `([a-z_]+)`/', (string) $line, $m)) {
                $tables[] = $m[1];
            }
        }
        gzclose($gz);
        $missing = array_diff(DB::column("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"), $tables);
        return [!$missing, $db['name'] . ': ' . count($tables) . ' tables' . ($missing ? ', missing ' . implode(', ', $missing) : '')];
    }
}
