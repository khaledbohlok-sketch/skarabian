<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Services\RecurringBillService;

/**
 * Recurring bills: rent, a feed contract, a subscription… entered once and created as a bill on every date of the
 * schedule by the daily "recurring" task (RecurringBillService). The bills it made are listed on its page.
 */
class RecurringBills extends Resource
{
    public string $key = 'recurring-bills';
    public string $table = 'recurring_bills';
    public string $module = 'finance';
    public string $recordType = 'recurring_bill';
    public string $title = 'nav.recurring_bills';
    public string $singular = 'recurring.singular';
    public array $search = ['t.name', 't.description', 'p.name_en'];
    public string $sort = 't.active DESC, t.next_date, t.name';

    public function sections(): array
    {
        return ['main' => 'recurring.sec_bill', 'when' => 'recurring.sec_schedule', 'links' => ['label' => 'bills.sec_links', 'collapsed' => true]];
    }

    public function fields(): array
    {
        return [
            'name'            => ['type' => 'text', 'label' => 'recurring.name', 'required' => true, 'max' => 150, 'col' => 6, 'help' => 'recurring.name_help'],
            'type'            => ['type' => 'segment', 'label' => 'common.type', 'options' => Bills::TYPES, 'required' => true, 'default' => 'expense', 'col' => 6],
            'category_id'     => ['type' => 'picker', 'source' => 'top_categories', 'label' => 'finance.category', 'required' => true, 'depends' => ['type' => 'type'], 'col' => 6, 'no_add' => true],
            'subcategory_id'  => ['type' => 'picker', 'source' => 'subcategories', 'label' => 'bills.subcategory', 'depends' => ['parent_id' => 'category_id'], 'col' => 6, 'no_add' => true],
            'party_id'        => ['type' => 'picker', 'source' => 'parties', 'label' => 'bills.party', 'col' => 6, 'help' => 'bills.party_help'],
            'description'     => ['type' => 'text', 'label' => 'common.description', 'max' => 200, 'col' => 6, 'no_phone' => true, 'help' => 'recurring.description_help'],
            'currency'        => ['type' => 'currency', 'label' => 'finance.currency', 'default' => 'QAR', 'required' => true, 'col' => 6],
            'amount_original' => ['type' => 'money', 'label' => 'bills.amount', 'required' => true, 'min' => 0.01, 'col' => 6, 'currency_field' => 'currency', 'help' => 'recurring.amount_help'],

            'frequency'       => ['type' => 'select', 'label' => 'recurring.frequency', 'section' => 'when', 'required' => true, 'default' => 'monthly', 'col' => 4,
                                  'options' => array_combine(RecurringBillService::FREQUENCIES, array_map(fn ($f) => 'recurring.freq_' . $f, RecurringBillService::FREQUENCIES))],
            'start_date'      => ['type' => 'date', 'label' => 'recurring.start_date', 'section' => 'when', 'required' => true, 'default' => fn () => date('Y-m-d', strtotime('first day of next month')), 'col' => 4, 'help' => 'recurring.start_help'],
            'end_date'        => ['type' => 'date', 'label' => 'recurring.end_date', 'section' => 'when', 'col' => 4, 'help' => 'recurring.end_help'],
            'due_days'        => ['type' => 'int', 'label' => 'recurring.due_days', 'section' => 'when', 'min' => 0, 'default' => 0, 'col' => 4, 'help' => 'recurring.due_days_help'],
            'active'          => ['type' => 'checkbox', 'label' => 'recurring.active', 'section' => 'when', 'default' => 1, 'col' => 4, 'help' => 'recurring.active_help'],
            'next_info'       => ['type' => 'display', 'label' => 'recurring.next_date', 'section' => 'when', 'col' => 4, 'edit_only' => true,
                                  'render' => fn ($r) => $r && $r['active'] ? e(fmt_date($r['next_date'])) : '<span class="muted">—</span>'],

            'horse_id'        => ['type' => 'picker', 'source' => 'horses', 'label' => 'bills.horse', 'section' => 'links', 'col' => 6],
            'employee_id'     => ['type' => 'picker', 'source' => 'employees', 'label' => 'bills.employee', 'section' => 'links', 'col' => 6],
            'notes'           => ['type' => 'textarea', 'label' => 'common.notes', 'section' => 'links', 'rows' => 3],
        ];
    }

    public function from(): string
    {
        return '`recurring_bills` t LEFT JOIN parties p ON p.id = t.party_id LEFT JOIN finance_categories c ON c.id = t.category_id';
    }

