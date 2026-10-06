<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/**
 * "Everything linked" timeline for a record: merges all activity from linked tables, newest first.
 * Each source is included only when the user may see that module.
 */
final class Timeline
{
    public static function for(string $type, int $id): array
    {
        $items = match ($type) {
            'horse'    => self::horse($id),
            'employee' => self::employee($id),
            'party'    => self::party($id),
            'bill'     => self::bill($id),
            'embryo'   => self::embryo($id),
            default    => [],
        };
        if (Auth::can('studio')) {
            foreach (DB::all('SELECT id, ref_no, doc_type, created_at FROM documents WHERE record_type = ? AND record_id = ? AND deleted_at IS NULL', [$type, $id]) as $d) {
                $items[] = self::item($d['created_at'], 'document', $d['ref_no'] . ' — ' . __('studio.type_' . $d['doc_type']), null, '/portal/studio/' . $d['id']);
            }
        }
        usort($items, fn ($a, $b) => strcmp($b['date'], $a['date']));
        return array_slice($items, 0, 300);
    }

    private static function item(?string $date, string $kind, string $title, ?string $detail = null, ?string $url = null): array
    {
        return ['date' => (string) $date, 'kind' => $kind, 'title' => $title, 'detail' => $detail, 'url' => $url];
    }

    private static function horse(int $id): array
    {
        $out = [];
        if (Auth::can('horse_health')) {
            foreach (DB::all('SELECT id, record_date, type, title, next_due_date FROM health_records WHERE horse_id = ? AND deleted_at IS NULL ORDER BY record_date DESC LIMIT 100', [$id]) as $r) {
                $out[] = self::item($r['record_date'], 'health', __('health.type_' . $r['type']) . ': ' . $r['title'], $r['next_due_date'] ? __('health.next_due') . ' ' . fmt_date($r['next_due_date']) : null, '/portal/health-records/' . $r['id']);
            }
        }
        if (Auth::can('horse_diet')) {
            foreach (DB::all('SELECT d.id, d.log_date, d.feeding, d.quantity, i.name_en, i.unit FROM diet_logs d LEFT JOIN inventory_items i ON i.id = d.item_id WHERE d.horse_id = ? AND d.deleted_at IS NULL ORDER BY d.log_date DESC LIMIT 60', [$id]) as $r) {
                $out[] = self::item($r['log_date'], 'diet', __('diet.feeding_' . $r['feeding']) . ': ' . ($r['name_en'] ?? '') . ' ' . ($r['quantity'] !== null ? rtrim(rtrim((string) $r['quantity'], '0'), '.') . ' ' . $r['unit'] : ''), null, '/portal/diet-logs/' . $r['id']);
            }
        }
        if (Auth::can('horse_training')) {
            foreach (DB::all('SELECT id, log_date, activity FROM training_logs WHERE horse_id = ? AND deleted_at IS NULL ORDER BY log_date DESC LIMIT 60', [$id]) as $r) {
                $out[] = self::item($r['log_date'], 'training', $r['activity'], null, '/portal/training-logs/' . $r['id']);
            }
        }
        foreach (DB::all('SELECT r.id, s.start_date, s.name_en, r.title_en, r.medal, r.placing FROM show_results r JOIN shows s ON s.id = r.show_id WHERE r.horse_id = ? AND r.deleted_at IS NULL', [$id]) as $r) {
            $out[] = self::item($r['start_date'], 'show', $r['name_en'] . ($r['title_en'] ? ' — ' . $r['title_en'] : ''), $r['medal'] !== 'none' ? __('shows.medal_' . $r['medal']) : ($r['placing'] ? '#' . $r['placing'] : null), '/portal/show-results/' . $r['id']);
        }
        if (Auth::can('horse_breeding') || Auth::can('horses')) {
            foreach (DB::all('SELECT b.id, b.start_date, b.method, b.status, b.expected_foaling_date, b.mare_id, m.name_en AS mare, s.name_en AS stallion FROM breeding_records b JOIN horses m ON m.id = b.mare_id LEFT JOIN horses s ON s.id = b.stallion_id WHERE (b.mare_id = ? OR b.stallion_id = ?) AND b.deleted_at IS NULL', [$id, $id]) as $r) {
                $out[] = self::item($r['start_date'], 'breeding', __('breeding.method_' . $r['method']) . ': ' . $r['mare'] . ($r['stallion'] ? ' × ' . $r['stallion'] : ''), __('breeding.status_' . $r['status']) . ($r['expected_foaling_date'] ? ' · ' . __('breeding.expected_foaling') . ' ' . fmt_date($r['expected_foaling_date']) : ''), '/portal/breeding-records/' . $r['id']);
            }
        }
        if (Auth::can('embryos')) {
            foreach (DB::all('SELECT id, code, status, flush_date, transfer_date, donor_mare_id, recipient_mare_id, sire_id FROM embryos WHERE (donor_mare_id = ? OR recipient_mare_id = ? OR sire_id = ?) AND deleted_at IS NULL', [$id, $id, $id]) as $r) {
                $role = (int) $r['donor_mare_id'] === $id ? 'embryo_donor' : ((int) $r['recipient_mare_id'] === $id ? 'embryo_recipient' : 'embryo_sire');
                $out[] = self::item($r['transfer_date'] ?? $r['flush_date'] ?? '', 'embryo', $r['code'] . ' — ' . __('timeline.' . $role), __('embryos.status_' . $r['status']), '/portal/embryos/' . $r['id']);
            }
        }
        foreach (DB::all('SELECT id, name_en, dob FROM horses WHERE (sire_id = ? OR dam_id = ?) AND deleted_at IS NULL', [$id, $id]) as $r) {
            $out[] = self::item($r['dob'] ?? '', 'offspring', $r['name_en'], null, '/portal/horses/' . $r['id']);
        }
        if (Auth::can('finance')) {
            foreach (DB::all('SELECT id, number, bill_date, type, description, amount_qar, status FROM bills WHERE horse_id = ? AND deleted_at IS NULL ORDER BY bill_date DESC LIMIT 100', [$id]) as $r) {
                $out[] = self::item($r['bill_date'], 'bill', $r['number'] . ' — ' . ($r['description'] ?? ''), money($r['amount_qar'], 'QAR', false) . ' · ' . __('status.' . $r['status']), '/portal/bills/' . $r['id']);
            }
        }
        if (Auth::can('inventory') || Auth::can('horse_health') || Auth::can('horse_diet')) {
            foreach (DB::all('SELECT m.movement_date, m.quantity, m.total_qar, i.name_en, i.unit, i.id AS item_id FROM stock_movements m JOIN inventory_items i ON i.id = m.item_id WHERE m.horse_id = ? ORDER BY m.movement_date DESC LIMIT 100', [$id]) as $r) {
                $out[] = self::item($r['movement_date'], 'inventory', $r['name_en'] . ' − ' . rtrim(rtrim((string) $r['quantity'], '0'), '.') . ' ' . $r['unit'], Auth::can('finance') ? money($r['total_qar'], 'QAR', false) : null, '/portal/items/' . $r['item_id']);
            }
        }
        // How the horse came to the stud: born here or purchased
        $h = DB::row('SELECT dob, born_at_sk, purchase_date FROM horses WHERE id = ?', [$id]);
        if ($h && $h['born_at_sk'] && $h['dob']) {
            $out[] = self::item($h['dob'], 'acquired', __('timeline.born_at_sk'), null);
        } elseif ($h && $h['purchase_date']) {
            $out[] = self::item($h['purchase_date'], 'acquired', __('timeline.purchased'), null);
        }
        if (Auth::can('horse_notes')) {
            foreach (DB::all('SELECT n.id, n.created_at, n.note, u.name FROM horse_notes n LEFT JOIN users u ON u.id = n.created_by WHERE n.horse_id = ? AND n.deleted_at IS NULL ORDER BY n.id DESC LIMIT 60', [$id]) as $r) {
                $out[] = self::item($r['created_at'], 'note', mb_strimwidth($r['note'], 0, 120, '…'), $r['name'], '/portal/horse-notes/' . $r['id']);
            }
        }
        if (Auth::can('inbox')) {
            foreach (DB::all('SELECT id, created_at, name, status FROM inquiries WHERE horse_id = ? AND deleted_at IS NULL', [$id]) as $r) {
                $out[] = self::item($r['created_at'], 'inquiry', $r['name'], __('inbox.status_' . $r['status']), '/portal/inbox/' . $r['id']);
            }
        }
        return $out;
    }

