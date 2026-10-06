<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\Crypto;
use App\Core\DB;
use App\Core\ValidationException;
use App\Services\Money;
use App\Services\Pickers;

/**
 * Describes one kind of record (fields, list columns, filters, links, permissions, hooks).
 * CrudController turns a Resource into list / create / show / edit / delete / export pages.
 *
 * Field definition keys:
 *   type      text|textarea|email|phone|url|int|decimal|money|date|time|select|lookup|picker|checkbox|encrypted|currency
 *   label     translation key            required  bool          max  max length
 *   options   [value => label key] or callable (select)          lookup  lookup type (lookups table)
 *   source    picker source (Pickers)    filter   picker filter  sensitive  hidden without "view sensitive"
 *   section   form section key           default  default value  readonly  never written from the form
 *   create_only / edit_only, help, col (grid width 3|4|6|12), no_phone (reject phone numbers)
 */
abstract class Resource
{
    public string $key;              // URL segment: /portal/{key}
    public string $table;
    public string $module;           // permission module
    public string $recordType;       // used by files, documents, timeline and activity log
    public string $title;            // translation key (plural)
    public string $singular;         // translation key
    public array $search = [];
    public string $sort = 't.id DESC';
    public array $sortable = [];
    public ?string $parentField = null;    // e.g. horse_id for records shown inside a horse profile
    public ?string $parentResource = null; // e.g. horses
    public bool $archivable = false;
    public bool $financial = false;  // deleting needs Owner/GM approval
    public bool $hasCreatedBy = true;
    public ?string $showView = null;
    public ?string $formView = null;
    public int $perPage = 25;

    abstract public function fields(): array;

    /** List columns: key => [label, sql, fmt, sort, sensitive]. */
    public function columns(): array
    {
        return [];
    }

    public function filters(): array
    {
        return [];
    }

    public function from(): string
    {
        return '`' . $this->table . '` t';
    }

    /** Record-level restrictions added to every query (list, show, export). */
    public function scope(array &$where, array &$params): void
    {
    }

    public function label(array $row): string
    {
        return (string) ($row['name_en'] ?? $row['number'] ?? $row['code'] ?? $row['title'] ?? ('#' . $row['id']));
    }

    /** Tables that reference this record: [table, column, label key]. Deleting is blocked while links exist. */
    public function links(): array
    {
        return [];
    }

