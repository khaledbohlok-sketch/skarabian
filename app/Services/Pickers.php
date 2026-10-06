<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/**
 * Searchable dropdown sources. Every selection of a horse, employee, supplier, client, item... uses these:
 * no free-text names. Only id + display label are returned, and only to users allowed to see the source.
 */
final class Pickers
{
    public static function sources(): array
    {
        $horseLabel = "CONCAT(t.name_en, IF(t.name_ar IS NULL OR t.name_ar = '', '', CONCAT(' / ', t.name_ar)), IF(t.is_external = 1, ' (ext.)', ''))";
        return [
            'horses' => [
                'table' => 'horses', 'label' => $horseLabel, 'search' => ['t.name_en', 't.name_ar', 't.registration_no', 't.microchip'],
                'grantedBy' => ['horses', 'finance', 'embryos', 'inventory', 'studio', 'cms', 'horse_diet', 'horse_health'],
                'filters' => ['sex' => 't.sex', 'category' => 't.category', 'is_external' => 't.is_external'],
                'create' => ['/portal/horses/create', 'horses'],
                'scoped' => true,
            ],
            'mares' => [
                'table' => 'horses', 'label' => $horseLabel, 'search' => ['t.name_en', 't.name_ar', 't.registration_no'],
                'where' => "t.sex = 'female'", 'grantedBy' => ['horses', 'embryos', 'horse_breeding'],
                'create' => ['/portal/horses/create?sex=female', 'horses'], 'scoped' => true,
            ],
            'stallions' => [
                'table' => 'horses', 'label' => $horseLabel, 'search' => ['t.name_en', 't.name_ar', 't.registration_no'],
                'where' => "t.sex = 'male'", 'grantedBy' => ['horses', 'embryos', 'horse_breeding'],
                'create' => ['/portal/horses/create?sex=male', 'horses'], 'scoped' => true,
            ],
            'employees' => [
                'table' => 'employees', 'label' => "CONCAT(t.name_en, IF(t.name_ar IS NULL OR t.name_ar = '', '', CONCAT(' / ', t.name_ar)))",
                'search' => ['t.name_en', 't.name_ar', 't.emp_no', 't.phone'], 'grantedBy' => ['hr', 'payroll', 'finance', 'horses', 'users', 'horse_training', 'horse_health'],
                'create' => ['/portal/employees/create', 'hr'],
            ],
            'clients' => [
                'table' => 'parties', 'label' => 't.name_en', 'search' => ['t.name_en', 't.name_ar', 't.phone', 't.email'],
                'where' => "t.type IN ('client','both')", 'grantedBy' => ['finance', 'horses', 'embryos', 'inbox'],
                'create' => ['/portal/parties/create?type=client', 'finance'],
            ],
            'suppliers' => [
                'table' => 'parties', 'label' => 't.name_en', 'search' => ['t.name_en', 't.name_ar', 't.phone', 't.email'],
                'where' => "t.type IN ('supplier','both')", 'grantedBy' => ['finance', 'inventory'],
                'create' => ['/portal/parties/create?type=supplier', 'finance'],
            ],
            'parties' => [
                'table' => 'parties', 'label' => "CONCAT(t.name_en, ' (', t.type, ')')", 'search' => ['t.name_en', 't.name_ar', 't.phone', 't.email'],
                'grantedBy' => ['finance'], 'create' => ['/portal/parties/create', 'finance'],
            ],
            'items' => [
                'table' => 'inventory_items', 'label' => "CONCAT(t.name_en, ' — ', TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM t.quantity)), ' ', t.unit)",
                'search' => ['t.name_en', 't.name_ar', 't.sku'], 'grantedBy' => ['inventory', 'finance', 'horse_health', 'horse_diet'],
                'filters' => ['inventory_id' => 't.inventory_id'],
                'joinFilters' => ['category' => 'EXISTS (SELECT 1 FROM inventories i WHERE i.id = t.inventory_id AND i.category = :f_category)'],
                'create' => ['/portal/items/create', 'inventory'],
            ],
            'embryos' => [
                'table' => 'embryos', 'label' => "CONCAT(t.code, IF(t.name IS NULL OR t.name = '', '', CONCAT(' — ', t.name)))",
                'search' => ['t.code', 't.name'], 'grantedBy' => ['embryos', 'finance'], 'create' => ['/portal/embryos/create', 'embryos'],
            ],
            'shows' => [
                'table' => 'shows', 'label' => "CONCAT(t.name_en, ' (', DATE_FORMAT(t.start_date, '%Y-%m-%d'), ')')",
                'search' => ['t.name_en', 't.name_ar', 't.city'], 'grantedBy' => ['horse_training', 'horses', 'cms'],
                'create' => ['/portal/shows/create', 'horse_training'], 'order' => 't.start_date DESC',
            ],
            'bills' => [
                'table' => 'bills', 'label' => "CONCAT(t.number, ' — ', COALESCE(t.description, ''), ' — ', t.amount_qar, ' QAR')",
                'search' => ['t.number', 't.description', 't.reference_no'], 'grantedBy' => ['finance'], 'order' => 't.id DESC',
            ],
            'users' => [
                'table' => 'users', 'label' => "CONCAT(t.name, ' (', t.username, ')')", 'search' => ['t.name', 't.username', 't.email'],
                'where' => "t.status = 'active'", 'grantedBy' => ['users', 'inbox'],
            ],
            'accounts' => [
                'table' => 'accounts', 'label' => 't.name', 'search' => ['t.name'], 'where' => 't.active = 1', 'grantedBy' => ['finance', 'payroll'],
                'create' => ['/portal/accounts/create', 'finance'],
            ],
            'categories' => [
                'table' => 'finance_categories', 'label' => "CONCAT(IF(t.parent_id IS NULL, '', '   › '), t.name_en)", 'search' => ['t.name_en', 't.name_ar'],
                'where' => 't.active = 1', 'grantedBy' => ['finance'], 'filters' => ['parent_id' => 't.parent_id'],
                'order' => 'COALESCE(t.parent_id, t.id), t.parent_id IS NOT NULL, t.sort, t.name_en', 'noDeleted' => true,
            ],
            'inventories' => [
                'table' => 'inventories', 'label' => 't.name_en', 'search' => ['t.name_en', 't.name_ar'], 'grantedBy' => ['inventory', 'finance'],
                'create' => ['/portal/inventories/create', 'inventory'],
            ],
            'payroll_lines' => [
                'table' => 'payroll_lines', 'label' => "CONCAT((SELECT pr.period FROM payroll_runs pr WHERE pr.id = t.payroll_run_id), ' — ', (SELECT e.name_en FROM employees e WHERE e.id = t.employee_id))",
                'search' => ['t.id'], 'grantedBy' => ['payroll'], 'noDeleted' => true, 'order' => 't.id DESC',
            ],
            'invoices' => [
                'table' => 'invoices', 'label' => "CONCAT(t.number, ' — ', t.total_qar, ' QAR')", 'search' => ['t.number'], 'grantedBy' => ['finance'], 'order' => 't.id DESC',
            ],
            'purchase_orders' => [
                'table' => 'purchase_orders', 'label' => "CONCAT(t.number, ' — ', t.total_qar, ' QAR')", 'search' => ['t.number'], 'grantedBy' => ['finance', 'inventory'], 'order' => 't.id DESC',
            ],
            'leave_requests' => [
                'table' => 'leave_requests', 'label' => "CONCAT((SELECT e.name_en FROM employees e WHERE e.id = t.employee_id), ' — ', t.start_date, ' → ', t.end_date)",
                'search' => ['t.id'], 'where' => "t.status = 'approved'", 'grantedBy' => ['hr'], 'order' => 't.id DESC',
            ],
            'bill_payments' => [
                'table' => 'bill_payments', 'label' => "CONCAT((SELECT b.number FROM bills b WHERE b.id = t.bill_id), ' — ', t.amount_qar, ' QAR — ', t.payment_date)",
                'search' => ['t.reference_no'], 'grantedBy' => ['finance'], 'order' => 't.id DESC',
            ],
            'ownership' => [
                'table' => 'ownership_history', 'label' => "CONCAT((SELECT h.name_en FROM horses h WHERE h.id = t.horse_id), ' — ', t.event_type, ' ', t.event_date)",
                'search' => ['t.id'], 'where' => "t.event_type IN ('sale','transfer') AND t.status = 'completed'", 'grantedBy' => ['horses'], 'noDeleted' => true, 'order' => 't.id DESC',
            ],
        ];
    }