    private static function employee(int $id): array
    {
        $out = [];
        foreach (DB::all('SELECT a.assigned_at, h.id, h.name_en FROM horse_assignments a JOIN horses h ON h.id = a.horse_id WHERE a.employee_id = ?', [$id]) as $r) {
            $out[] = self::item($r['assigned_at'], 'assignment', $r['name_en'], null, '/portal/horses/' . $r['id']);
        }
        if (Auth::can('payroll')) {
            foreach (DB::all('SELECT l.net_qar, l.paid_at, r.period, r.id, r.status FROM payroll_lines l JOIN payroll_runs r ON r.id = l.payroll_run_id WHERE l.employee_id = ?', [$id]) as $r) {
                $out[] = self::item($r['period'] . '-28', 'payroll', __('payroll.period') . ' ' . $r['period'], (Auth::can('payroll', 'sensitive') ? money($r['net_qar'], 'QAR', false) . ' · ' : '') . __('payroll.status_' . $r['status']), '/portal/payroll/' . $r['id']);
            }
        }
        if (Auth::can('finance')) {
            foreach (DB::all("SELECT id, number, bill_date, description, amount_qar FROM bills WHERE employee_id = ? AND deleted_at IS NULL AND payroll_run_id IS NULL ORDER BY bill_date DESC LIMIT 100", [$id]) as $r) {
                $out[] = self::item($r['bill_date'], 'bill', $r['number'] . ' — ' . $r['description'], money($r['amount_qar'], 'QAR', false), '/portal/bills/' . $r['id']);
            }
        }
        if (Auth::can('hr')) {
            foreach (DB::all('SELECT id, start_date, end_date, leave_type, days, status FROM leave_requests WHERE employee_id = ? AND deleted_at IS NULL', [$id]) as $r) {
                $out[] = self::item($r['start_date'], 'leave', __('leave.type_' . $r['leave_type']) . ' · ' . $r['days'] . ' ' . __('common.days'), __('status.' . $r['status']), '/portal/leave-requests/' . $r['id']);
            }
            foreach (DB::all("SELECT id, work_date, status FROM attendance WHERE employee_id = ? AND status IN ('absent','late','sick') AND deleted_at IS NULL ORDER BY work_date DESC LIMIT 60", [$id]) as $r) {
                $out[] = self::item($r['work_date'], 'attendance', __('attendance.status_' . $r['status']), null, '/portal/attendance/' . $r['id']);
            }
        }
        $userId = DB::value('SELECT id FROM users WHERE employee_id = ?', [$id]);
        if ($userId && Auth::isOwner()) {
            foreach (DB::all('SELECT id, created_at, action, summary FROM activity_log WHERE user_id = ? ORDER BY id DESC LIMIT 50', [$userId]) as $r) {
                $out[] = self::item($r['created_at'], 'system', __('activity.a_' . $r['action']), $r['summary'], '/portal/activity/' . $r['id']);
            }
        }
        return $out;
    }

