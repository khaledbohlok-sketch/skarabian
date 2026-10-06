<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;

/** Clients and suppliers. Contact data is sensitive (hidden without finance "view sensitive"). */
class Parties extends Resource
{
    public string $key = 'parties';
    public string $table = 'parties';
    public string $module = 'finance';
    public string $recordType = 'party';
    public string $title = 'nav.parties';
    public string $singular = 'parties.singular';
    public array $search = ['t.name_en', 't.name_ar', 't.contact_person', 't.phone', 't.email'];
    public string $sort = 't.name_en ASC';
    public bool $archivable = true;

    public const TYPES = ['supplier' => 'parties.type_supplier', 'client' => 'parties.type_client', 'both' => 'parties.type_both'];

    public function fields(): array
    {
        return [
            'type'           => ['type' => 'select', 'label' => 'common.type', 'options' => self::TYPES, 'required' => true, 'default' => 'supplier', 'col' => 4],
            'name_en'        => ['type' => 'text', 'label' => 'parties.name_en', 'required' => true, 'max' => 150, 'col' => 8, 'no_phone' => true],
            'name_ar'        => ['type' => 'text', 'label' => 'parties.name_ar', 'max' => 150, 'col' => 6, 'attrs' => ['dir' => 'rtl'], 'no_phone' => true],
            'contact_person' => ['type' => 'text', 'label' => 'parties.contact_person', 'max' => 120, 'col' => 6, 'no_phone' => true],
            'phone'          => ['type' => 'phone', 'label' => 'common.phone', 'col' => 4, 'sensitive' => true],
            'email'          => ['type' => 'email', 'label' => 'common.email', 'col' => 4, 'sensitive' => true],
            'id_number'      => ['type' => 'text', 'label' => 'parties.id_number', 'max' => 60, 'col' => 4, 'sensitive' => true],
            'address'        => ['type' => 'text', 'label' => 'parties.address', 'max' => 255, 'col' => 8, 'sensitive' => true],
            'country'        => ['type' => 'text', 'label' => 'parties.country', 'max' => 60, 'col' => 4],
            'nationality'    => ['type' => 'text', 'label' => 'employees.nationality', 'max' => 60, 'col' => 4],
            'tax_no'         => ['type' => 'text', 'label' => 'parties.tax_no', 'max' => 60, 'col' => 4],
            'notes'          => ['type' => 'textarea', 'label' => 'common.notes', 'rows' => 3],
        ];
    }

    public function columns(): array
    {
        $open = "('approved','partially_paid','overdue')";
        return [
            'name'     => ['label' => 'parties.name_en', 'sql' => 't.name_en', 'sort' => true],
            'type'     => ['label' => 'common.type', 'sql' => 't.type', 'fmt' => 'enum', 'prefix' => 'parties.type'],
            'contact'  => ['label' => 'parties.contact_person', 'sql' => 't.contact_person'],
            'phone'    => ['label' => 'common.phone', 'sql' => 't.phone', 'sensitive' => true],
            'owed_us'  => ['label' => 'parties.owed_to_us', 'fmt' => 'money', 'sql' => "(SELECT COALESCE(SUM(b.amount_qar - b.paid_qar), 0) FROM bills b WHERE b.party_id = t.id AND b.type = 'income' AND b.status IN $open AND b.deleted_at IS NULL)"],
            'we_owe'   => ['label' => 'parties.we_owe', 'fmt' => 'money', 'sql' => "(SELECT COALESCE(SUM(b.amount_qar - b.paid_qar), 0) FROM bills b WHERE b.party_id = t.id AND b.type <> 'income' AND b.status IN $open AND b.deleted_at IS NULL)"],
        ];
    }

    public function filters(): array
    {
        return [
            'type'     => ['type' => 'raw', 'label' => 'common.type', 'options' => ['supplier' => 'parties.type_supplier', 'client' => 'parties.type_client'],
                           'sqlFor' => ['supplier' => "t.type IN ('supplier','both')", 'client' => "t.type IN ('client','both')"]],
            'archived' => ['type' => 'archived', 'label' => 'common.show_archived'],
        ];
    }

    public function links(): array
    {
        return [['bills', 'party_id', 'nav.bills'], ['invoices', 'party_id', 'nav.invoices'], ['purchase_orders', 'supplier_id', 'nav.purchase_orders'],
                ['inventory_items', 'supplier_id', 'nav.items'], ['horses', 'owner_party_id', 'nav.horses'], ['embryos', 'owner_party_id', 'nav.embryos']];
    }

    public function prepare(array $data, ?array $old): array
    {
        $data['name_en'] = preg_replace('/\s+/', ' ', $data['name_en']);
        if (DB::value('SELECT id FROM parties WHERE name_en = ? AND id <> ? AND deleted_at IS NULL', [$data['name_en'], $old['id'] ?? 0])) {
            throw ValidationException::one('name_en', 'parties.duplicate');
        }
        return $data;
    }

    public function afterDetails(array $row): string
    {
        return View::partial('portal/finance/party_balance', ['p' => $row]);
    }

    public function tabs(array $row): array
    {
        $id = (int) $row['id'];
        $t = ['bills' => ['label' => 'nav.bills', 'related' => 'bills', 'filter' => ['party_id' => $id], 'count' => (int) DB::value('SELECT COUNT(*) FROM bills WHERE party_id = ? AND deleted_at IS NULL', [$id]) ?: null]];
        if ($row['type'] !== 'supplier') {
            $t['invoices'] = ['label' => 'nav.invoices', 'related' => 'invoices', 'filter' => ['party_id' => $id]];
        }
        if ($row['type'] !== 'client') {
            $t['purchase-orders'] = ['label' => 'nav.purchase_orders', 'related' => 'purchase-orders', 'filter' => ['supplier_id' => $id]];
        }
        return $t + $this->standardTabs($row, ['categories' => ['document'], 'studio' => $row['type'] !== 'supplier' ? ['board'] : []]);
    }
}