    public static function allowed(string $source): bool
    {
        $s = self::sources()[$source] ?? null;
        return $s !== null && Auth::canAny($s['grantedBy']);
    }

    public static function search(string $source, string $q, array $filters = [], int $limit = 20): array
    {
        $s = self::sources()[$source];
        [$where, $params] = self::where($s, $filters);
        if ($q !== '') {
            $or = [];
            foreach ($s['search'] as $i => $col) {
                $or[] = "$col LIKE :q$i";
                $params["q$i"] = '%' . $q . '%';
            }
            $where[] = '(' . implode(' OR ', $or) . ')';
        }
        $sql = "SELECT t.id, {$s['label']} AS label FROM `{$s['table']}` t WHERE " . implode(' AND ', $where)
             . ' ORDER BY ' . ($s['order'] ?? 'label') . ' LIMIT ' . max(1, min(50, $limit));
        return DB::all($sql, $params);
    }

    public static function label(string $source, mixed $id): ?string
    {
        if ($id === null || $id === '' || !isset(self::sources()[$source])) {
            return null;
        }
        $s = self::sources()[$source];
        return DB::value("SELECT {$s['label']} FROM `{$s['table']}` t WHERE t.id = ?", [(int) $id]);
    }

    /** Validates that a submitted id belongs to the source (and to the user's horse scope). */
    public static function valid(string $source, mixed $id, array $filters = []): bool
    {
        $s = self::sources()[$source] ?? null;
        if (!$s) {
            return false;
        }
        [$where, $params] = self::where($s, $filters);
        $where[] = 't.id = :id';
        $params['id'] = (int) $id;
        return (bool) DB::value("SELECT 1 FROM `{$s['table']}` t WHERE " . implode(' AND ', $where), $params);
    }

    private static function where(array $s, array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        if (empty($s['noDeleted'])) {
            $where[] = 't.deleted_at IS NULL';
        }
        if (!empty($s['where'])) {
            $where[] = $s['where'];
        }
        foreach ($s['filters'] ?? [] as $key => $col) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $where[] = "$col = :f_$key";
                $params["f_$key"] = $filters[$key];
            }
        }
        foreach ($s['joinFilters'] ?? [] as $key => $sql) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $where[] = $sql;
                $params["f_$key"] = $filters[$key];
            }
        }
        if (!empty($s['scoped']) && Auth::horseScopeAssigned()) {
            $ids = Auth::assignedHorseIds();
            $where[] = 't.id IN ' . DB::in($ids ?: [0], 'sc', $params);
        }
        return [$where, $params];
    }
}