    private static function party(int $id): array
    {
        $out = [];
        if (!Auth::can('finance')) {
            return $out;
        }
        foreach (DB::all('SELECT id, number, bill_date, type, description, amount_qar, status FROM bills WHERE party_id = ? AND deleted_at IS NULL ORDER BY bill_date DESC LIMIT 200', [$id]) as $r) {
            $out[] = self::item($r['bill_date'], 'bill', $r['number'] . ' — ' . ($r['description'] ?? ''), __('bills.type_' . $r['type']) . ' · ' . money($r['amount_qar'], 'QAR', false) . ' · ' . __('status.' . $r['status']), '/portal/bills/' . $r['id']);
        }
        foreach (DB::all('SELECT id, number, invoice_date, total_qar, status FROM invoices WHERE party_id = ? AND deleted_at IS NULL', [$id]) as $r) {
            $out[] = self::item($r['invoice_date'], 'invoice', $r['number'], money($r['total_qar'], 'QAR', false) . ' · ' . __('invoices.status_' . $r['status']), '/portal/invoices/' . $r['id']);
        }
        foreach (DB::all('SELECT id, number, order_date, total_qar, status FROM purchase_orders WHERE supplier_id = ? AND deleted_at IS NULL', [$id]) as $r) {
            $out[] = self::item($r['order_date'], 'purchase_order', $r['number'], money($r['total_qar'], 'QAR', false) . ' · ' . __('po.status_' . $r['status']), '/portal/purchase-orders/' . $r['id']);
        }
        foreach (DB::all('SELECT p.payment_date, p.amount_qar, b.number, b.id FROM bill_payments p JOIN bills b ON b.id = p.bill_id WHERE b.party_id = ? AND p.deleted_at IS NULL', [$id]) as $r) {
            $out[] = self::item($r['payment_date'], 'payment', $r['number'], money($r['amount_qar'], 'QAR', false), '/portal/bills/' . $r['id']);
        }
        return $out;
    }

