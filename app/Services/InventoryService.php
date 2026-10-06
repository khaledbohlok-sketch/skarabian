<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;

/**
 * Stock movements. Every change of quantity is a movement row (full history):
 *  - medicine / feed logged on a horse  → stock out, cost allocated to that horse
 *  - purchase order received            → stock in (weighted average price) + bill
 */
final class InventoryService
{
    /** Keeps the stock movement of a health record / diet log in sync with its item and quantity. */
    public static function syncUsage(string $source, int $sourceId, ?int $horseId, ?int $itemId, ?float $qty, string $date): void
    {
        $col = match ($source) {
            'health' => 'health_record_id',
            'diet'   => 'diet_log_id',
            default  => throw new \InvalidArgumentException($source),
        };
        DB::transaction(function () use ($col, $sourceId, $horseId, $itemId, $qty, $date) {
            // Reverse the previous usage (if any), then book the new one
            foreach (DB::all("SELECT * FROM stock_movements WHERE $col = ? AND direction = 'out'", [$sourceId]) as $m) {
                DB::run('UPDATE inventory_items SET quantity = quantity + ? WHERE id = ?', [$m['quantity'], $m['item_id']]);
                DB::insert('stock_movements', [
                    'item_id' => $m['item_id'], 'direction' => 'adjust', 'quantity' => $m['quantity'], 'unit_price_qar' => $m['unit_price_qar'],
                    'total_qar' => -1 * (float) $m['total_qar'], 'movement_date' => date('Y-m-d'), 'horse_id' => $m['horse_id'], $col => null,
                    'note' => 'Reversal of usage #' . $m['id'], 'created_by' => Auth::id(),
                ]);
                DB::run("UPDATE stock_movements SET direction = 'adjust', note = CONCAT(COALESCE(note,''), ' (reversed)') WHERE id = ?", [$m['id']]);
            }
            if ($itemId && $qty !== null && $qty > 0) {
                $item = DB::row('SELECT * FROM inventory_items WHERE id = ? FOR UPDATE', [$itemId]);
                if (!$item) {
                    return;
                }
                if ((float) $item['quantity'] < $qty) {
                    throw new \DomainException(__('inventory.not_enough', ['item' => $item['name_en'], 'qty' => rtrim(rtrim((string) $item['quantity'], '0'), '.'), 'unit' => $item['unit']]));
                }
                DB::run('UPDATE inventory_items SET quantity = quantity - ? WHERE id = ?', [$qty, $itemId]);
                DB::insert('stock_movements', [
                    'item_id' => $itemId, 'direction' => 'out', 'quantity' => $qty, 'unit_price_qar' => $item['unit_price_qar'],
                    'total_qar' => round($qty * (float) $item['unit_price_qar'], 2), 'movement_date' => $date, 'horse_id' => $horseId,
                    $col => $sourceId, 'created_by' => Auth::id(),
                ]);
                self::checkLow((int) $itemId);
            }
        });
    }