    public function columns(): array
    {
        return [
            'name'      => ['label' => 'recurring.name', 'sql' => 't.name', 'sort' => true],
            'category'  => ['label' => 'finance.category', 'sql' => self::nameSql('c')],
            'party'     => ['label' => 'bills.party', 'sql' => self::nameSql('p'), 'sort' => true],
            'amount'    => ['label' => 'bills.amount', 'sql' => "CONCAT(FORMAT(t.amount_original, 2), ' ', t.currency)"],
            'frequency' => ['label' => 'recurring.frequency', 'sql' => 't.frequency', 'fmt' => 'enum', 'prefix' => 'recurring.freq'],
            'next'      => ['label' => 'recurring.next_date', 'sql' => 'IF(t.active = 1, t.next_date, NULL)', 'fmt' => 'date', 'sort' => true],
            'made'      => ['label' => 'recurring.bills_created', 'sql' => 't.bills_created', 'fmt' => 'num'],
            'active'    => ['label' => 'recurring.active', 'sql' => 't.active', 'fmt' => 'bool'],
        ];
    }

    public function filters(): array
    {
        return [
            'active'      => ['type' => 'select', 'label' => 'recurring.active', 'sql' => 't.active', 'options' => ['1' => 'common.yes', '0' => 'common.no']],
            'frequency'   => ['type' => 'select', 'label' => 'recurring.frequency', 'sql' => 't.frequency',
                              'options' => array_combine(RecurringBillService::FREQUENCIES, array_map(fn ($f) => 'recurring.freq_' . $f, RecurringBillService::FREQUENCIES))],
            'category_id' => ['type' => 'picker', 'source' => 'top_categories', 'label' => 'finance.category', 'sql' => 't.category_id'],
            'party_id'    => ['type' => 'picker', 'source' => 'parties', 'label' => 'bills.party', 'sql' => 't.party_id'],
        ];
    }

    public function rowClass(array $row): string
    {
        return empty($row['active']) ? 'row-muted' : '';
    }

    public function label(array $row): string
    {
        return (string) $row['name'];
    }

    /** The bills it made stay when a recurring bill is deleted: they are real bills with their own history. */
    public function linkedCounts(int $id): array
    {
        return [];
    }

    public function tabs(array $row): array
    {
        // no "+ Add": a bill typed in there would not be one of its own (only the daily task makes those)
        return ['bills' => ['label' => 'nav.bills', 'related' => 'bills', 'filter' => ['recurring_bill_id' => (int) $row['id']], 'note' => 'recurring.bills_note', 'no_add' => true]];
    }

    public function prepare(array $data, ?array $old): array
    {
        $errors = [];
        $cat = DB::row('SELECT type FROM finance_categories WHERE id = ?', [$data['category_id']]);
        if ($cat && !in_array($cat['type'], [$data['type'], 'any'], true)) {
            $errors['category_id'] = __('bills.category_type');
        }
        if (!empty($data['subcategory_id']) && (int) DB::value('SELECT parent_id FROM finance_categories WHERE id = ?', [$data['subcategory_id']]) !== (int) $data['category_id']) {
            $errors['subcategory_id'] = __('bills.subcategory_mismatch');
        }
        if (!empty($data['end_date']) && $data['end_date'] < $data['start_date']) {
            $errors['end_date'] = __('recurring.end_before_start');
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        $data['due_days'] = (int) ($data['due_days'] ?? 0);
        $data['saved_by'] = Auth::id(); // its bills are this person's request (RecurringBillService::createBill)
        if (!$old) {
            $data['next_date'] = $data['start_date']; // a start in the past also makes the bills of the past dates
            return $data;
        }
        // Rescheduled, or switched back on: continue from the first date after the last bill and not before today,
        // so it never makes a date twice nor a pile of missed bills at once
        $lastBill = DB::value('SELECT MAX(bill_date) FROM bills WHERE recurring_bill_id = ?', [$old['id']]);
        $from = max(date('Y-m-d'), $lastBill ? date('Y-m-d', strtotime($lastBill . ' +1 day')) : '0000-00-00');
        if ($data['start_date'] !== $old['start_date'] || $data['frequency'] !== $old['frequency']) {
            $data['next_date'] = (int) $old['bills_created'] === 0 ? $data['start_date'] : RecurringBillService::firstOnOrAfter($data['start_date'], $data['frequency'], $from);
        } elseif (!empty($data['active']) && empty($old['active']) && $old['next_date'] < $from) {
            $data['next_date'] = RecurringBillService::firstOnOrAfter($data['start_date'], $data['frequency'], $from);
        }
        return $data;
    }
}