    private static function bill(int $id): array
    {
        $out = [];
        foreach (DB::all('SELECT p.*, a.name AS account FROM bill_payments p LEFT JOIN accounts a ON a.id = p.account_id WHERE p.bill_id = ? AND p.deleted_at IS NULL', [$id]) as $r) {
            $out[] = self::item($r['payment_date'], 'payment', money($r['amount_qar'], 'QAR', false), $r['account']);
        }
        foreach (DB::all('SELECT a.*, u.name AS requester, d.name AS decider FROM approvals a LEFT JOIN users u ON u.id = a.requested_by LEFT JOIN users d ON d.id = a.decided_by WHERE a.record_type = ? AND a.record_id = ?', ['bill', $id]) as $r) {
            $out[] = self::item($r['requested_at'], 'approval', __('approvals.requested_by') . ' ' . $r['requester'], null);
            if ($r['decided_at']) {
                $out[] = self::item($r['decided_at'], 'approval', __('approvals.status_' . $r['status']) . ' — ' . $r['decider'], $r['decision_note']);
            }
        }
        foreach (DB::all('SELECT m.movement_date, m.quantity, i.name_en, i.unit, i.id FROM stock_movements m JOIN inventory_items i ON i.id = m.item_id WHERE m.bill_id = ?', [$id]) as $r) {
            $out[] = self::item($r['movement_date'], 'inventory', $r['name_en'] . ' + ' . rtrim(rtrim((string) $r['quantity'], '0'), '.') . ' ' . $r['unit'], null, '/portal/items/' . $r['id']);
        }
        return $out;
    }

    private static function embryo(int $id): array
    {
        $out = [];
        if (Auth::can('finance')) {
            foreach (DB::all('SELECT id, number, bill_date, description, amount_qar, type FROM bills WHERE embryo_id = ? AND deleted_at IS NULL', [$id]) as $r) {
                $out[] = self::item($r['bill_date'], 'bill', $r['number'] . ' — ' . ($r['description'] ?? ''), money($r['amount_qar'], 'QAR', false), '/portal/bills/' . $r['id']);
            }
        }
        foreach (DB::all('SELECT c.check_date, c.result, c.notes FROM pregnancy_checks c JOIN breeding_records b ON b.id = c.breeding_record_id WHERE b.embryo_id = ? AND c.deleted_at IS NULL', [$id]) as $r) {
            $out[] = self::item($r['check_date'], 'check', __('breeding.result_' . $r['result']), $r['notes']);
        }
        if (Auth::can('inbox')) {
            foreach (DB::all('SELECT id, created_at, name FROM inquiries WHERE embryo_id = ? AND deleted_at IS NULL', [$id]) as $r) {
                $out[] = self::item($r['created_at'], 'inquiry', $r['name'], null, '/portal/inbox/' . $r['id']);
            }
        }
        return $out;
    }
}
