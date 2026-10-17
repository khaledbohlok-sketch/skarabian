<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Resources\Registry;
use App\Resources\Resource;

/**
 * Soft delete: items go to Trash; only the Owner can restore or permanently empty it.
 * The Owner's deletions happen at once; anyone else's wait for the Owner's approval (Approvals → "Delete").
 */
final class TrashService
{
    /**
     * Records with a document number (BILL-…, INV-…, PO-…, SKA-…, EMB-…, SK-…): never removed for good, so a number stays
     * on record as deleted (the Number register lists it) and can never be given again.
     */
    public const NUMBERED = ['bills', 'invoices', 'purchase_orders', 'documents', 'printouts', 'embryos', 'employees'];

    /** Returns 'deleted' (the Owner), 'approval' (anyone else: the Owner decides) or throws when linked records exist. */
    public static function delete(Resource $res, array $row): string
    {
        self::assertUnlinked($res, (int) $row['id']);
        // anyone but the Owner asks the Owner; the Owner too asks another Owner when a second approver is required
        if (!Auth::isOwner() || (Approvals::ownerSecond() && Approvals::otherOwner())) {
            // the request says what kind of record it is ("Payment: BILL-202610-0007 — …"), not just its label
            self::requestDeletion($res->recordType, (int) $row['id'], \App\Core\Lang::get($res->singular, [], 'en') . ': ' . $res->label($row),
                ['kind' => 'record', 'resource' => $res->key, 'module' => $res->module, 'url' => $res->url((int) $row['id'])],
                isset($row['amount_qar']) ? (float) $row['amount_qar'] : null);
            return 'approval';
        }
        self::softDelete($res, $row);
        return 'deleted';
    }

    private static function assertUnlinked(Resource $res, int $id): void
    {
        $links = $res->linkedCounts($id);
        if ($links) {
            $parts = [];
            foreach ($links as $label => $n) {
                $parts[] = "$label ($n)";
            }
            throw new \DomainException(__('common.delete_blocked', ['links' => implode(', ', $parts)]));
        }
    }

    /** A deletion asked for by someone other than the Owner. The payload says what to delete ('kind') and where the
     * record is ('url', for the Approvals card); nothing is removed until the Owner approves. */
    public static function requestDeletion(string $recordType, int $recordId, string $label, array $payload, ?float $amount = null): void
    {
        Approvals::request('delete', $recordType, $recordId, __('approvals.delete_record', ['label' => $label]), $amount, $payload);
    }

    /** Approval handler: the Owner approved a deletion. It is checked again, since things may have changed meanwhile. */
    public static function approveDeletion(int $recordId, array $payload, array $approval): void
    {
        match ($payload['kind'] ?? 'record') {
            'file'    => self::deleteFile($recordId),
            'inquiry' => self::deleteInquiry($recordId),
            'payroll' => PayrollService::delete($recordId),
            default   => self::approveDelete($recordId, $payload, $approval),
        };
    }

