<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Append-only, tamper-evident activity log. Each entry carries an HMAC over its content and the previous
 * entry's hash, so any edit or deletion breaks the chain (see verify()). Rows are never updated or deleted.
 */
final class Audit
{
    /** Current signing scheme: 2 = HMAC key derived from app.key with HKDF ('audit-log'). */
    public const HASH_VERSION = 2;

    private const SECRET_FIELDS = ['password', 'password_hash', 'totp_secret', '_csrf', 'qid_no', 'passport_no', 'bank_iban', 'bank_name', 'account_no'];

    public static function log(
        string $action,
        ?string $module = null,
        ?string $recordType = null,
        int|string|null $recordId = null,
        ?array $old = null,
        ?array $new = null,
        ?string $summary = null,
        ?array $actor = null
    ): void {
        try {
            $actor ??= Auth::user();
            $row = [
                'user_id'     => $actor['id'] ?? null,
                'user_name'   => $actor['name'] ?? null,
                'action'      => $action,
                'module'      => $module,
                'record_type' => $recordType,
                'record_id'   => $recordId !== null ? (int) $recordId : null,
                'summary'     => $summary !== null ? mb_substr($summary, 0, 255) : null,
                'old_values'  => $old !== null ? json_encode(self::scrub($old), JSON_UNESCAPED_UNICODE) : null,
                'new_values'  => $new !== null ? json_encode(self::scrub($new), JSON_UNESCAPED_UNICODE) : null,
                'ip'          => PHP_SAPI === 'cli' ? 'cli' : Request::ip(),
                'device'      => PHP_SAPI === 'cli' ? 'cron' : Request::device(),
                'user_agent'  => PHP_SAPI === 'cli' ? null : Request::userAgent(),
                'created_at'  => date('Y-m-d H:i:s'),
            ];
            if (DB::hasColumn('activity_log', 'hash_v')) {
                $row['hash_v'] = self::HASH_VERSION; // signed with the derived audit key (older rows keep version 1)
            }
            DB::transaction(function () use ($row) {
                // Serialise writers so the chain stays linear
                $prev = DB::value('SELECT hash FROM activity_log ORDER BY id DESC LIMIT 1 FOR UPDATE');
                $row['prev_hash'] = $prev;
                $row['hash'] = self::hashRow($row);
                DB::insert('activity_log', $row);
            });
        } catch (\Throwable $e) {
            // Logging must never break the user's action, but a failure is itself recorded privately.
            ErrorHandler::log($e, ['audit_action' => $action]);
        }
    }

    public static function hashRow(array $row): string
    {
        $fields = ['user_id', 'user_name', 'action', 'module', 'record_type', 'record_id', 'summary', 'old_values', 'new_values', 'ip', 'device', 'created_at', 'prev_hash'];
        $parts = [];
        foreach ($fields as $f) {
            $v = $row[$f] ?? null;
            if (($f === 'old_values' || $f === 'new_values') && is_string($v)) {
                // MySQL's JSON type may re-format whitespace and reorder object keys; hash a canonical form
                $v = json_encode(self::sortKeys(json_decode($v, true)), JSON_UNESCAPED_UNICODE);
            }
            $parts[] = $v === null ? "\0" : (string) $v;
        }
        // Version 1 rows were signed with the app key itself; version 2 with a key derived from it for this log only
        return Crypto::hmac(implode("\x1f", $parts), (int) ($row['hash_v'] ?? 1) >= 2 ? 'audit-log' : null);
    }

    /** Recursively sorts object keys so JSON hashing is independent of MySQL's stored key order. */
    private static function sortKeys(mixed $v): mixed
    {
        if (!is_array($v)) {
            return $v;
        }
        $v = array_map([self::class, 'sortKeys'], $v);
        if (!array_is_list($v)) {
            ksort($v);
        }
        return $v;
    }

    /** Walks the chain. Returns [ok(bool), checked(int), firstBrokenId(?int)]. */
    public static function verify(int $limit = 0): array
    {
        $prev = null;
        $checked = 0;
        $lastId = 0;
        while (true) {
            $rows = DB::all('SELECT * FROM activity_log WHERE id > ? ORDER BY id ASC LIMIT 2000', [$lastId]);
            if (!$rows) {
                break;
            }
            foreach ($rows as $r) {
                if ($r['prev_hash'] !== $prev || !hash_equals(self::hashRow($r), (string) $r['hash'])) {
                    return [false, $checked, (int) $r['id']];
                }
                $prev = $r['hash'];
                $lastId = (int) $r['id'];
                $checked++;
                if ($limit && $checked >= $limit) {
                    return [true, $checked, null];
                }
            }
        }
        return [true, $checked, null];
    }

    /** Diff of changed fields only, for update entries. */
    public static function diff(array $old, array $new): array
    {
        $o = [];
        $n = [];
        foreach ($new as $k => $v) {
            if (in_array($k, ['updated_at'], true)) {
                continue;
            }
            $ov = $old[$k] ?? null;
            if ((string) $ov !== (string) $v) {
                $o[$k] = $ov;
                $n[$k] = $v;
            }
        }
        return [$o, $n];
    }

    private static function scrub(array $data): array
    {
        foreach ($data as $k => $v) {
            if (in_array($k, self::SECRET_FIELDS, true) && $v !== null && $v !== '') {
                $data[$k] = '[protected]';
            }
        }
        return $data;
    }
}
