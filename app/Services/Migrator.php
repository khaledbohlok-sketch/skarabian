<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Versioned database migrations (database/migrations/NNN_name.sql), applied once each and recorded. */
final class Migrator
{
    public static function dir(): string
    {
        return APP_ROOT . '/database/migrations';
    }

    public static function ensureTable(): void
    {
        DB::pdo()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    public static function pending(): array
    {
        self::ensureTable();
        $done = DB::column('SELECT version FROM schema_migrations');
        $files = glob(self::dir() . '/*.sql') ?: [];
        sort($files);
        return array_values(array_filter($files, fn ($f) => !in_array(basename($f), $done, true)));
    }

    /** @return string[] log lines */
    public static function run(): array
    {
        $log = [];
        foreach (self::pending() as $file) {
            $name = basename($file);
            $optional = str_contains($name, '.optional.');
            try {
                foreach (self::statements((string) file_get_contents($file)) as $sql) {
                    DB::pdo()->exec($sql);
                }
                DB::insert('schema_migrations', ['version' => $name, 'applied_at' => date('Y-m-d H:i:s')]);
                $log[] = "applied  $name";
            } catch (\PDOException $e) {
                if (!$optional) {
                    throw new \RuntimeException("Migration $name failed: " . $e->getMessage(), 0, $e);
                }
                DB::insert('schema_migrations', ['version' => $name, 'applied_at' => date('Y-m-d H:i:s')]);
                $log[] = "skipped  $name (optional: " . $e->getMessage() . ')';
            }
        }
        return $log;
    }

    /** Splits a SQL file into statements (statements end with ";" at end of line; "--" comments removed). */
    public static function statements(string $sql): array
    {
        $lines = preg_split('/\R/', $sql);
        $buf = '';
        $out = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, '--')) {
                continue;
            }
            $buf .= $line . "\n";
            if (str_ends_with($trim, ';')) {
                $out[] = trim(substr(trim($buf), 0, -1));
                $buf = '';
            }
        }
        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }
        return $out;
    }
}
