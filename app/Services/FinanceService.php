<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;

/**
 * Bills: numbering, approval rules, payments and status.
 *
 * Status rules
 *   draft          saved, not submitted (not counted as approved; shown as pending in reports)
 *   pending        waiting for Owner / General Manager approval (expense or liability above the limit)
 *   approved       approved, nothing paid yet
 *   partially_paid some payments recorded
 *   paid           fully paid
 *   overdue        approved, not fully paid and past the due date (set automatically)
 *   cancelled      not counted anywhere
 * Every payment is a bill_payments row, so cash per account can always be rebuilt from the payments.
 */
final class FinanceService
{
    public const OPEN = ['approved', 'partially_paid', 'overdue'];

    /**
     * The four money figures shown everywhere (dashboard, reports, horse finance), so they always agree:
     *   paid     what was actually paid (received, for income), on any bill not cancelled — a partly paid bill sent
     *            back for approval (a bigger amount) or rejected to draft keeps its payments, as the cash balance does
     *   unpaid   approved but not paid yet
     *   pending  waiting for approval (less anything already paid)
     *   draft    saved, not submitted: shown, never added into totals (less anything already paid)
     * SQL sums over the bills alias $b; $when narrows them (e.g. "b.type = 'income'"), $suffix names the columns.
     */
    public static function bucketSums(string $b = 'b', string $when = '1=1', string $suffix = ''): string
    {
        $ok = "$b.status IN ('approved','partially_paid','paid','overdue')";
        return "COALESCE(SUM(CASE WHEN $when AND $b.status <> 'cancelled' THEN $b.paid_qar END), 0) AS paid$suffix,
            COALESCE(SUM(CASE WHEN $when AND $ok THEN $b.amount_qar - $b.paid_qar END), 0) AS unpaid$suffix,
            COALESCE(SUM(CASE WHEN $when AND $b.status = 'pending' THEN $b.amount_qar - $b.paid_qar END), 0) AS pending$suffix,
            COALESCE(SUM(CASE WHEN $when AND $b.status = 'draft' THEN $b.amount_qar - $b.paid_qar END), 0) AS draft$suffix";
    }

    /** One set of figures from a bucketSums() row: [paid, unpaid, pending, draft, total (without drafts)]. */
    public static function buckets(array $row, string $suffix = ''): array
    {
        $f = [];
        foreach (['paid', 'unpaid', 'pending', 'draft'] as $k) {
            $f[$k] = round((float) ($row[$k . $suffix] ?? 0), 2);
        }
        $f['total'] = round($f['paid'] + $f['unpaid'] + $f['pending'], 2);
        return $f;
    }

    public static function limit(): float
    {
        return (float) Settings::get('approval.bill_limit_qar', '5000');
    }

    // ------------------------------------------------------------- due dates

    /** Days to pay when a client / supplier has no terms of its own (Settings; default 30). */
    public static function defaultTerms(): int
    {
        return max(0, min(365, (int) Settings::get('finance.default_terms_days', '30')));
    }

    /** A client's / supplier's payment terms in days (its own, else the default). */
    public static function termsFor(?int $partyId): int
    {
        if ($partyId && DB::hasColumn('parties', 'payment_terms_days')) {
            $t = DB::value('SELECT payment_terms_days FROM parties WHERE id = ?', [$partyId]);
            if ($t !== null) {
                return (int) $t;
            }
        }
        return self::defaultTerms();
    }

    /** When a bill falls due: its own due date, else the bill date plus the client's / supplier's terms; a bill without one is due on its date. */
    public static function effectiveDue(array $bill): string
    {
        if (!empty($bill['due_date'])) {
            return (string) $bill['due_date'];
        }
        if (empty($bill['party_id'])) {
            return (string) $bill['bill_date'];
        }
        return date('Y-m-d', strtotime($bill['bill_date'] . ' +' . self::termsFor((int) $bill['party_id']) . ' days'));
    }

