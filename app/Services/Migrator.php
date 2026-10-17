<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Versioned database migrations (database/migrations/NNN_name.sql), applied once each and recorded. */
final class Migrator
{
    /**
     * Errors that mean a statement was already applied: table exists (1050), duplicate column (1060), duplicate index
     * name (1061). Table changes cannot be rolled back, so after an update stopped half-way these statements are
     * skipped and the update can simply be applied again.
     */
    private const ALREADY_DONE = [1050, 1060, 1061];

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

    /** How many are waiting, read-only: Settings and the Owner's dashboard ask on every visit (an update was uploaded but not applied). */
    public static function pendingCount(): int
    {
        try {
            $done = DB::column('SELECT version FROM schema_migrations');
        } catch (\PDOException) {
            return 0; // not installed yet: the installer applies them
        }
        return count(array_diff(array_map('basename', glob(self::dir() . '/*.sql') ?: []), $done));
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
                    // query()+closeCursor() rather than exec(): a migration occasionally ends with a
                    // diagnostic SELECT (e.g. "rows left unmapped after this backfill, for review"), and
                    // under this connection's native (non-emulated) prepares, an unclosed SELECT result
                    // from exec() blocks the next statement with "unbuffered queries are active".
                    try {
                        DB::pdo()->query($sql)->closeCursor();
                    } catch (\PDOException $e) {
                        if (!in_array((int) ($e->errorInfo[1] ?? 0), self::ALREADY_DONE, true)) {
                            throw $e;
                        }
                        $log[] = "  already there ($name): " . mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 90);
                    }
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
        DB::forgetColumns(); // the tables may have new columns now
        return $log;
    }

    /** Splits a SQL file into statements (statements end with ";" at end of line; "--" comments removed). */
    public static function statements(string $sql): array
    {
        // Plain \r\n|\r|\n only: PCRE's \R (without the /u modifier) also treats the lone byte 0x85 as a
        // line break, which is the second byte of several Arabic letters' UTF-8 encoding (e.g. م) —
        // splitting a multi-byte character in two and corrupting it once the fragments are rejoined.
        $lines = preg_split('/\r\n|\r|\n/', $sql);
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