    /** Manual stock in / out / adjustment from the item page. */
    public static function move(int $itemId, string $direction, float $qty, ?float $unitPrice, ?int $horseId, ?string $note, string $date, ?int $billId = null, ?int $poId = null): void
    {
        DB::transaction(function () use ($itemId, $direction, $qty, $unitPrice, $horseId, $note, $date, $billId, $poId) {
            $item = DB::row('SELECT * FROM inventory_items WHERE id = ? FOR UPDATE', [$itemId]);
            if (!$item || $qty == 0.0) {
                throw new \DomainException(__('validation.invalid'));
            }
            $price = $unitPrice ?? (float) $item['unit_price_qar'];
            if ($direction === 'in') {
                // Weighted average unit price
                $newQty = (float) $item['quantity'] + $qty;
                $avg = $newQty > 0 ? round(((float) $item['quantity'] * (float) $item['unit_price_qar'] + $qty * $price) / $newQty, 2) : $price;
                DB::run('UPDATE inventory_items SET quantity = ?, unit_price_qar = ? WHERE id = ?', [$newQty, $avg, $itemId]);
            } elseif ($direction === 'out') {
                if ((float) $item['quantity'] < $qty) {
                    throw new \DomainException(__('inventory.not_enough', ['item' => $item['name_en'], 'qty' => rtrim(rtrim((string) $item['quantity'], '0'), '.'), 'unit' => $item['unit']]));
                }
                DB::run('UPDATE inventory_items SET quantity = quantity - ? WHERE id = ?', [$qty, $itemId]);
            } else { // adjust: qty is the counted new quantity
                $diff = $qty - (float) $item['quantity'];
                DB::run('UPDATE inventory_items SET quantity = ? WHERE id = ?', [$qty, $itemId]);
                $qty = $diff;
            }
            DB::insert('stock_movements', [
                'item_id' => $itemId, 'direction' => $direction, 'quantity' => $qty, 'unit_price_qar' => $price,
                'total_qar' => round($qty * $price, 2), 'movement_date' => $date, 'horse_id' => $horseId,
                'bill_id' => $billId, 'purchase_order_id' => $poId, 'note' => $note, 'created_by' => Auth::id(),
            ]);
            Audit::log('stock', 'inventory', 'item', $itemId, ['quantity' => $item['quantity']], ['direction' => $direction, 'quantity' => $qty, 'horse_id' => $horseId], $item['name_en']);
            self::checkLow($itemId);
        });
        Cache::bump();
    }

    public static function checkLow(int $itemId): void
    {
        $i = DB::row('SELECT id, name_en, quantity, min_quantity, unit FROM inventory_items WHERE id = ?', [$itemId]);
        if ($i && (float) $i['min_quantity'] > 0 && (float) $i['quantity'] <= (float) $i['min_quantity']) {
            Notifier::permitted('inventory', 'edit', 'low_stock', __('notify.low_stock', ['item' => $i['name_en']]),
                __('notify.low_stock_body', ['qty' => rtrim(rtrim((string) $i['quantity'], '0'), '.') . ' ' . $i['unit']]), '/portal/items/' . $i['id'], false, 'low-' . $i['id'] . '-' . date('Y-m-d'));
        }
    }

    /** Allocated inventory cost per horse (used feed and medicine). */
    public static function horseUsageCost(int $horseId, ?string $from = null, ?string $to = null): float
    {
        $params = [$horseId];
        // Usage rows (out) plus reversals (adjust: the reversed row and its negative counterpart cancel out)
        $sql = "SELECT COALESCE(SUM(total_qar),0) FROM stock_movements WHERE horse_id = ? AND direction IN ('out','adjust')";
        if ($from) {
            $sql .= ' AND movement_date >= ?';
            $params[] = $from;
        }
        if ($to) {
            $sql .= ' AND movement_date <= ?';
            $params[] = $to;
        }
        return (float) DB::value($sql, $params);
    }

    // ------------------------------------------------------------- purchase orders

    /** Replaces the lines of a draft purchase order. $lines: [[item_id, quantity, unit_price (PO currency)], ...] */
    public static function savePoLines(int $poId, array $lines): void
    {
        DB::transaction(function () use ($poId, $lines) {
            $po = DB::row('SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE', [$poId]);
            if (!$po || $po['status'] !== 'draft') {
                throw new \DomainException(__('po.not_draft'));
            }
            DB::run('DELETE FROM purchase_order_items WHERE purchase_order_id = ?', [$poId]);
            foreach ($lines as [$itemId, $qty, $price]) {
                DB::insert('purchase_order_items', ['purchase_order_id' => $poId, 'item_id' => $itemId, 'quantity' => $qty, 'unit_price' => $price, 'unit_price_qar' => 0, 'line_total_qar' => 0]);
            }
            self::repricePo($poId);
            Audit::log('update', 'finance', 'purchase_order', $poId, null, ['lines' => count($lines)], $po['number']);
        });
    }

    /** QAR prices of the lines and the PO total from the PO's saved exchange rate. */
    public static function repricePo(int $poId): void
    {
        $rate = (float) DB::value('SELECT exchange_rate FROM purchase_orders WHERE id = ?', [$poId]);
        DB::run('UPDATE purchase_order_items SET unit_price_qar = ROUND(unit_price * ?, 2), line_total_qar = ROUND(quantity * unit_price * ?, 2) WHERE purchase_order_id = ?', [$rate, $rate, $poId]);
        DB::run('UPDATE purchase_orders SET total_qar = (SELECT COALESCE(SUM(line_total_qar), 0) FROM purchase_order_items WHERE purchase_order_id = ?) WHERE id = ?', [$poId, $poId]);
    }