    /** A photo or file: hidden, and listed in the Trash so the Owner can bring it back. */
    public static function deleteFile(int $fileId): void
    {
        $f = DB::row('SELECT * FROM files WHERE id = ? AND deleted_at IS NULL', [$fileId]);
        if (!$f) {
            return;
        }
        $module = FileStore::OWNERS[$f['owner_type']][0] ?? 'documents';
        DB::transaction(function () use ($f, $module) {
            DB::update('files', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $f['id']]);
            DB::run('UPDATE horses SET main_photo_id = NULL WHERE main_photo_id = ?', [$f['id']]);
            DB::run('UPDATE employees SET photo_id = NULL WHERE photo_id = ?', [$f['id']]);
            DB::insert('trash', ['record_type' => 'file', 'record_table' => 'files', 'record_id' => $f['id'],
                'label' => mb_substr(__('files.singular') . ': ' . $f['original_name'], 0, 255), 'module' => $module, 'deleted_by' => Auth::id() ?? 0]);
            Audit::log('delete_file', $module, $f['owner_type'], $f['owner_id'], ['file_id' => $f['id'], 'name' => $f['original_name']], null, $f['original_name']);
        });
        Cache::bump();
    }

    /** A message from the website (Inbox). */
    public static function deleteInquiry(int $id): void
    {
        $m = DB::row('SELECT * FROM inquiries WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$m) {
            return;
        }
        DB::transaction(function () use ($m) {
            DB::update('inquiries', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $m['id']]);
            DB::insert('trash', ['record_type' => 'inquiry', 'record_table' => 'inquiries', 'record_id' => $m['id'],
                'label' => mb_substr(__('inbox.singular') . ': ' . $m['name'], 0, 255), 'module' => 'inbox', 'deleted_by' => Auth::id() ?? 0]);
            Audit::log('delete', 'inbox', 'inquiry', (int) $m['id'], ['name' => $m['name'], 'email' => $m['email']], null, 'Inquiry deleted');
        });
        Cache::bump();
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

    /** Approval handler for deleting a record (also requests sent before every deletion went to the Owner). */
    public static function approveDelete(int $recordId, array $payload, array $approval): void
    {
        $res = Registry::get($payload['resource'] ?? '');
        if (!$res) {
            return;
        }
        $row = DB::row('SELECT * FROM `' . $res->table . '` WHERE id = ? AND deleted_at IS NULL', [$recordId]);
        if ($row) {
            // e.g. a purchase order asked to be deleted as a draft and approved since
            if (!$res->canDelete($row)) {
                throw new \DomainException(__('approvals.delete_not_allowed_now'));
            }
            self::assertUnlinked($res, $recordId);
            self::softDelete($res, $row);
        }
    }

    /** Brings an item back. Its resource may refuse (throws DomainException with the reason) and update what depends on it. */
    public static function restore(int $trashId): void
    {
        $t = DB::row('SELECT * FROM trash WHERE id = ?', [$trashId]);
        if (!$t) {
            return;
        }
        DB::assertIdent($t['record_table']);
        $res = Registry::forRecordType($t['record_type']);
        $res = $res && $res->table === $t['record_table'] ? $res : null;
        $row = $res ? DB::row('SELECT * FROM `' . $t['record_table'] . '` WHERE id = ?', [$t['record_id']]) : null;
        if ($res && $row && ($why = $res->restoreBlocker($row)) !== null) {
            throw new \DomainException($why);
        }
        DB::transaction(function () use ($t, $res, $row) {
            DB::run('UPDATE `' . $t['record_table'] . '` SET deleted_at = NULL WHERE id = ?', [$t['record_id']]);
            DB::run('DELETE FROM trash WHERE id = ?', [$t['id']]);
            if ($res && $row) {
                $res->afterRestore(['deleted_at' => null] + $row);
            }
            Audit::log('restore', $t['module'], $t['record_type'], $t['record_id'], null, null, $t['label']);
        });
        Cache::bump();
    }

    /** A numbered document stays in the Trash for good (its number must stay on record). */
    public static function keptForGood(array $trashRow): bool
    {
        return in_array($trashRow['record_table'], self::NUMBERED, true);
    }

    /** Permanently removes one item. Returns false when the database still has references to it, or it is a numbered document. */
    public static function purge(int $trashId): bool
    {
        $t = DB::row('SELECT * FROM trash WHERE id = ?', [$trashId]);
        if (!$t) {
            return true;
        }
        if (self::keptForGood($t)) {
            return false;
        }
        DB::assertIdent($t['record_table']);
        $files = [];
        try {
            DB::transaction(function () use ($t, &$files) {
                // what is stored on the disk: a removed file's, or a Documents item's files (they go with it)
                $files = match ($t['record_table']) {
                    'files'           => DB::all('SELECT * FROM files WHERE id = ? AND deleted_at IS NOT NULL', [$t['record_id']]),
                    'media_documents' => DB::all("SELECT * FROM files WHERE owner_type = 'document' AND owner_id = ?", [$t['record_id']]),
                    default           => [],
                };
                if (!DB::run('DELETE FROM `' . $t['record_table'] . '` WHERE id = ? AND deleted_at IS NOT NULL', [$t['record_id']])->rowCount()) {
                    $files = []; // not deleted (any more): its files stay
                } elseif ($t['record_table'] === 'media_documents' && $files) {
                    $p = [];
                    $in = DB::in(array_column($files, 'id'), 'f', $p);
                    DB::run("DELETE FROM trash WHERE record_table = 'files' AND record_id IN $in", $p);
                    DB::run("DELETE FROM files WHERE id IN $in", $p);
                }
                DB::run('DELETE FROM trash WHERE id = ?', [$t['id']]);
                Audit::log('purge', $t['module'], $t['record_type'], $t['record_id'], null, null, $t['label']);
            });
        } catch (\PDOException) {
            return false;
        }
        // the stored copies on the disk, once their rows are gone for good
        foreach ($files as $f) {
            FileStore::removeStored($f);
        }
        return true;
    }
}
