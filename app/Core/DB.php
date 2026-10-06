<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/** Thin PDO wrapper. Every query uses prepared statements with bound parameters. */
final class DB
{
    private static ?PDO $pdo = null;
    private static int $txDepth = 0;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = Config::get('db');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'] ?? 3306, $c['name'], $c['charset'] ?? 'utf8mb4');
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            self::$pdo->exec("SET time_zone = '" . (new \DateTime())->format('P') . "'");
        }
        return self::$pdo;
    }

    public static function setPdo(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $key = is_int($k) ? $k + 1 : (str_starts_with((string) $k, ':') ? $k : ':' . $k);
            $type = match (true) {
                is_int($v)  => PDO::PARAM_INT,
                is_bool($v) => PDO::PARAM_BOOL,
                $v === null => PDO::PARAM_NULL,
                default     => PDO::PARAM_STR,
            };
            $stmt->bindValue($key, $v, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function row(string $sql, array $params = []): ?array
    {
        $r = self::run($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function column(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Key => value pairs from a two-column query. */
    public static function pairs(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function insert(string $table, array $data): int
    {
        self::assertIdent($table);
        $cols = array_keys($data);
        array_walk($cols, [self::class, 'assertIdent']);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (:' . implode(',:', $cols) . ')';
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        self::assertIdent($table);
        if (!$data) {
            return 0;
        }
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            self::assertIdent($col);
            $sets[] = "`$col` = :set_$col";
            $params["set_$col"] = $val;
        }
        $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE $where";
        return self::run($sql, $params + $whereParams)->rowCount();
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        if (self::$txDepth === 0) {
            $pdo->beginTransaction();
        }
        self::$txDepth++;
        try {
            $result = $fn();
            self::$txDepth--;
            if (self::$txDepth === 0) {
                $pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            self::$txDepth--;
            if (self::$txDepth === 0 && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static array $columns = [];

    public static function hasColumn(string $table, string $column): bool
    {
        self::assertIdent($table);
        if (!isset(self::$columns[$table])) {
            self::$columns[$table] = array_column(self::all("SHOW COLUMNS FROM `$table`"), 'Field');
        }
        return in_array($column, self::$columns[$table], true);
    }

    /** Guard for identifiers that are interpolated into SQL (tables/columns come from code, never from users). */
    public static function assertIdent(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Invalid identifier: ' . $name);
        }
    }

    /** Builds "IN (:p0,:p1)" placeholders. */
    public static function in(array $values, string $prefix, array &$params): string
    {
        if (!$values) {
            return '(NULL)';
        }
        $ph = [];
        foreach (array_values($values) as $i => $v) {
            $ph[] = ":{$prefix}{$i}";
            $params["{$prefix}{$i}"] = $v;
        }
        return '(' . implode(',', $ph) . ')';
    }
}