    /** Every purchase order is approved by the Owner or General Manager (the Owner's own orders are approved at once). */
    public static function submitPo(int $poId): string
    {
        $po = DB::row('SELECT * FROM purchase_orders WHERE id = ? AND deleted_at IS NULL', [$poId]);
        if (!$po || $po['status'] !== 'draft' || !DB::value('SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id = ?', [$poId])) {
            throw new \DomainException(__('po.cannot_submit'));
        }
        if (Auth::isOwner()) {
            DB::update('purchase_orders', ['status' => 'approved', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $poId]);
            Audit::log('approve', 'finance', 'purchase_order', $poId, ['status' => 'draft'], ['status' => 'approved'], $po['number']);
            return 'approved';
        }
        DB::update('purchase_orders', ['status' => 'pending', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $poId]);
        $supplier = (string) DB::value('SELECT name_en FROM parties WHERE id = ?', [$po['supplier_id']]);
        Approvals::request('purchase_order', 'purchase_order', $poId, $po['number'] . ' — ' . $supplier, (float) $po['total_qar']);
        return 'pending';
    }

    public static function approvePo(int $poId, array $payload, array $approval): void
    {
        DB::update('purchase_orders', ['status' => 'approved'], "id = :id AND status = 'pending'", ['id' => $poId]);
    }

    public static function rejectPo(int $poId, array $payload, array $approval): void
    {
        DB::update('purchase_orders', ['status' => 'draft'], "id = :id AND status = 'pending'", ['id' => $poId]);
    }

    /** Goods arrived: stock in for every line (weighted average price) and the supplier bill is created. */
    public static function receivePo(int $poId): int
    {
        return DB::transaction(function () use ($poId) {
            $po = DB::row('SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE', [$poId]);
            if (!$po || $po['status'] !== 'approved') {
                throw new \DomainException(__('po.cannot_receive'));
            }
            $lines = DB::all('SELECT l.*, i.name_en FROM purchase_order_items l JOIN inventory_items i ON i.id = l.item_id WHERE l.purchase_order_id = ?', [$poId]);
            $billId = FinanceService::create([
                'type' => 'expense', 'category_id' => $po['category_id'], 'party_id' => $po['supplier_id'],
                'description' => __('po.bill_description', ['number' => $po['number']]), 'currency' => $po['currency'], 'exchange_rate' => $po['exchange_rate'],
                'amount_original' => round((float) DB::value('SELECT SUM(quantity * unit_price) FROM purchase_order_items WHERE purchase_order_id = ?', [$poId]), 2),
                'amount_qar' => (float) $po['total_qar'], 'bill_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+30 days')),
                'purchase_order_id' => $poId, 'reference_no' => $po['number'],
            ]);
            foreach ($lines as $l) {
                self::move((int) $l['item_id'], 'in', (float) $l['quantity'], (float) $l['unit_price_qar'], null, $po['number'], date('Y-m-d'), $billId, $poId);
            }
            DB::update('purchase_orders', ['status' => 'received', 'received_at' => date('Y-m-d H:i:s'), 'bill_id' => $billId, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $poId]);
            Audit::log('receive', 'finance', 'purchase_order', $poId, ['status' => 'approved'], ['status' => 'received', 'bill_id' => $billId], $po['number']);
            return $billId;
        });
    }

    public static function cancelPo(int $poId): void
    {
        $po = DB::row('SELECT * FROM purchase_orders WHERE id = ?', [$poId]);
        if (!$po || !in_array($po['status'], ['draft', 'pending', 'approved'], true)) {
            throw new \DomainException(__('po.cannot_cancel'));
        }
        DB::update('purchase_orders', ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $poId]);
        Approvals::cancelFor('purchase_order', $poId);
        Audit::log('cancel', 'finance', 'purchase_order', $poId, ['status' => $po['status']], ['status' => 'cancelled'], $po['number']);
    }
}
