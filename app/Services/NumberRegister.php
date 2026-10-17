<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/**
 * Number register: every document number ever issued (bills, invoices, purchase orders, embryos, employees, Studio
 * documents and printouts), from the counters in `sequences`, which only ever go up. Each number shows what it is now:
 * active, void (cancelled), deleted (kept in the Trash for good) or no record (a save that did not finish, or a record
 * removed before numbered documents were kept for good). A number is never given twice.
 */
final class NumberRegister
{
    /** type => definition. 'seq' is the counter name (DOC-… for Studio: one counter per document type). */
    public static function types(): array
    {
        return [
            'bill'     => ['label' => 'nav.bills', 'seq' => 'BILL', 'table' => 'bills', 'col' => 'number', 'date' => 'bill_date', 'module' => 'finance', 'url' => '/portal/bills/'],
            'invoice'  => ['label' => 'nav.invoices', 'seq' => 'INV', 'table' => 'invoices', 'col' => 'number', 'date' => 'invoice_date', 'module' => 'finance', 'url' => '/portal/invoices/'],
            'po'       => ['label' => 'nav.purchase_orders', 'seq' => 'PO', 'table' => 'purchase_orders', 'col' => 'number', 'date' => 'order_date', 'module' => 'finance', 'url' => '/portal/purchase-orders/'],
            'embryo'   => ['label' => 'nav.embryos', 'seq' => 'EMB', 'table' => 'embryos', 'col' => 'code', 'date' => 'created_at', 'module' => 'embryos', 'url' => '/portal/embryos/'],
            'employee' => ['label' => 'nav.employees', 'seq' => 'EMP', 'table' => 'employees', 'col' => 'emp_no', 'date' => 'created_at', 'module' => 'hr', 'url' => '/portal/employees/'],
            'studio'   => ['label' => 'nav.studio', 'seq' => 'DOC-', 'table' => 'documents', 'col' => 'ref_no', 'date' => 'doc_date', 'module' => 'studio', 'url' => '/portal/studio/'],
            'printout' => ['label' => 'numbers.printouts', 'seq' => 'DOC-PRT', 'table' => 'printouts', 'col' => 'ref_no', 'date' => 'printed_at', 'module' => 'owner', 'url' => null],
        ];
    }

    /** The types the user may see (Owner: all). */
    public static function visibleTypes(): array
    {
        return array_filter(self::types(), fn ($t) => Auth::isOwner() || ($t['module'] !== 'owner' && Auth::can($t['module'])));
    }

    /** The number a counter gave: BILL-202610-0007, INV-…, PO-…, EMB-2026-003, SK-004, SKA-SAL-2026-0001. */
    public static function format(string $seq, string $period, int $n): string
    {
        return match (true) {
            $seq === 'BILL' => sprintf('BILL-%s-%04d', $period, $n),
            $seq === 'INV', $seq === 'PO' => sprintf('%s-%s-%04d', $seq, $period, $n),
            $seq === 'EMB' => sprintf('EMB-%s-%03d', $period, $n),
            $seq === 'EMP' => sprintf('SK-%03d', $n),
            str_starts_with($seq, 'DOC-') => sprintf('SKA-%s-%s-%04d', substr($seq, 4), $period, $n),
            default => $seq . '-' . $period . '-' . $n,
        };
    }

    /** What a counter numbers: "Bills & transactions", or for Studio the document type ("SK Arabian Studio · Payslip"). */
    public static function label(string $type, string $seq): string
    {
        $name = __(self::types()[$type]['label'] ?? 'common.record');
        if ($type === 'studio') {
            foreach (StudioDocs::TYPES as $docType => $t) {
                if ('DOC-' . $t['code'] === $seq) {
                    return $name . ' · ' . __('studio.type_' . $docType);
                }
            }
        }
        return $name;
    }

    /** The type a counter belongs to. */
    public static function typeOf(string $seq): ?string
    {
        foreach (self::types() as $k => $t) {
            if ($seq === $t['seq']) {
                return $k;
            }
        }
        return str_starts_with($seq, 'DOC-') ? 'studio' : null;
    }

    /** Counters the user may see: [type, seq, period, last], grouped by type (in the order of types()), newest first. */
    public static function counters(): array
    {
        $types = self::visibleTypes();
        $order = array_flip(array_keys(self::types()));
        $out = [];
        foreach (DB::all('SELECT name, period, `last_value` FROM sequences WHERE `last_value` > 0 ORDER BY period DESC, name') as $s) {
            $type = self::typeOf($s['name']);
            if ($type !== null && isset($types[$type])) {
                $out[] = ['type' => $type, 'seq' => $s['name'], 'period' => $s['period'], 'last' => (int) $s['last_value']];
            }
        }
        usort($out, fn ($a, $b) => [$order[$a['type']], $b['period'], $a['seq']] <=> [$order[$b['type']], $a['period'], $b['seq']]);
        return $out;
    }

    /** Every number of one counter with its state: [no, state, date, id, url]. */
    public static function numbers(string $seq, string $period): array
    {
        $type = self::typeOf($seq);
        $t = $type ? self::types()[$type] : null;
        $last = (int) DB::value('SELECT `last_value` FROM sequences WHERE name = ? AND period = ?', [$seq, $period]);
        if (!$t || !$last || !isset(self::visibleTypes()[$type])) {
            return [];
        }
        $want = [];
        for ($n = 1; $n <= $last; $n++) {
            $want[self::format($seq, $period, $n)] = $n;
        }
        $cols = DB::columns($t['table']);
        $select = ['id', '`' . $t['col'] . '` AS no', isset($cols[$t['date']]) ? '`' . $t['date'] . '` AS d' : 'NULL AS d',
                   isset($cols['deleted_at']) ? 'deleted_at' : 'NULL AS deleted_at', isset($cols['status']) ? 'status' : 'NULL AS status', isset($cols['is_void']) ? 'is_void' : '0 AS is_void'];
        $params = [];
        $in = DB::in(array_keys($want), 'n', $params);
        $found = [];
        foreach (DB::all('SELECT ' . implode(', ', $select) . ' FROM `' . $t['table'] . '` WHERE `' . $t['col'] . "` IN $in", $params) as $r) {
            $found[$r['no']] = $r;
        }
        $out = [];
        foreach ($want as $no => $n) {
            $r = $found[$no] ?? null;
            $state = match (true) {
                $r === null => 'none',
                $r['deleted_at'] !== null => 'deleted',
                (int) $r['is_void'] === 1 || $r['status'] === 'cancelled' => 'void',
                $r['status'] === 'draft' => 'draft',
                default => 'active',
            };
            $out[] = ['no' => $no, 'state' => $state, 'date' => $r ? substr((string) $r['d'], 0, 10) : null, 'id' => $r ? (int) $r['id'] : null,
                      'url' => $r && $t['url'] && $state !== 'deleted' ? $t['url'] . (int) $r['id'] : null];
        }
        return $out;
    }

    /** Numbers in use more than once (must never happen: each number column is unique). */
    public static function duplicates(): array
    {
        $out = [];
        foreach (self::types() as $k => $t) {
            foreach (DB::column('SELECT `' . $t['col'] . '` FROM `' . $t['table'] . '` WHERE `' . $t['col'] . '` IS NOT NULL GROUP BY `' . $t['col'] . '` HAVING COUNT(*) > 1') as $no) {
                $out[] = [$k, $no];
            }
        }
        return $out;
    }
}
