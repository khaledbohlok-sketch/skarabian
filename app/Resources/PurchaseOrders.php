<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\Approvals;
use App\Services\Money;
use App\Services\Sequence;

/**
 * Purchase orders: draft (lines) → approval by Owner / GM → goods received. Receiving adds the items to stock
 * (weighted average price) and creates the supplier bill automatically.
 */
class PurchaseOrders extends Resource
{
    public string $key = 'purchase-orders';
    public string $table = 'purchase_orders';
    public string $module = 'finance';
    public string $recordType = 'purchase_order';
    public string $title = 'nav.purchase_orders';
    public string $singular = 'po.singular';
    public array $search = ['t.number', 's.name_en', 't.notes'];
    public string $sort = 't.order_date DESC, t.id DESC';
    public bool $financial = true;

    public const STATUSES = ['draft' => 'po.status_draft', 'pending' => 'po.status_pending', 'approved' => 'po.status_approved', 'received' => 'po.status_received', 'cancelled' => 'po.status_cancelled'];

    public function canList(): bool
    {
        return Auth::can('finance') || Auth::can('inventory');
    }

    public function canView(array $row): bool
    {
        return $this->canList();
    }

    public function fields(): array
    {
        return [
            'number'        => ['type' => 'text', 'label' => 'po.number', 'readonly' => true, 'edit_only' => true, 'col' => 4],
            'supplier_id'   => ['type' => 'picker', 'source' => 'suppliers', 'label' => 'po.supplier', 'required' => true, 'col' => 8],
            'order_date'    => ['type' => 'date', 'label' => 'po.order_date', 'required' => true, 'default' => fn () => date('Y-m-d'), 'col' => 4],
            'expected_date' => ['type' => 'date', 'label' => 'po.expected_date', 'col' => 4],
            'category_id'   => ['type' => 'picker', 'source' => 'top_categories', 'filter' => ['type' => 'expense'], 'label' => 'finance.category', 'required' => true, 'col' => 4, 'no_add' => true],
            'currency'      => ['type' => 'currency', 'label' => 'finance.currency', 'default' => 'QAR', 'required' => true, 'col' => 6],
            'exchange_rate' => ['type' => 'decimal', 'label' => 'finance.exchange_rate', 'default' => 1, 'col' => 6, 'help' => 'bills.rate_help'],
            'notes'         => ['type' => 'textarea', 'label' => 'common.notes', 'rows' => 3],
        ];
    }

    public function from(): string
    {
        return '`purchase_orders` t JOIN parties s ON s.id = t.supplier_id';
    }

    public function columns(): array
    {
        return [
            'number'   => ['label' => 'po.number', 'sql' => 't.number', 'sort' => true],
            'date'     => ['label' => 'po.order_date', 'sql' => 't.order_date', 'fmt' => 'date', 'sort' => true],
            'supplier' => ['label' => 'po.supplier', 'sql' => 's.name_en', 'sort' => true, 'hide_in_parent' => true],
            'total'    => ['label' => 'po.total_qar', 'sql' => 't.total_qar', 'fmt' => 'money', 'sort' => true],
            'status'   => ['label' => 'common.status', 'sql' => 't.status', 'fmt' => 'badge', 'prefix' => 'po.status'],
            'expected' => ['label' => 'po.expected_date', 'sql' => 't.expected_date', 'fmt' => 'date'],
        ];
    }

    public function filters(): array
    {
        return [
            'status'      => ['type' => 'select', 'label' => 'common.status', 'sql' => 't.status', 'options' => self::STATUSES],
            'supplier_id' => ['type' => 'picker', 'source' => 'suppliers', 'label' => 'po.supplier', 'sql' => 't.supplier_id'],
            'from'        => ['type' => 'date_from', 'label' => 'common.from', 'sql' => 't.order_date'],
            'to'          => ['type' => 'date_to', 'label' => 'common.to', 'sql' => 't.order_date'],
        ];
    }

    public function label(array $row): string
    {
        return (string) $row['number'];
    }

    public function headerBadges(array $row): string
    {
        return status_badge($row['status'], 'po.status');
    }

