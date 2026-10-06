<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\DB;

/**
 * Server-side list query: search, filters, sort and pagination. Never loads all rows into the browser.
 * An empty filter always means "show all" (the old Inventory pages returned nothing without a filter).
 */
final class ListQuery
{
    public array $where = [];
    public array $params = [];
    public string $order;
    public int $page;
    public int $perPage;
    public array $activeFilters = [];

    public function __construct(public Resource $res, public array $input)
    {
        $this->where = $res->baseWhere($this->params);
        $this->page = max(1, (int) ($input['page'] ?? 1));
        $this->perPage = in_array((int) ($input['per'] ?? 0), [25, 50, 100], true) ? (int) $input['per'] : $res->perPage;
        $this->applySearch();
        $this->applyFilters();
        $this->order = $this->resolveSort();
    }

    private function applySearch(): void
    {
        $q = trim((string) ($this->input['q'] ?? ''));
        if ($q === '' || !$this->res->search) {
            return;
        }
        $or = [];
        foreach ($this->res->search as $i => $col) {
            $or[] = "$col LIKE :s$i";
            $this->params["s$i"] = '%' . $q . '%';
        }
        $this->where[] = '(' . implode(' OR ', $or) . ')';
        $this->activeFilters['q'] = $q;
    }

    private function applyFilters(): void
    {
        $archivedShown = false;
        foreach ($this->res->filters() as $key => $f) {
            $v = $this->input[$key] ?? ($f['default'] ?? '');
            if (is_array($v) || $v === '' || $v === null) {
                continue;
            }
            $this->activeFilters[$key] = $v;
            $p = 'f_' . $key;
            switch ($f['type']) {
                case 'date_from':
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                        $this->where[] = "{$f['sql']} >= :$p";
                        $this->params[$p] = $v;
                    }
                    break;
                case 'date_to':
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                        $this->where[] = "{$f['sql']} <= :$p";
                        $this->params[$p] = $v;
                    }
                    break;
                case 'bool':
                    $this->where[] = "{$f['sql']} = :$p";
                    $this->params[$p] = $v === '1' ? 1 : 0;
                    break;
                case 'raw': // custom SQL with a :value placeholder chosen from fixed options
                    $opts = $f['options'];
                    if (isset($f['sqlFor'][$v])) {
                        $this->where[] = $f['sqlFor'][$v];
                    } elseif (array_key_exists($v, $opts) && isset($f['sql'])) {
                        $this->where[] = str_replace(':value', ":$p", $f['sql']);
                        $this->params[$p] = $v;
                    }
                    break;
                case 'archived':
                    $archivedShown = $v === '1';
                    break;
                default: // select, lookup, picker
                    $this->where[] = "{$f['sql']} = :$p";
                    $this->params[$p] = $v;
            }
        }
        if ($this->res->archivable && !$archivedShown) {
            $this->where[] = 't.archived_at IS NULL';
        }
    }

    private function resolveSort(): string
    {
        $sort = (string) ($this->input['sort'] ?? '');
        $dir = strtolower((string) ($this->input['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $cols = $this->res->visibleColumns();
        if ($sort !== '' && isset($cols[$sort]) && !empty($cols[$sort]['sort'])) {
            return $cols[$sort]['sql'] . ' ' . $dir . ', t.id DESC';
        }
        return $this->res->sort;
    }

    public function selectSql(): string
    {
        $parts = ['t.id'];
        foreach ($this->res->visibleColumns() as $key => $c) {
            $parts[] = $c['sql'] . ' AS `' . $key . '`';
        }
        if ($this->res->hasCreatedBy) {
            $parts[] = 't.created_by AS _created_by';
            $parts[] = 't.created_at AS _created_at';
        }
        return implode(', ', $parts);
    }

    public function count(): int
    {
        return (int) DB::value('SELECT COUNT(*) FROM ' . $this->res->from() . ' WHERE ' . implode(' AND ', $this->where), $this->params);
    }

    public function rows(?int $limit = null, ?int $offset = null): array
    {
        $limit ??= $this->perPage;
        $offset ??= ($this->page - 1) * $this->perPage;
        $sql = 'SELECT ' . $this->selectSql() . ' FROM ' . $this->res->from() . ' WHERE ' . implode(' AND ', $this->where)
             . ' ORDER BY ' . $this->order . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        return DB::all($sql, $this->params);
    }

    /** Sum of a SQL expression over the filtered set (e.g. totals row). */
    public function sum(string $expr): float
    {
        return (float) DB::value('SELECT COALESCE(SUM(' . $expr . '), 0) FROM ' . $this->res->from() . ' WHERE ' . implode(' AND ', $this->where), $this->params);
    }
}