    /** The same date in SQL, for bills $b with their client / supplier joined as $p (LEFT JOIN parties $p ON $p.id = $b.party_id). */
    public static function dueSql(string $b = 'b', string $p = 'p'): string
    {
        $default = self::defaultTerms();
        $terms = DB::hasColumn('parties', 'payment_terms_days') ? "COALESCE($p.payment_terms_days, $default)" : (string) $default;
        return "COALESCE($b.due_date, CASE WHEN $b.party_id IS NULL THEN $b.bill_date ELSE DATE_ADD($b.bill_date, INTERVAL $terms DAY) END)";
    }

    /**
     * Expenses and liabilities above the limit need Owner / GM approval. The Owner's own entries do not, unless Security
     * says the Owner's own requests need a second approver.
     */
    public static function needsApproval(string $type, float $amountQar): bool
    {
        return $type !== 'income' && $amountQar > self::limit() && (!Auth::isOwner() || Approvals::ownerSecond());
    }

    /** An Owner's entry above the limit that skipped approval ("auto-approved (Owner)" in the activity log). */
    public static function ownerAutoApproved(string $type, float $amountQar): bool
    {
        return $type !== 'income' && $amountQar > self::limit() && Auth::isOwner() && !Approvals::ownerSecond();
    }

    public static function title(array $bill): string
    {
        $party = !empty($bill['party_id']) ? (string) DB::value('SELECT name_en FROM parties WHERE id = ?', [$bill['party_id']]) : '';
        return trim(($bill['number'] ?? '') . ' — ' . (($bill['description'] ?? '') ?: __('bills.type_' . ($bill['type'] ?? 'expense'))) . ($party !== '' ? ' — ' . $party : ''));
    }

    /**
     * Creates a bill from another workflow (purchase order received, payroll approved, invoice issued).
     * Those workflows were already approved, so the bill starts as approved.
     */
    public static function create(array $d): int
    {
        $d += ['currency' => Money::BASE, 'exchange_rate' => 1, 'status' => 'approved', 'bill_date' => date('Y-m-d')];
        $d['amount_qar'] ??= Money::toQar((float) $d['amount_original'], (float) $d['exchange_rate']);
        if (empty($d['due_date'])) { // as on the bill form: the bill date plus the client's / supplier's payment terms
            $d['due_date'] = self::effectiveDue(['bill_date' => $d['bill_date'], 'party_id' => $d['party_id'] ?? null, 'due_date' => null]);
        }
        $d['number'] = Sequence::bill($d['bill_date']);
        $d['created_by'] ??= Auth::id();
        if ($d['status'] === 'approved') {
            $d['approved_by'] ??= Auth::id();
            $d['approved_at'] ??= date('Y-m-d H:i:s');
        }
        $id = DB::insert('bills', $d);
        Audit::log('create', 'finance', 'bill', $id, null, $d, self::title($d + ['id' => $id]));
        self::recalc($id);
        return $id;
    }