    public function canEdit(array $row): bool
    {
        return $row['status'] === 'draft' && parent::canEdit($row);
    }

    public function canDelete(array $row): bool
    {
        return in_array($row['status'], ['draft', 'cancelled'], true) && parent::canDelete($row);
    }

    public function links(): array
    {
        return [['bills', 'purchase_order_id', 'nav.bills']];
    }

    public function prepare(array $data, ?array $old): array
    {
        $rate = $data['currency'] === Money::BASE ? 1.0 : (float) ($data['exchange_rate'] ?? 0);
        if ($rate <= 0) {
            throw ValidationException::one('exchange_rate', 'bills.rate_required');
        }
        if (!empty($data['expected_date']) && $data['expected_date'] < $data['order_date']) {
            throw ValidationException::one('expected_date', 'bills.due_before_date');
        }
        $data['exchange_rate'] = $rate;
        if (!$old) {
            $data['number'] = Sequence::monthly('PO', $data['order_date']);
            $data['status'] = 'draft';
        }
        return $data;
    }

    /** A rate change re-prices the lines in QAR. */
    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        if ($old && (float) $old['exchange_rate'] !== (float) $data['exchange_rate']) {
            \App\Services\InventoryService::repricePo($id);
        }
    }

    public function afterDelete(array $row): void
    {
        Approvals::cancelFor('purchase_order', (int) $row['id']);
    }

    public function redirectAfterSave(int $id, array $data): string
    {
        return '/portal/purchase-orders/' . $id . '/lines';
    }

    public function afterDetails(array $row): string
    {
        return View::partial('portal/finance/lines_view', ['kind' => 'po', 'row' => $row, 'res' => $this]);
    }

    public function actions(array $row): array
    {
        $a = [];
        $id = (int) $row['id'];
        $lines = (int) DB::value('SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id = ?', [$id]);
        if ($row['status'] === 'draft' && $this->canEdit($row)) {
            $a[] = ['label' => 'po.edit_lines', 'url' => "/portal/purchase-orders/$id/lines"];
            if ($lines) {
                $a[] = ['label' => 'po.submit', 'url' => "/portal/purchase-orders/$id/submit", 'method' => 'post', 'class' => 'btn-primary'];
            }
        }
        if ($row['status'] === 'pending') {
            $ap = DB::row("SELECT * FROM approvals WHERE type = 'purchase_order' AND record_id = ? AND status = 'pending'", [$id]);
            if ($ap && Approvals::canDecide($ap)) {
                $a[] = ['label' => 'common.approve', 'url' => '/portal/approvals/' . $ap['id'] . '/approve', 'method' => 'post', 'class' => 'btn-primary'];
                $a[] = ['label' => 'common.reject', 'url' => '/portal/approvals/' . $ap['id'] . '/reject', 'method' => 'post', 'class' => 'btn-danger', 'confirm' => 'approvals.confirm_reject'];
            }
        }
        if ($row['status'] === 'approved' && (Auth::can('inventory', 'create') || Auth::can('finance', 'create'))) {
            $a[] = ['label' => 'po.receive', 'url' => "/portal/purchase-orders/$id/receive", 'method' => 'post', 'class' => 'btn-primary', 'confirm' => 'po.confirm_receive'];
        }
        if (in_array($row['status'], ['draft', 'pending', 'approved'], true) && Auth::can('finance', 'edit')) {
            $a[] = ['label' => 'po.cancel', 'url' => "/portal/purchase-orders/$id/cancel", 'method' => 'post', 'confirm' => 'po.confirm_cancel'];
        }
        if ($row['bill_id'] && Auth::can('finance')) {
            $a[] = ['label' => 'po.open_bill', 'url' => '/portal/bills/' . $row['bill_id']];
        }
        if (\App\Services\Studio::canCreate('po')) {
            $a[] = ['label' => 'po.print', 'url' => '/portal/studio/new/po?record_id=' . $id];
        }
        return $a;
    }

    public function tabs(array $row): array
    {
        return $this->standardTabs($row, ['categories' => ['document'], 'studio' => ['po']]);
    }
}
