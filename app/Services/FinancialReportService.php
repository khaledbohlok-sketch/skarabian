<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Resources\ListQuery;
use App\Resources\Registry;

/**
 * Financial Report (Studio type "fin") built from picked bills, not typed by hand.
 *
 * A report is a `documents` row from the moment it's started (status='draft'): no reference number or
 * verify token yet (those columns are nullable and only get filled in when the report is issued, same as
 * every other Studio type always has). Its bills live in `document_bills` — one row per bill (or per
 * manual line), holding a snapshot of the fields the report displays. While the document is a draft,
 * "refresh" re-pulls that snapshot from the live bill; once issued, nothing touches it again, so the
 * issued PDF/preview never changes even if the underlying bill is edited later.
 */
final class FinancialReportService
{
    private const DOC_TYPE = 'fin';
    private const KINDS = ['outstanding', 'expenses', 'income', 'full'];

    /** A period date from the report's settings, or null: a cleared period is "" in the browser, which a DATE column refuses. */
    private static function day(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $v : null;
    }

    private static function kind(mixed $v, ?string $fallback): string
    {
        return in_array($v, self::KINDS, true) ? $v : ($fallback ?: 'outstanding');
    }

    /** Bills shown in the "Add bills" picker, filtered exactly like Finance → Bills & transactions. */
    public static function billsForPicker(array $input, int $limit = 300): array
    {
        $res = Registry::get('bills');
        FinanceService::refreshOverdue();
        $q = new ListQuery($res, $input);
        if (empty($input['show_cancelled'])) {
            $q->where[] = "t.status <> 'cancelled'";
        }
        return ['rows' => $q->rows($limit, 0), 'total' => $q->count()];
    }

    /** The settings half of the report (title/subtitle/period/currency/...), unchanged from today's single JSON blob. */
    public static function createDraft(array $settings, ?int $userId = null): int
    {
        $kind = self::kind($settings['reportKind'] ?? null, 'outstanding');
        $id = DB::insert('documents', [
            'doc_type' => self::DOC_TYPE, 'lang' => ($settings['lang'] ?? 'en') === 'ar' ? 'ar' : 'en',
            'title' => mb_substr(Studio::title(self::DOC_TYPE, $settings), 0, 200),
            'record_type' => null, 'record_id' => null, 'doc_date' => date('Y-m-d'), 'letterhead_version' => (int) ($settings['_letterhead'] ?? 2),
            'status' => 'draft', 'period_from' => self::day($settings['periodFrom'] ?? null), 'period_to' => self::day($settings['periodTo'] ?? null), 'report_kind' => $kind,
            'data' => json_encode($settings, JSON_UNESCAPED_UNICODE), 'created_by' => $userId ?? Auth::id(),
        ]);
        Audit::log('create', 'studio', 'document', $id, null, ['type' => self::DOC_TYPE, 'draft' => true, 'kind' => $kind], __('studio.type_fin'));
        return $id;
    }

    public static function saveDraftSettings(int $documentId, array $settings): void
    {
        $doc = self::requireDraft($documentId);
        // documents has no updated_at column (document_bills does)
        DB::update('documents', [
            'title' => mb_substr(Studio::title(self::DOC_TYPE, $settings), 0, 200),
            'lang' => ($settings['lang'] ?? $doc['lang']) === 'ar' ? 'ar' : 'en',
            'period_from' => self::day($settings['periodFrom'] ?? null), 'period_to' => self::day($settings['periodTo'] ?? null),
            'report_kind' => self::kind($settings['reportKind'] ?? null, $doc['report_kind']), 'letterhead_version' => (int) ($settings['_letterhead'] ?? $doc['letterhead_version']),
            'data' => json_encode($settings, JSON_UNESCAPED_UNICODE),
        ], 'id = :id', ['id' => $documentId]);
    }

    /** The user's newest draft of this month that has no lines yet: opening "Financial Report" again continues it
     * instead of starting one more empty draft. */
    public static function emptyDraftOf(int $userId): ?int
    {
        $id = DB::value("SELECT d.id FROM documents d WHERE d.doc_type = ? AND d.status = 'draft' AND d.deleted_at IS NULL AND d.created_by = ?
            AND d.created_at >= ? AND NOT EXISTS (SELECT 1 FROM document_bills b WHERE b.document_id = d.id) ORDER BY d.id DESC LIMIT 1",
            [self::DOC_TYPE, $userId, date('Y-m-01')]);
        return $id ? (int) $id : null;
    }