    /** Derives values and validates across fields. Throw ValidationException for errors. */
    public function prepare(array $data, ?array $old): array
    {
        return $data;
    }

    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
    }

    public function afterDelete(array $row): void
    {
    }

    public function tabs(array $row): array
    {
        return [];
    }

    public function actions(array $row): array
    {
        return [];
    }

    /** Extra buttons on the list page: [label key, url, css class]. */
    public function listActions(): array
    {
        return [];
    }

    public function rowClass(array $row): string
    {
        return '';
    }

    /** Totals row for money columns: [column key => amount]. */
    public function listTotals(ListQuery $q): array
    {
        return [];
    }

    /** Form sections: key => label key. Fields without a section go to "main". */
    public function sections(): array
    {
        return ['main' => null];
    }

    public function headerBadges(array $row): string
    {
        return !empty($row['archived_at']) ? '<span class="badge badge-archived">' . e(__('common.archived')) . '</span>' : '';
    }

    /** HTML shown above the tabs (e.g. horse photo header). */
    public function beforeTabs(array $row): string
    {
        return '';
    }

    public function afterDetails(array $row): string
    {
        return '';
    }

    /** Standard tabs every main record gets. */
    protected function standardTabs(array $row, array $documents = []): array
    {
        $tabs = [
            'timeline'  => ['label' => 'common.timeline', 'type' => 'timeline'],
            'documents' => ['label' => 'common.documents', 'type' => 'documents'] + $documents,
        ];
        if (Auth::isOwner() || Auth::roleSlug() === 'general_manager') {
            $tabs['history'] = ['label' => 'common.history', 'type' => 'history'];
        }
        return $tabs;
    }

    public function canList(): bool
    {
        return Auth::can($this->module, 'view');
    }

    public function canView(array $row): bool
    {
        return Auth::can($this->module, 'view');
    }

    public function canCreate(): bool
    {
        return Auth::can($this->module, 'create');
    }

    public function canEdit(array $row): bool
    {
        if ($this->hasCreatedBy && array_key_exists('created_by', $row)) {
            return Auth::canEditRecord($this->module, $row);
        }
        return Auth::can($this->module, 'edit');
    }

    public function canDelete(array $row): bool
    {
        return Auth::can($this->module, 'delete');
    }

    public function canSensitive(): bool
    {
        return Auth::can($this->module, 'sensitive');
    }

    public function url(?int $id = null, string $suffix = ''): string
    {
        return '/portal/' . $this->key . ($id ? '/' . $id : '') . $suffix;
    }

    /** Where to go after saving (sub-records go back to their parent's tab). */
    public function redirectAfterSave(int $id, array $data): string
    {
        if ($this->parentField && !empty($data[$this->parentField]) && $this->parentResource) {
            return '/portal/' . $this->parentResource . '/' . $data[$this->parentField] . '?tab=' . $this->key;
        }
        return $this->url($id);
    }

    // ------------------------------------------------------------------ data access

    public function baseWhere(array &$params): array
    {
        $where = ['t.deleted_at IS NULL'];
        $this->scope($where, $params);
        return $where;
    }

    public function find(int $id, bool $withDeleted = false): ?array
    {
        $params = ['id' => $id];
        $where = $withDeleted ? ['1=1'] : $this->baseWhere($params);
        $where[] = 't.id = :id';
        return DB::row('SELECT t.* FROM ' . $this->from() . ' WHERE ' . implode(' AND ', $where), $params);
    }

    public function visibleFields(bool $forCreate = true): array
    {
        $out = [];
        foreach ($this->fields() as $name => $f) {
            if (!empty($f['sensitive']) && !$this->canSensitive()) {
                continue;
            }
            if ($forCreate && !empty($f['edit_only'])) {
                continue;
            }
            if (!$forCreate && !empty($f['create_only'])) {
                continue;
            }
            $out[$name] = $f;
        }
        return $out;
    }

    public function visibleColumns(): array
    {
        return array_filter($this->columns(), fn ($c) => empty($c['sensitive']) || $this->canSensitive());
    }

    /** Reads and validates the submitted form. Returns the data to store. */
    public function collect(array $input, ?array $old): array
    {
        $data = [];
        $errors = [];
        foreach ($this->visibleFields($old === null) as $name => $f) {
            if (!empty($f['readonly']) || ($f['type'] ?? '') === 'display') {
                continue;
            }
            $type = $f['type'] ?? 'text';
            $raw = $input[$name] ?? null;
            if (is_string($raw)) {
                $raw = trim($raw);
            }
            if ($type === 'checkbox') {
                $data[$name] = !empty($raw) ? 1 : 0;
                continue;
            }
            if ($raw === '' || $raw === null) {
                if (!empty($f['required'])) {
                    $errors[$name] = __('validation.required');
                }
                $data[$name] = array_key_exists('empty', $f) ? $f['empty'] : null;
                continue;
            }
            try {
                $data[$name] = $this->normalize($name, $f, $raw);
            } catch (ValidationException $e) {
                $errors += $e->errors;
            }
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        return $data;
    }

    protected function normalize(string $name, array $f, mixed $raw): mixed
    {
        $type = $f['type'] ?? 'text';
        $fail = fn (string $key, array $p = []) => throw ValidationException::one($name, $key, $p);
        if (is_array($raw)) {
            $fail('validation.invalid');
        }
        $max = $f['max'] ?? match ($type) {
            'textarea' => 5000, 'encrypted' => 60, default => 190,
        };
        switch ($type) {
            case 'email':
                if (!filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                    $fail('validation.email');
                }
                return mb_strtolower($raw);
            case 'phone':
                $p = preg_replace('/[\s\-()]/', '', strtr($raw, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));
                if (!preg_match('/^\+?\d{6,15}$/', $p)) {
                    $fail('validation.phone');
                }
                return $p;
            case 'url':
                if (!filter_var($raw, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $raw)) {
                    $fail('validation.url');
                }
                return $raw;
            case 'int':
                if (!preg_match('/^-?\d+$/', $raw)) {
                    $fail('validation.number');
                }
                $v = (int) $raw;
                if (isset($f['min']) && $v < $f['min']) {
                    $fail('validation.min', ['min' => $f['min']]);
                }
                return $v;
            case 'decimal':
            case 'money':
                $v = Money::parse($raw);
                if ($v === null) {
                    $fail('validation.number');
                }
                if (isset($f['min']) && $v < $f['min']) {
                    $fail('validation.min', ['min' => $f['min']]);
                }
                return $type === 'money' ? round($v, 2) : $v;
            case 'date':
                $d = \DateTime::createFromFormat('Y-m-d', $raw);
                if (!$d || $d->format('Y-m-d') !== $raw) {
                    $fail('validation.date');
                }
                return $raw;
            case 'time':
                if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $raw)) {
                    $fail('validation.time');
                }
                return $raw;
            case 'select':
                $opts = is_callable($f['options']) ? ($f['options'])() : $f['options'];
                if (!array_key_exists($raw, $opts)) {
                    $fail('validation.invalid');
                }
                return $raw;
            case 'currency':
                if (!DB::value('SELECT 1 FROM currencies WHERE code = ? AND active = 1', [$raw])) {
                    $fail('validation.invalid');
                }
                return $raw;
            case 'lookup':
                if (!DB::value('SELECT 1 FROM lookups WHERE id = ? AND type = ?', [(int) $raw, $f['lookup']])) {
                    $fail('validation.invalid');
                }
                return (int) $raw;
            case 'picker':
                if (!Pickers::valid($f['source'], $raw, $f['filter'] ?? [])) {
                    $fail('validation.pick_from_list');
                }
                return (int) $raw;
            case 'encrypted':
                if (mb_strlen($raw) > $max) {
                    $fail('validation.max', ['max' => $max]);
                }
                if (!empty($f['pattern']) && !preg_match($f['pattern'], $raw)) {
                    $fail($f['pattern_msg'] ?? 'validation.invalid');
                }
                return Crypto::encrypt($raw);
            default: // text, textarea
                if (mb_strlen($raw) > $max) {
                    $fail('validation.max', ['max' => $max]);
                }
                if (!empty($f['no_phone']) && preg_match('/^[\s+()\-\d]{7,}$/', $raw)) {
                    $fail('validation.no_phone');
                }
                if (!empty($f['pattern']) && !preg_match($f['pattern'], $raw)) {
                    $fail($f['pattern_msg'] ?? 'validation.invalid');
                }
                return $raw;
        }
    }

    /** Value prepared for a form input (decrypts sensitive values for permitted users). */
    public function formValue(string $name, array $f, ?array $row): mixed
    {
        $old = old($name);
        if ($old !== null) {
            return $old;
        }
        if ($row === null) {
            $q = $_GET[$name] ?? null; // prefill from query string, e.g. ?horse_id=5
            if ($q !== null && is_string($q)) {
                return $q;
            }
            return is_callable($f['default'] ?? null) ? ($f['default'])() : ($f['default'] ?? null);
        }
        $v = $row[$name] ?? null;
        return ($f['type'] ?? '') === 'encrypted' ? Crypto::decrypt($v) : $v;
    }

    /** Human readable value for show pages. */
    public function display(string $name, array $f, array $row): string
    {
        $v = $row[$name] ?? null;
        $type = $f['type'] ?? 'text';
        if ($v === null || $v === '') {
            return '<span class="muted">—</span>';
        }
        return match ($type) {
            'checkbox' => $v ? '✓ ' . e(__('common.yes')) : e(__('common.no')),
            'money'    => money($v),
            'decimal'  => e(rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.')),
            'date'     => '<span class="' . e(!empty($f['expiry']) ? expiry_class($v) : '') . '">' . e(fmt_date($v)) . '</span>',
            'select'   => e(__((is_callable($f['options']) ? ($f['options'])() : $f['options'])[$v] ?? (string) $v)),
            'lookup'   => e(loc(DB::row('SELECT value_en, value_ar FROM lookups WHERE id = ?', [$v]), 'value')),
            'picker'   => $this->pickerLink($f['source'], (int) $v),
            'encrypted'=> e((string) Crypto::decrypt($v)),
            'email'    => '<a href="mailto:' . e($v) . '">' . e($v) . '</a>',
            'phone'    => '<a href="tel:' . e($v) . '" dir="ltr">' . e($v) . '</a>',
            'url'      => '<a href="' . e($v) . '" target="_blank" rel="noopener">' . e($v) . '</a>',
            'textarea' => nl2br(e($v)),
            default    => e($v),
        };
    }

    protected function pickerLink(string $source, int $id): string
    {
        $label = Pickers::label($source, $id) ?? ('#' . $id);
        $map = ['horses' => 'horses', 'mares' => 'horses', 'stallions' => 'horses', 'employees' => 'employees', 'clients' => 'parties', 'suppliers' => 'parties', 'parties' => 'parties', 'items' => 'items', 'embryos' => 'embryos', 'shows' => 'shows', 'bills' => 'bills', 'accounts' => 'accounts', 'inventories' => 'inventories', 'invoices' => 'invoices', 'purchase_orders' => 'purchase-orders'];
        $res = $map[$source] ?? null;
        if ($res && ($r = Registry::get($res)) && $r->canList()) {
            return '<a href="' . e(url('/portal/' . $res . '/' . $id)) . '">' . e($label) . '</a>';
        }
        return e($label);
    }

    /** Linked records preventing deletion: [label => count]. */
    public function linkedCounts(int $id): array
    {
        $out = [];
        foreach ($this->links() as [$table, $col, $labelKey]) {
            DB::assertIdent($table);
            DB::assertIdent($col);
            $hasDeleted = DB::hasColumn($table, 'deleted_at') ? ' AND deleted_at IS NULL' : '';
            $n = (int) DB::value("SELECT COUNT(*) FROM `$table` WHERE `$col` = ?" . $hasDeleted, [$id]);
            if ($n > 0) {
                $out[__($labelKey)] = ($out[__($labelKey)] ?? 0) + $n;
            }
        }
        return $out;
    }

    public static function opts(array $values, string $prefix): array
    {
        $o = [];
        foreach ($values as $v) {
            $o[$v] = $prefix . '.' . $v;
        }
        return $o;
    }
}
