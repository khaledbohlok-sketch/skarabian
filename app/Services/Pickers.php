<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Lang;

/**
 * Searchable dropdown sources. Every selection of a horse, employee, supplier, client, item... uses these:
 * no free-text names. Only id + display label are returned, and only to users allowed to see the source.
 */
final class Pickers
{
    public static function sources(): array
    {
        $horseLabel = "CONCAT(t.name_en, IF(t.name_ar IS NULL OR t.name_ar = '', '', CONCAT(' / ', t.name_ar)), IF(t.is_external = 1, ' (ext.)', ''))";
        $commonGroupLabel = Lang::isRtl() ? 'الأكثر استخداماً' : 'Common';
        // Name in the user's language: the Arabic name when there is one, else the English name
        $nm = fn (string $t = 't') => Lang::isRtl() ? "COALESCE(NULLIF($t.name_ar, ''), $t.name_en)" : "$t.name_en";
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
                'where' => "t.sex = 'female'", 'grantedBy' => ['horses', 'embryos', 'horse_breeding', 'semen_stock'],
                'create' => ['/portal/horses/create?sex=female', 'horses'], 'scoped' => true,
            ],
            'stallions' => [
                'table' => 'horses', 'label' => $horseLabel, 'search' => ['t.name_en', 't.name_ar', 't.registration_no'],
                'where' => "t.sex = 'male'", 'grantedBy' => ['horses', 'embryos', 'horse_breeding', 'semen_stock'],
                'create' => ['/portal/horses/create?sex=male', 'horses'], 'scoped' => true,
            ],
            'tanks' => [
                'table' => 'tanks', 'label' => 't.name', 'search' => ['t.name', 't.location'],
                'grantedBy' => ['semen_stock'], 'create' => ['/portal/tanks/create', 'semen_stock'],
            ],
            'canisters' => [
                'table' => 'canisters', 'label' => "CONCAT((SELECT tk.name FROM tanks tk WHERE tk.id = t.tank_id), ' › ', t.name)", 'search' => ['t.name'],
                'grantedBy' => ['semen_stock'], 'filters' => ['tank_id' => 't.tank_id'], 'create' => ['/portal/canisters/create', 'semen_stock'],
            ],
            'employees' => [
                'table' => 'employees', 'label' => "CONCAT(t.name_en, IF(t.name_ar IS NULL OR t.name_ar = '', '', CONCAT(' / ', t.name_ar)))",
                'search' => ['t.name_en', 't.name_ar', 't.emp_no', 't.phone'], 'grantedBy' => ['hr', 'payroll', 'finance', 'horses', 'users', 'horse_training', 'horse_health'],
                'create' => ['/portal/employees/create', 'hr'],
            ],
            'clients' => [
                'table' => 'parties', 'label' => $nm(), 'search' => ['t.name_en', 't.name_ar', 't.phone', 't.email'],
                'where' => "t.type IN ('client','both')", 'grantedBy' => ['finance', 'horses', 'embryos', 'inbox'],
                'create' => ['/portal/parties/create?type=client', 'finance'],
            ],
            'suppliers' => [
                'table' => 'parties', 'label' => $nm(), 'search' => ['t.name_en', 't.name_ar', 't.phone', 't.email'],
                'where' => "t.type IN ('supplier','both')", 'grantedBy' => ['finance', 'inventory'],
                'create' => ['/portal/parties/create?type=supplier', 'finance'],
            ],
            'parties' => [
                'table' => 'parties', 'label' => $nm(), 'search' => ['t.name_en', 't.name_ar', 't.phone', 't.email'],
                'grantedBy' => ['finance'], 'create' => ['/portal/parties/create', 'finance'],
            ],
            'items' => [
                'table' => 'inventory_items', 'label' => "CONCAT(" . $nm() . ", ' — ', TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM t.quantity)), ' ', t.unit)",
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
                'table' => 'shows', 'label' => "CONCAT(" . $nm() . ", ' (', " . (DB::hasColumn('shows', 'year_only') ? "IF(t.year_only = 1, DATE_FORMAT(t.start_date, '%Y'), DATE_FORMAT(t.start_date, '%Y-%m-%d'))" : "DATE_FORMAT(t.start_date, '%Y-%m-%d')") . ", ')')",
                'search' => ['t.name_en', 't.name_ar', 't.city'], 'grantedBy' => ['horse_training', 'horses', 'cms'],
                'create' => ['/portal/shows/create', 'horse_training'], 'order' => 't.start_date DESC',
            ],
            'bills' => [
                'table' => 'bills', 'label' => "CONCAT(t.number, ' — ', COALESCE(t.description, ''), ' — ', t.amount_qar, ' QAR')",
                'search' => ['t.number', 't.description', 't.reference_no'], 'grantedBy' => ['finance'], 'order' => 't.id DESC',
            ],
            'open_bills' => [
                'table' => 'bills', 'label' => "CONCAT(t.number, ' — ', COALESCE(t.description, ''), ' — ', FORMAT(t.amount_qar - t.paid_qar, 2), ' QAR')",
                'search' => ['t.number', 't.description', 't.reference_no'], 'where' => "t.status IN ('approved','partially_paid','overdue') AND t.payroll_run_id IS NULL", 'grantedBy' => ['finance'], 'order' => 't.bill_date DESC, t.id DESC', // (salaries: Payroll)
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
                'table' => 'finance_categories', 'label' => "CONCAT(IF(t.parent_id IS NULL, '', '   › '), " . $nm() . ")", 'search' => ['t.name_en', 't.name_ar'],
                'where' => 't.active = 1', 'grantedBy' => ['finance'], 'filters' => ['parent_id' => 't.parent_id'],
                'order' => 'COALESCE(t.parent_id, t.id), t.parent_id IS NOT NULL, t.sort, t.name_en',
            ],
            'top_categories' => [
                'table' => 'finance_categories', 'label' => $nm(), 'search' => ['t.name_en', 't.name_ar'],
                'where' => 't.active = 1 AND t.parent_id IS NULL', 'grantedBy' => ['finance'], 'order' => 't.sort, t.name_en',
                'joinFilters' => ['type' => "t.type IN (:f_type, 'any')"],
            ],
            'subcategories' => [
                'table' => 'finance_categories', 'label' => "CONCAT((SELECT " . $nm('p') . " FROM finance_categories p WHERE p.id = t.parent_id), ' › ', " . $nm() . ")",
                'search' => ['t.name_en', 't.name_ar'], 'where' => 't.active = 1 AND t.parent_id IS NOT NULL', 'grantedBy' => ['finance'],
                'filters' => ['parent_id' => 't.parent_id'], 'order' => 't.sort, t.name_en',
            ],
            'inventories' => [
                'table' => 'inventories', 'label' => $nm(), 'search' => ['t.name_en', 't.name_ar'], 'grantedBy' => ['inventory', 'finance'],
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
            // Nationality (adjective: "Qatari"/"قطري") and country (noun: "Qatar"/"قطر") both read from the
            // same `countries` table — the shared reference list for every nationality/country field in
            // the system. 'common' pins the most-used entries to the top; 'noDeleted' because the table
            // has no deleted_at (it's a fixed reference list, not a user-editable record type).
            'nationalities' => [
                'table' => 'countries', 'noDeleted' => true,
                'label' => Lang::isRtl() ? "CONCAT(t.flag, ' ', t.nationality_ar)" : "CONCAT(t.flag, ' ', t.nationality_en)",
                'search' => ['t.nationality_en', 't.nationality_ar', 't.name_en', 't.name_ar', 't.id'],
                'group' => "IF(t.common = 1, '" . $commonGroupLabel . "', '')",
                'grantedBy' => ['hr', 'finance', 'horses', 'users'],
                'order' => 't.common DESC, t.sort, ' . (Lang::isRtl() ? 't.nationality_ar' : 't.nationality_en'),
            ],
            'countries' => [
                'table' => 'countries', 'noDeleted' => true,
                'label' => Lang::isRtl() ? "CONCAT(t.flag, ' ', t.name_ar)" : "CONCAT(t.flag, ' ', t.name_en)",
                'search' => ['t.name_en', 't.name_ar', 't.nationality_en', 't.nationality_ar', 't.id'],
                'group' => "IF(t.common = 1, '" . $commonGroupLabel . "', '')",
                'grantedBy' => ['hr', 'finance', 'horses', 'users'],
                'order' => 't.common DESC, t.sort, ' . (Lang::isRtl() ? 't.name_ar' : 't.name_en'),
            ],
        ];
    }

    public static function allowed(string $source): bool
    {
        $s = self::sources()[$source] ?? null;
        return $s !== null && Auth::canAny($s['grantedBy']);
    }

    /** Letter variants Arabic speakers treat as equivalent when typing a search — applied to both the
     *  stored column and the typed query so e.g. "احمد" finds "أحمد". */
    private const AR_VARIANTS = ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي'];

    private static function normalizeArSql(string $col): string
    {
        foreach (self::AR_VARIANTS as $from => $to) {
            $col = "REPLACE($col, '$from', '$to')";
        }
        return $col;
    }

    public static function search(string $source, string $q, array $filters = [], int $limit = 20): array
    {
        $s = self::sources()[$source];
        if ($s['table'] === 'parties' && !Auth::can('finance', 'sensitive')) {
            // a client's phone / email is sensitive: matching on it would reveal it
            $s['search'] = array_values(array_diff($s['search'], ['t.phone', 't.email']));
        }
        [$where, $params] = self::where($s, $filters);
        if ($q !== '') {
            $qNorm = strtr($q, self::AR_VARIANTS);
            $or = [];
            foreach ($s['search'] as $i => $col) {
                $or[] = self::normalizeArSql($col) . " LIKE :q$i";
                $params["q$i"] = '%' . $qNorm . '%';
            }
            $where[] = '(' . implode(' OR ', $or) . ')';
        }
        $groupCol = !empty($s['group']) ? ", {$s['group']} AS grp" : '';
        $sql = "SELECT t.id, {$s['label']} AS label{$groupCol} FROM `{$s['table']}` t WHERE " . implode(' AND ', $where)
             . ' ORDER BY ' . ($s['order'] ?? 'label') . ' LIMIT ' . max(1, min(50, $limit));
        return DB::all($sql, $params);
    }

    public static function label(string $source, mixed $id): ?string
    {
        if ($id === null || $id === '' || !isset(self::sources()[$source])) {
            return null;
        }
        $s = self::sources()[$source];
        // Not (int) $id: most sources key off an auto-increment int, but 'countries'/'nationalities' key
        // off a 2-letter ISO code (e.g. "QA") — casting that to int would silently turn it into 0. MySQL
        // compares a string parameter against either column type correctly, so a plain string is safe here.
        return DB::value("SELECT {$s['label']} FROM `{$s['table']}` t WHERE t.id = ?", [(string) $id]);
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
        $params['id'] = (string) $id;
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