    /** Removes a draft (hidden like any deleted record). An issued report is voided instead, never deleted. */
    public static function discard(int $documentId): void
    {
        $doc = self::requireDraft($documentId);
        DB::update('documents', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $documentId]);
        Audit::log('delete', 'studio', 'document', $documentId, ['status' => 'draft'], null, $doc['title']);
    }

    private static function requireDraft(int $documentId): array
    {
        $doc = DB::row("SELECT * FROM documents WHERE id = ? AND doc_type = ? AND deleted_at IS NULL", [$documentId, self::DOC_TYPE]);
        if (!$doc || $doc['status'] !== 'draft') {
            throw new \DomainException(__('studio.fin_not_draft'));
        }
        return $doc;
    }

    private static function snapshotRow(array $bill): array
    {
        $cur = (string) ($bill['currency'] ?? 'QAR');
        return [
            'number' => $bill['number'], 'bill_date' => $bill['bill_date'], 'due_date' => $bill['due_date'],
            'party_name_en' => $bill['party_en'], 'party_name_ar' => $bill['party_ar'],
            'category_name_en' => $bill['category_en'], 'category_name_ar' => $bill['category_ar'],
            'description' => $bill['description'], 'bill_type' => $bill['type'], 'bill_status' => $bill['status'],
            'currency' => $cur, 'amount_original' => $cur === 'QAR' ? null : (float) $bill['amount_original'],
            'amount_qar' => (float) $bill['amount_qar'], 'paid_qar' => (float) $bill['paid_qar'],
        ];
    }

    private static function loadBills(array $billIds): array
    {
        if (!$billIds) {
            return [];
        }
        $p = [];
        $in = DB::in($billIds, 'b', $p);
        // The payee is the supplier/client; a bill without one that belongs to an employee (salaries) is paid to that employee
        return DB::all("SELECT b.*, CASE WHEN p.id IS NULL THEN emp.name_en ELSE p.name_en END AS party_en,
                CASE WHEN p.id IS NULL THEN emp.name_ar ELSE p.name_ar END AS party_ar, c.name_en AS category_en, c.name_ar AS category_ar
            FROM bills b LEFT JOIN parties p ON p.id = b.party_id LEFT JOIN employees emp ON emp.id = b.employee_id
            LEFT JOIN finance_categories c ON c.id = b.category_id
            WHERE b.id IN $in AND b.deleted_at IS NULL", $p);
    }

    /** Adds bills to a draft (skips ones already linked). Returns ['added' => n, 'already' => n]. */
    public static function addBills(int $documentId, array $billIds): array
    {
        self::requireDraft($documentId);
        $billIds = array_values(array_unique(array_map('intval', $billIds)));
        if (!$billIds) {
            return ['added' => 0, 'already' => 0];
        }
        FinanceService::refreshOverdue();
        $existing = DB::column('SELECT bill_id FROM document_bills WHERE document_id = ? AND bill_id IS NOT NULL', [$documentId]);
        $new = array_values(array_diff($billIds, array_map('intval', $existing)));
        $bills = self::loadBills($new);
        $maxOrder = (int) DB::value('SELECT COALESCE(MAX(sort_order), -1) FROM document_bills WHERE document_id = ?', [$documentId]);
        foreach ($bills as $i => $bill) {
            DB::insert('document_bills', ['document_id' => $documentId, 'bill_id' => (int) $bill['id'], 'sort_order' => $maxOrder + 1 + $i, 'is_manual' => 0] + self::snapshotRow($bill));
        }
        return ['added' => count($bills), 'already' => count($billIds) - count($new)];
    }

    public static function addManualLine(int $documentId, array $line): int
    {
        self::requireDraft($documentId);
        $maxOrder = (int) DB::value('SELECT COALESCE(MAX(sort_order), -1) FROM document_bills WHERE document_id = ?', [$documentId]);
        return DB::insert('document_bills', [
            'document_id' => $documentId, 'bill_id' => null, 'is_manual' => 1, 'sort_order' => $maxOrder + 1,
            'manual_label_en' => mb_substr((string) ($line['en'] ?? ''), 0, 255), 'manual_label_ar' => mb_substr((string) ($line['ar'] ?? ''), 0, 255),
            'bill_type' => in_array($line['type'] ?? '', ['income', 'expense', 'liability'], true) ? $line['type'] : 'expense',
            'bill_status' => (string) ($line['status'] ?? 'pending'), 'amount_qar' => round((float) ($line['amount'] ?? 0), 2), 'paid_qar' => round((float) ($line['paid'] ?? 0), 2),
        ]);
    }

    /** Syncs edited fields of manual lines (name/amount/type/status) — called alongside "Save draft", since
     * manual lines live in document_bills, not the documents.data settings blob those saves normally touch. */
    public static function syncManualLines(int $documentId, array $lines): void
    {
        self::requireDraft($documentId);
        foreach ($lines as $line) {
            $lineId = (int) ($line['lineId'] ?? 0);
            if (!$lineId) {
                continue;
            }
            DB::update('document_bills', [
                'manual_label_en' => mb_substr((string) ($line['en'] ?? ''), 0, 255), 'manual_label_ar' => mb_substr((string) ($line['ar'] ?? ''), 0, 255),
                'description' => mb_substr((string) ($line['desc'] ?? ''), 0, 255),
                'bill_type' => in_array($line['type'] ?? '', ['income', 'expense', 'liability'], true) ? $line['type'] : 'expense',
                'bill_status' => (string) ($line['status'] ?? 'pending'), 'amount_qar' => round((float) ($line['amt'] ?? 0), 2), 'paid_qar' => round((float) ($line['paid'] ?? 0), 2),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id AND document_id = :doc AND is_manual = 1', ['id' => $lineId, 'doc' => $documentId]);
        }
    }

    public static function removeLine(int $documentId, int $lineId): void
    {
        self::requireDraft($documentId);
        DB::run('DELETE FROM document_bills WHERE id = ? AND document_id = ?', [$lineId, $documentId]);
    }

    public static function reorder(int $documentId, array $orderedLineIds): void
    {
        self::requireDraft($documentId);
        foreach (array_values($orderedLineIds) as $i => $lineId) {
            DB::run('UPDATE document_bills SET sort_order = ? WHERE id = ? AND document_id = ?', [$i, (int) $lineId, $documentId]);
        }
    }

    /** Re-pulls every non-manual line from its live bill. Returns the lines whose snapshot actually changed. */
    public static function refresh(int $documentId): array
    {
        self::requireDraft($documentId);
        FinanceService::refreshOverdue();
        $lines = DB::all('SELECT * FROM document_bills WHERE document_id = ? AND is_manual = 0 AND bill_id IS NOT NULL', [$documentId]);
        if (!$lines) {
            return [];
        }
        $bills = array_column(self::loadBills(array_map(fn ($l) => (int) $l['bill_id'], $lines)), null, 'id');
        $changed = [];
        foreach ($lines as $line) {
            $bill = $bills[(int) $line['bill_id']] ?? null;
            if (!$bill) {
                continue; // the bill was deleted since; leave the last known snapshot, caller can remove the line
            }
            $fresh = self::snapshotRow($bill);
            $diffKeys = array_filter(array_keys($fresh), fn ($k) => (string) $fresh[$k] !== (string) ($line[$k] ?? ''));
            if ($diffKeys) {
                DB::update('document_bills', $fresh + ['updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $line['id']]);
                $changed[] = ['id' => (int) $line['id'], 'number' => $line['number'], 'before' => array_intersect_key($line, array_flip($diffKeys)), 'after' => array_intersect_key($fresh, array_flip($diffKeys))];
            }
        }
        return $changed;
    }

    public static function linesFor(int $documentId): array
    {
        return DB::all('SELECT * FROM document_bills WHERE document_id = ? ORDER BY sort_order, id', [$documentId]);
    }

    /** One document_bills row in the shape the report is drawn from (as finLineFromServer() in studio-editor.js). */
    private static function reportLine(array $r): array
    {
        return [
            'lineId' => (int) $r['id'], 'isManual' => (bool) $r['is_manual'], 'billId' => $r['bill_id'] ? (int) $r['bill_id'] : null,
            'number' => (string) $r['number'], 'date' => (string) $r['bill_date'], 'due' => (string) $r['due_date'],
            'partyEn' => (string) $r['party_name_en'], 'partyAr' => (string) $r['party_name_ar'], 'catEn' => (string) $r['category_name_en'], 'catAr' => (string) $r['category_name_ar'],
            'desc' => (string) $r['description'], 'en' => (string) $r['manual_label_en'], 'ar' => (string) $r['manual_label_ar'],
            'type' => $r['bill_type'] ?: 'expense', 'status' => $r['bill_status'] ?: 'pending', 'currency' => $r['currency'] ?: 'QAR',
            'amtOriginal' => $r['amount_original'] !== null ? (float) $r['amount_original'] : null, 'amt' => (float) $r['amount_qar'], 'paid' => (float) $r['paid_qar'],
        ];
    }

    /** Draft 'fin' reports the current user may continue, newest first (for "choose an existing draft"). */
    public static function draftsFor(?string $reportKind = null): array
    {
        $where = ["doc_type = 'fin'", "status = 'draft'", 'deleted_at IS NULL'];
        $p = [];
        if ($reportKind) {
            $where[] = 'report_kind = :k';
            $p['k'] = $reportKind;
        }
        if (!Auth::isOwner()) {
            $where[] = 'created_by = :u';
            $p['u'] = Auth::id();
        }
        return DB::all('SELECT id, title, period_from, period_to, report_kind, created_at,
            (SELECT COUNT(*) FROM document_bills WHERE document_id = documents.id) AS line_count
            FROM documents WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 50', $p);
    }

    /** Reports (draft or issued) a bill is linked to — for the Bills list's "included in" icon. */
    public static function reportsFor(int $billId): array
    {
        return DB::all("SELECT d.id, d.ref_no, d.title, d.status FROM document_bills db JOIN documents d ON d.id = db.document_id
            WHERE db.bill_id = ? AND d.deleted_at IS NULL ORDER BY d.status = 'issued' DESC, d.id DESC", [$billId]);
    }

    /** Another ISSUED report of the same kind, overlapping period, that already contains one of these bills. */
    public static function duplicateWarning(int $documentId, array $billIds, ?string $periodFrom, ?string $periodTo, ?string $reportKind): ?string
    {
        if (!$billIds) {
            return null;
        }
        $p = ['id' => $documentId, 'k' => $reportKind ?: ''];
        $in = DB::in(array_map('intval', $billIds), 'b', $p);
        $periodSql = '';
        if ($periodFrom && $periodTo) {
            $periodSql = 'AND (d.period_from IS NULL OR d.period_to IS NULL OR d.period_from <= :pto AND d.period_to >= :pfrom)';
            $p['pfrom'] = $periodFrom;
            $p['pto'] = $periodTo;
        }
        $hit = DB::row("SELECT d.ref_no, d.title FROM documents d JOIN document_bills db ON db.document_id = d.id
            WHERE d.doc_type = 'fin' AND d.status = 'issued' AND d.id <> :id AND d.report_kind = :k AND d.deleted_at IS NULL $periodSql AND db.bill_id IN $in LIMIT 1", $p);
        return $hit ? __('studio.fin_duplicate_warn', ['ref' => $hit['ref_no'], 'title' => $hit['title']]) : null;
    }

    /** Locks the draft: assigns the reference number + verify token, same as every other Studio type gets on its one save. */
    public static function issue(int $documentId, array $settings, bool $override = false): array
    {
        $doc = self::requireDraft($documentId);
        // the period and report type as issued now, even when changed since the last "Save draft"
        $from = self::day($settings['periodFrom'] ?? null);
        $to = self::day($settings['periodTo'] ?? null);
        $kind = self::kind($settings['reportKind'] ?? null, $doc['report_kind']);
        $billIds = DB::column('SELECT bill_id FROM document_bills WHERE document_id = ? AND bill_id IS NOT NULL', [$documentId]);
        if (!$override) {
            $warn = self::duplicateWarning($documentId, array_map('intval', $billIds), $from, $to, $kind);
            if ($warn) {
                return ['ok' => false, 'warning' => $warn];
            }
        }
        return DB::transaction(function () use ($documentId, $settings, $doc, $from, $to, $kind) {
            // Locked: the draft (issued only once) and its bills (no payment/edit lands between the snapshot and the
            // issue). The snapshots are refreshed now, so the issued report shows the bills as they are at issue time.
            $locked = DB::row('SELECT status FROM documents WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$documentId]);
            if (!$locked || $locked['status'] !== 'draft') {
                throw new \DomainException(__('studio.fin_not_draft'));
            }
            $p = [];
            $ids = array_map('intval', DB::column('SELECT bill_id FROM document_bills WHERE document_id = ? AND bill_id IS NOT NULL', [$documentId]));
            if ($ids) {
                DB::all('SELECT id FROM bills WHERE id IN ' . DB::in($ids, 'b', $p) . ' FOR UPDATE', $p);
            }
            // manual lines typed since the last "Save draft" are kept too
            $manual = array_values(array_filter((array) ($settings['rows'] ?? []), fn ($r) => is_array($r) && !empty($r['isManual'])));
            if ($manual) {
                self::syncManualLines($documentId, $manual);
            }
            self::refresh($documentId);
            // the issued report is drawn from these lines (as just refreshed), not from the copy the browser sent
            $settings['rows'] = array_map(fn ($r) => self::reportLine($r), self::linesFor($documentId));
            $ref = Sequence::document(StudioDocs::TYPES[self::DOC_TYPE]['code'], date('Y-m-d'));
            DB::update('documents', [
                'ref_no' => $ref, 'verify_token' => bin2hex(random_bytes(16)), 'status' => 'issued', 'doc_date' => date('Y-m-d'),
                'title' => mb_substr(Studio::title(self::DOC_TYPE, $settings), 0, 200), 'data' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                'lang' => ($settings['lang'] ?? $doc['lang']) === 'ar' ? 'ar' : 'en', 'period_from' => $from, 'period_to' => $to, 'report_kind' => $kind,
                'letterhead_version' => (int) ($settings['_letterhead'] ?? $doc['letterhead_version']),
            ], 'id = :id', ['id' => $documentId]);
            Audit::log('issue', 'studio', 'document', $documentId, ['status' => 'draft'], ['status' => 'issued', 'ref' => $ref], $ref);
            return ['ok' => true, 'ref' => $ref];
        });
    }
}
