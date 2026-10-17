<?php
declare(strict_types=1);

namespace App\Core;

/**
 * The preview's database: none. Settings come from the fixtures; everything else answers "nothing here", so the
 * website renders (SiteVisits sees no site_visits table and keeps quiet, a horse that does not exist is a 404).
 * Writing (an enquiry, a log entry) is not possible in the preview.
 */
final class DB
{
    public static function pdo(): \PDO
    {
        throw new \RuntimeException('The preview has no database.');
    }

    public static function setPdo(?\PDO $pdo): void
    {
    }

    public static function run(string $sql, array $params = []): \PDOStatement
    {
        throw new \RuntimeException('The preview has no database (write attempted).');
    }

    public static function all(string $sql, array $params = []): array
    {
        return [];
    }

    public static function row(string $sql, array $params = []): ?array
    {
        if (str_contains($sql, 'FROM news WHERE slug = ? AND published = 1')) {
            return \App\Services\SiteData::newsItem((string) ($params[0] ?? ''));
        }
        return null;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        return null;
    }

    public static function column(string $sql, array $params = []): array
    {
        return [];
    }

    public static function pairs(string $sql, array $params = []): array
    {
        return str_contains($sql, 'settings') ? \App\Services\SiteData::fixtures()['settings'] : [];
    }

    public static function insert(string $table, array $data): int
    {
        return 1;
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        return 0;
    }

    public static function transaction(callable $fn): mixed
    {
        return $fn();
    }

    public static function hasColumn(string $table, string $column): bool
    {
        return false;
    }

    public static function columns(string $table): array
    {
        return [];
    }

    public static function forgetColumns(): void
    {
    }

    public static function emptyValue(string $table, string $column): mixed
    {
        return null;
    }

    public static function assertIdent(string $name): void
    {
    }

    public static function in(array $values, string $prefix, array &$params): string
    {
        $out = [];
        foreach (array_values($values) as $i => $v) {
            $params[$prefix . $i] = $v;
            $out[] = ':' . $prefix . $i;
        }
        return implode(', ', $out);
    }
}
