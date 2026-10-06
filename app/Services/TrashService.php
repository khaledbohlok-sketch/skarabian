<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Resources\Registry;
use App\Resources\Resource;

/** Soft delete: items go to Trash; only the Owner can restore or permanently empty it. */
final class TrashService
{
    /** Returns 'deleted', 'approval' (financial: waits for Owner/GM) or throws when linked records exist. */
    public static function delete(Resource $res, array $row): string
    {
        $links = $res->linkedCounts((int) $row['id']);
        if ($links) {
            $parts = [];
            foreach ($links as $label => $n) {
                $parts[] = "$label ($n)";
            }
            throw new \DomainException(__('common.delete_blocked', ['links' => implode(', ', $parts)]));
        }
        if ($res->financial) {
            Approvals::request('delete_financial', $res->recordType, (int) $row['id'], __('approvals.delete_record', ['label' => $res->label($row)]), isset($row['amount_qar']) ? (float) $row['amount_qar'] : null, ['resource' => $res->key]);
            return 'approval';
        }
        self::softDelete($res, $row);
        return 'deleted';
    }

    public static function softDelete(Resource $res, array $row): void
    {
        DB::transaction(function () use ($res, $row) {
            DB::update($res->table, ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);
            DB::insert('trash', [
                'record_type' => $res->recordType, 'record_table' => $res->table, 'record_id' => $row['id'],
                'label' => mb_substr(__($res->singular) . ': ' . $res->label($row), 0, 255), 'module' => $res->module, 'deleted_by' => Auth::id() ?? 0,
            ]);
            $res->afterDelete($row);
            Audit::log('delete', $res->module, $res->recordType, $row['id'], $row, null, $res->label($row));
        });
        Cache::bump();
    }

    /** Approval handler for deleting financial records. */
    public static function approveDelete(int $recordId, array $payload, array $approval): void
    {
        $res = Registry::get($payload['resource'] ?? '');
        if (!$res) {
            return;
        }
        $row = DB::row('SELECT * FROM `' . $res->table . '` WHERE id = ? AND deleted_at IS NULL', [$recordId]);
        if ($row) {
            self::softDelete($res, $row);
        }
    }

    public static function restore(int $trashId): void
    {
        $t = DB::row('SELECT * FROM trash WHERE id = ?', [$trashId]);
        if (!$t) {
            return;
        }
        DB::assertIdent($t['record_table']);
        DB::transaction(function () use ($t) {
            DB::run('UPDATE `' . $t['record_table'] . '` SET deleted_at = NULL WHERE id = ?', [$t['record_id']]);
            DB::run('DELETE FROM trash WHERE id = ?', [$t['id']]);
            Audit::log('restore', $t['module'], $t['record_type'], $t['record_id'], null, null, $t['label']);
        });
        Cache::bump();
    }

    /** Permanently removes one item. Returns false when the database still has references to it. */
    public static function purge(int $trashId): bool
    {
        $t = DB::row('SELECT * FROM trash WHERE id = ?', [$trashId]);
        if (!$t) {
            return true;
        }
        DB::assertIdent($t['record_table']);
        try {
            DB::transaction(function () use ($t) {
                DB::run('DELETE FROM `' . $t['record_table'] . '` WHERE id = ? AND deleted_at IS NOT NULL', [$t['record_id']]);
                DB::run('DELETE FROM trash WHERE id = ?', [$t['id']]);
                Audit::log('purge', $t['module'], $t['record_type'], $t['record_id'], null, null, $t['label']);
            });
            return true;
        } catch (\PDOException) {
            return false;
        }
    }
}