    /** Draft → approved, or → pending with an approval request. */
    public static function submit(int $billId, array $payload = []): string
    {
        $b = DB::row('SELECT * FROM bills WHERE id = ? AND deleted_at IS NULL', [$billId]);
        if (!$b || $b['status'] !== 'draft') {
            throw new \DomainException(__('bills.cannot_submit'));
        }
        if (self::needsApproval($b['type'], (float) $b['amount_qar'])) {
            DB::update('bills', ['status' => 'pending', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $billId]);
            Approvals::request('bill', 'bill', $billId, self::title($b), (float) $b['amount_qar'], $payload);
            return 'pending';
        }
        DB::update('bills', ['status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $billId]);
        Audit::log('submit', 'finance', 'bill', $billId, ['status' => 'draft'], ['status' => 'approved'], self::title($b));
        self::recalc($billId);
        if (!empty($payload['pay_account_id'])) {
            self::payInFull($billId, (int) $payload['pay_account_id'], $payload['pay_method_id'] ?? null, $b['bill_date']);
        }
        return 'approved';
    }

    /** Approval handler (Owner / GM). */
    public static function approveBill(int $billId, array $payload, array $approval): void
    {
        $b = DB::row("SELECT * FROM bills WHERE id = ? AND status = 'pending' FOR UPDATE", [$billId]);
        if (!$b) {
            return;
        }
        DB::update('bills', ['status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $billId]);
        self::recalc($billId);
        if (!empty($payload['pay_account_id'])) {
            self::payInFull($billId, (int) $payload['pay_account_id'], $payload['pay_method_id'] ?? null, $b['bill_date'], (int) $approval['requested_by']);
        }
    }

    /** A rejected bill goes back to draft so the person who entered it can correct or cancel it. */
    public static function rejectBill(int $billId, array $payload, array $approval): void
    {
        DB::update('bills', ['status' => 'draft'], "id = :id AND status = 'pending'", ['id' => $billId]);
    }

    public static function cancel(int $billId): void
    {
        $b = DB::row('SELECT * FROM bills WHERE id = ? AND deleted_at IS NULL', [$billId]);
        if (!$b || $b['status'] === 'cancelled') {
            throw new \DomainException(__('bills.cannot_cancel'));
        }
        // the goods of a received purchase order are in stock: its supplier bill stays (a received order can't be cancelled)
        if ($b['purchase_order_id']) {
            throw new \DomainException(__('bills.cancel_po', ['number' => (string) DB::value('SELECT number FROM purchase_orders WHERE id = ?', [$b['purchase_order_id']])]));
        }
        if (DB::value('SELECT COUNT(*) FROM bill_payments WHERE bill_id = ? AND deleted_at IS NULL', [$billId])) {
            throw new \DomainException(__('bills.cancel_has_payments'));
        }
        DB::update('bills', ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $billId]);
        Approvals::cancelFor('bill', $billId, 'bill'); // a pending delete request stays: a cancelled bill can still be deleted
        Audit::log('cancel', 'finance', 'bill', $billId, ['status' => $b['status']], ['status' => 'cancelled'], self::title($b));
        Cache::bump();
    }

    public static function remaining(array $bill): float
    {
        return round((float) $bill['amount_qar'] - (float) $bill['paid_qar'], 2);
    }

    /** Records one payment for the whole remaining amount (used by "paid now" and payroll). */
    public static function payInFull(int $billId, int $accountId, mixed $methodId, ?string $date = null, ?int $userId = null, ?int $payrollLineId = null): ?int
    {
        return DB::transaction(function () use ($billId, $accountId, $methodId, $date, $userId, $payrollLineId) {
            // Bill locked and payments read as committed: two payments at the same moment can't both pay it in full
            $b = DB::row('SELECT * FROM bills WHERE id = ? FOR UPDATE', [$billId]);
            if (!$b || !in_array($b['status'], self::OPEN, true)) {
                return null;
            }
            $paid = (float) DB::value('SELECT COALESCE(SUM(amount_qar), 0) FROM bill_payments WHERE bill_id = ? AND deleted_at IS NULL FOR UPDATE', [$billId]);
            $amount = round((float) $b['amount_qar'] - $paid, 2);
            if ($amount <= 0) {
                return null;
            }
            $id = DB::insert('bill_payments', [
                'bill_id' => $billId, 'payment_date' => $date ?: date('Y-m-d'), 'amount_qar' => $amount, 'account_id' => $accountId,
                'payment_method_id' => $methodId ?: null, 'payroll_line_id' => $payrollLineId, 'created_by' => $userId ?? Auth::id(),
            ]);
            Audit::log('payment', 'finance', 'bill', $billId, null, ['payment_id' => $id, 'amount_qar' => $amount, 'account_id' => $accountId], self::title($b));
            self::recalc($billId);
            return $id;
        });
    }

    /** Re-reads the payments and sets paid amount and status. Keeps linked invoices in step. */
    public static function recalc(int $billId): void
    {
        $b = DB::row('SELECT * FROM bills WHERE id = ?', [$billId]);
        if (!$b) {
            return;
        }
        $paid = round((float) DB::value('SELECT COALESCE(SUM(amount_qar), 0) FROM bill_payments WHERE bill_id = ? AND deleted_at IS NULL', [$billId]), 2);
        $status = $b['status'];
        if (in_array($status, self::OPEN, true) || $status === 'paid') {
            $amount = (float) $b['amount_qar'];
            if ($paid >= $amount - 0.004) {
                $status = 'paid';
            } elseif (self::effectiveDue($b) < date('Y-m-d')) {
                $status = 'overdue';
            } elseif ($paid > 0) {
                $status = 'partially_paid';
            } else {
                $status = 'approved';
            }
        }
        if ($paid !== round((float) $b['paid_qar'], 2) || $status !== $b['status']) {
            DB::update('bills', ['paid_qar' => $paid, 'status' => $status], 'id = :id', ['id' => $billId]);
        }
        if ($b['invoice_id']) {
            $inv = match ($status) {
                'paid' => 'paid',
                'partially_paid' => 'partially_paid',
                'overdue' => $paid > 0 ? 'partially_paid' : 'issued',
                'cancelled' => 'cancelled',
                default => 'issued',
            };
            DB::run("UPDATE invoices SET status = ? WHERE id = ? AND status <> 'draft'", [$inv, $b['invoice_id']]);
        }
        Cache::bump();
    }

    /**
     * Hourly (cron) and on list load: approved bills past their due date become overdue. A bill without a due date is
     * due its client's / supplier's payment terms after the bill date (on the bill date when it has none).
     */
    public static function refreshOverdue(): int
    {
        return DB::run("UPDATE bills b LEFT JOIN parties p ON p.id = b.party_id SET b.status = 'overdue'
            WHERE b.deleted_at IS NULL AND b.status IN ('approved','partially_paid') AND b.paid_qar < b.amount_qar AND " . self::dueSql('b', 'p') . ' < CURDATE()')->rowCount();
    }

    // ------------------------------------------------------------- invoices

    /** Replaces the lines of a draft invoice. $lines: [[description, quantity, unit_price], ...] */
    public static function saveInvoiceLines(int $invoiceId, array $lines): void
    {
        DB::transaction(function () use ($invoiceId, $lines) {
            $inv = DB::row('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            if (!$inv || $inv['status'] !== 'draft') {
                throw new \DomainException(__('invoices.not_draft'));
            }
            DB::run('DELETE FROM invoice_items WHERE invoice_id = ?', [$invoiceId]);
            $total = 0.0;
            foreach ($lines as [$desc, $qty, $price]) {
                $line = round($qty * $price, 2);
                $total += $line;
                DB::insert('invoice_items', ['invoice_id' => $invoiceId, 'description' => $desc, 'quantity' => $qty, 'unit_price' => $price, 'line_total' => $line]);
            }
            DB::update('invoices', ['total_original' => round($total, 2), 'total_qar' => Money::toQar($total, (float) $inv['exchange_rate']), 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $invoiceId]);
            Audit::log('update', 'finance', 'invoice', $invoiceId, ['total' => $inv['total_original']], ['total' => round($total, 2), 'lines' => count($lines)], $inv['number']);
        });
    }

    /** Issuing creates the income record (approved bill) used for payments and reports. */
    public static function issueInvoice(int $invoiceId): int
    {
        return DB::transaction(function () use ($invoiceId) {
            $inv = DB::row('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            if (!$inv || $inv['status'] !== 'draft' || (float) $inv['total_original'] <= 0) {
                throw new \DomainException(__('invoices.cannot_issue'));
            }
            $cat = $inv['category_id'] ?: DB::value("SELECT id FROM finance_categories WHERE parent_id IS NULL AND name_en = ?", [$inv['invoice_type'] === 'boarding' ? 'Boarding' : 'Other']);
            DB::update('invoices', ['status' => 'issued', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $invoiceId]);
            $billId = self::create([
                'type' => 'income', 'category_id' => $cat ?: null, 'party_id' => $inv['party_id'], 'description' => __('invoices.bill_description', ['number' => $inv['number']]),
                'currency' => $inv['currency'], 'exchange_rate' => $inv['exchange_rate'], 'amount_original' => $inv['total_original'], 'amount_qar' => (float) $inv['total_qar'],
                'bill_date' => $inv['invoice_date'], 'due_date' => $inv['due_date'], 'horse_id' => $inv['horse_id'], 'invoice_id' => $invoiceId, 'reference_no' => $inv['number'],
            ]);
            DB::update('invoices', ['bill_id' => $billId], 'id = :id', ['id' => $invoiceId]);
            Audit::log('issue', 'finance', 'invoice', $invoiceId, ['status' => 'draft'], ['status' => 'issued', 'bill_id' => $billId], $inv['number']);
            return $billId;
        });
    }

    /** Cancels an invoice that has no payments (its income record is cancelled too). */
    public static function cancelInvoice(int $invoiceId): void
    {
        DB::transaction(function () use ($invoiceId) {
            $inv = DB::row('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            if (!$inv || !in_array($inv['status'], ['draft', 'issued'], true)) {
                throw new \DomainException(__('invoices.cannot_cancel'));
            }
            if ($inv['bill_id']) {
                self::cancel((int) $inv['bill_id']);
            }
            DB::update('invoices', ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $invoiceId]);
            Audit::log('cancel', 'finance', 'invoice', $invoiceId, ['status' => $inv['status']], ['status' => 'cancelled'], $inv['number']);
        });
    }

    /** Cash, bank and petty-cash balances: opening + money in − money out ± transfers. */
    public static function accountBalances(?string $until = null, bool $activeOnly = false): array
    {
        $until ??= date('Y-m-d');
        return DB::all("SELECT a.id, a.name, a.type, a.opening_balance_qar,
              a.opening_balance_qar
            + COALESCE((SELECT SUM(CASE WHEN b.type = 'income' THEN p.amount_qar ELSE -p.amount_qar END) FROM bill_payments p JOIN bills b ON b.id = p.bill_id
                         WHERE p.account_id = a.id AND p.deleted_at IS NULL AND p.payment_date <= :u1), 0)
            + COALESCE((SELECT SUM(amount_qar) FROM account_transfers t WHERE t.to_account_id = a.id AND t.deleted_at IS NULL AND t.transfer_date <= :u2), 0)
            - COALESCE((SELECT SUM(amount_qar) FROM account_transfers t WHERE t.from_account_id = a.id AND t.deleted_at IS NULL AND t.transfer_date <= :u3), 0) AS balance
            FROM accounts a WHERE a.deleted_at IS NULL" . ($activeOnly ? ' AND a.active = 1' : '') . " ORDER BY a.active DESC, a.type, a.name", ['u1' => $until, 'u2' => $until, 'u3' => $until]);
    }

    /** What a client owes us and what we owe a supplier (approved, unpaid amounts). */
    public static function partyBalance(int $partyId): array
    {
        $r = DB::row("SELECT
            COALESCE(SUM(CASE WHEN type = 'income' AND status IN ('approved','partially_paid','overdue') THEN amount_qar - paid_qar END), 0) AS owed_to_us,
            COALESCE(SUM(CASE WHEN type <> 'income' AND status IN ('approved','partially_paid','overdue') THEN amount_qar - paid_qar END), 0) AS we_owe,
            COALESCE(SUM(CASE WHEN status IN ('draft','pending') THEN amount_qar END), 0) AS pending,
            COALESCE(SUM(CASE WHEN type = 'income' AND status <> 'cancelled' THEN amount_qar END), 0) AS income_total,
            COALESCE(SUM(CASE WHEN type <> 'income' AND status <> 'cancelled' THEN amount_qar END), 0) AS expense_total
            FROM bills WHERE party_id = ? AND deleted_at IS NULL", [$partyId]);
        return array_map('floatval', $r ?: []);
    }
}
