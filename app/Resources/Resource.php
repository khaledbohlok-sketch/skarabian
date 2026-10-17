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
 *   file      upload input (not stored in the row; the resource stores it in afterSave), accept = allowed types
 *   virtual   validated like any field but not stored (read it from $this->extra in prepare/afterSave)
 *   depends   picker filters taken from other fields of the form, e.g. ['parent_id' => 'category_id']
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
    public bool $softDelete = true;   // false for append-only tables without deleted_at (e.g. stock movements)
    public array $extra = [];         // submitted values of virtual fields
    public ?string $showView = null;
    public ?string $formView = null;
    public ?string $indexView = null;
    public int $perPage = 25;
    /** Opt-in row checkboxes + bulk-action bar on the list page (crud/index.php). False for every
     * resource except the ones that define bulkActions() — keeps the other 36 list pages unchanged. */
    public bool $bulkSelect = false;
    /** The message when a bulk action is used with nothing ticked ("Select at least one bill."). */
    public string $bulkNoneKey = 'common.select_some';

    /** HTML for the bulk-action bar shown above the list once rows are checked (only rendered when
     * bulkSelect is true). Owns its own markup/JS wiring — crud/index.php only provides the checkboxes
     * and the running "N selected" count (#bulk-count), which this HTML can read. */
    public function bulkBarHtml(): string
    {
        return '';
    }

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

    /** SQL for a joined record's name in the user's language: the Arabic name in Arabic when set, else English. */
    protected static function nameSql(string $alias): string
    {
        return \App\Core\Lang::current() === 'ar' ? "COALESCE(NULLIF($alias.name_ar, ''), $alias.name_en)" : "$alias.name_en";
    }

    /** SQL for a stored unit ("bag") in the user's language ("كيس" from the unit lookup list in Arabic). */
    protected static function unitSql(string $col): string
    {
        return \App\Core\Lang::current() === 'ar'
            ? "COALESCE((SELECT NULLIF(lk.value_ar, '') FROM lookups lk WHERE lk.type = 'unit' AND lk.value_en = $col LIMIT 1), $col, '')"
            : "COALESCE($col, '')";
    }

    /** The record's name on screen. label() stays English because the activity log and trash store it;
     * this one shows the Arabic name in Arabic when the record has one. */
    public function heading(array $row): string
    {
        $label = $this->label($row);
        if (\App\Core\Lang::current() === 'ar' && !empty($row['name_ar']) && $label === (string) ($row['name_en'] ?? '')) {
            return (string) $row['name_ar'];
        }
        return $label;
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

    /** Why the record can't come back from the Trash (null: it can). */
    public function restoreBlocker(array $row): ?string
    {
        return null;
    }

    /** After the record came back from the Trash (e.g. recalculating what depends on it). */
    public function afterRestore(array $row): void
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

    /** Runs before a list is shown (e.g. bills past their due date become overdue). */
    public function beforeList(): void
    {
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

    /** Form sections: key => label key, or key => ['label' => label key, 'collapsed' => true]. Fields without a section go to "main". */
    public function sections(): array
    {
        return ['main' => null];
    }

    /** Normalizes a sections() entry (plain label key, or ['label'=>, 'collapsed'=>]) to its label key. */
    public function sectionLabel(string $sec): ?string
    {
        $s = $this->sections()[$sec] ?? null;
        return is_array($s) ? ($s['label'] ?? null) : $s;
    }

    /** Whether a section should render collapsed (closed <details>) by default. */
    public function sectionCollapsed(string $sec): bool
    {
        $s = $this->sections()[$sec] ?? null;
        return is_array($s) && !empty($s['collapsed']);
    }

    /**
     * Lets a resource replace a whole section's generic fields with custom HTML (e.g. the horse pedigree
     * chart) — return null (the default) to render that section's fields normally. The custom HTML may be
     * its own independent <form> (forms can't nest): the shared form engine renders it after the main
     * form closes, still positioned in the right wizard step via a data-wizard-section wrapper.
     */
    public function customSection(string $sec, ?array $row): ?string
    {
        return null;
    }

    /**
     * Like customSection(), but the HTML is rendered INLINE inside the main <form> (same section wrapper,
     * same single POST) instead of as a sibling form — for widgets whose inputs must be part of the same
     * submission, e.g. the horse pedigree tree, which needs to exist on the create form before the record
     * has an id. Return null (the default) to render that section's fields normally.
     */
    public function sectionBody(string $sec, ?array $row): ?string
    {
        return null;
    }

    /** Extra HTML appended after a section's normal fields (or its sectionBody), still inside the same wrapper. */
    public function sectionExtra(string $sec, ?array $row): ?string
    {
        return null;
    }

    /** Extra page scripts the create/edit form needs beyond portal.js (e.g. a sectionBody()'s own JS). */
    public function formScripts(): array
    {
        return [];
    }

    /**
     * Optional step-wizard for long forms: ordered list of ['key' => step key, 'label' => i18n key, 'sections' => [section keys]].
     * Null (the default) means a plain single-page form. All fields still post in one request either way.
     */
    public function wizard(): ?array
    {
        return null;
    }

    /** Field keys shown in "quick add" mode (the inline popup create). Empty = quick mode shows every field. */
    public function quickFields(): array
    {
        return [];
    }

    public function headerBadges(array $row): string
    {
        return !empty($row['archived_at']) ? '<span class="badge badge-archived">' . e(__('common.archived')) . '</span>' : '';
    }

    /** Small icon/badge shown on a list row, next to its first (linked) cell — e.g. Bills' "included in a
     * financial report" marker. Empty by default. */
    public function rowBadge(array $row): string
    {
        return '';
    }

    /** Extra values a list row needs besides its columns, e.g. a flag for a control in a cell: [key => SQL], keys start with "_". */
    public function listExtraSelect(): array
    {
        return [];
    }

    /** HTML placed after a list cell's value, outside its link (e.g. the favourite star next to a horse's name). */
    public function afterCell(string $key, array $row): string
    {
        return '';
    }

    /** HTML before the record's name in the page head (e.g. the horse's photo). Empty by default. */
    public function headMedia(array $row): string
    {
        return '';
    }

    /** HTML under the record's name in the page head (e.g. the name in the other language). Empty by default. */
    public function headSub(array $row): string
    {
        return '';
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

    /**
     * When set, deleting this resource needs the user to type the row's value of this field (e.g.
     * 'username') to confirm, instead of a plain yes/no dialog. Null (the default) keeps the plain confirm.
     */
    public function deleteConfirmField(): ?string
    {
        return null;
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
        $where = $this->softDelete ? ['t.deleted_at IS NULL'] : ['1=1'];
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
        $this->extra = []; // the virtual fields of this form only (not of an earlier one read by the same object)
        $data = [];
        $errors = [];
        foreach ($this->visibleFields($old === null) as $name => $f) {
            if (!empty($f['readonly']) || in_array($f['type'] ?? '', ['display', 'file'], true)) {
                continue; // file inputs are handled by the resource's afterSave()
            }
            $type = $f['type'] ?? 'text';
            $raw = $input[$name] ?? null;
            // A required field entirely absent from the submission (e.g. a quick-add form that only
            // renders quickFields()) falls back to its own default instead of hard-failing — a required
            // field the user explicitly cleared on a full form still has no input and still errors below.
            if ($raw === null && !array_key_exists($name, $input) && !empty($f['required']) && array_key_exists('default', $f)) {
                $raw = is_callable($f['default']) ? ($f['default'])() : $f['default'];
                if (is_int($raw) || is_float($raw)) {
                    $raw = (string) $raw; // checked like a typed value (a number default would crash the text checks)
                }
            }
            if (is_string($raw)) {
                $raw = trim($raw);
            }
            $virtual = !empty($f['virtual']);
            if ($type === 'checkbox') {
                if ($virtual) {
                    $this->extra[$name] = !empty($raw);
                } else {
                    $data[$name] = !empty($raw) ? 1 : 0;
                }
                continue;
            }
            if ($raw === '' || $raw === null) {
                if (!empty($f['required'])) {
                    $errors[$name] = __('validation.required');
                }
                if ($virtual) {
                    $this->extra[$name] = null;
                } else {
                    $data[$name] = array_key_exists('empty', $f) ? $f['empty'] : DB::emptyValue($this->table, $name);
                }
                continue;
            }
            try {
                $v = $this->normalize($name, $f, $raw);
                if ($virtual) {
                    $this->extra[$name] = $v;
                } else {
                    $data[$name] = $v;
                }
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
            case 'segment':
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
            case 'pednode':
                $pf = $f['filter'] ?? [];
                foreach ($f['depends'] ?? [] as $param => $other) {
                    if (isset($_POST[$other]) && is_string($_POST[$other]) && $_POST[$other] !== '') {
                        $pf[$param] = $_POST[$other];
                    }
                }
                if (!Pickers::valid($f['source'], $raw, $pf)) {
                    $fail('validation.pick_from_list');
                }
                return (int) $raw;
            case 'country':
                // Keyed by its ISO 3166-1 alpha-2 code (e.g. "QA"), not an auto-increment int — unlike
                // 'picker', this must NOT cast to (int) or every code would collapse to 0.
                $src = !empty($f['as']) && $f['as'] === 'nationality' ? 'nationalities' : 'countries';
                if (!Pickers::valid($src, $raw)) {
                    $fail('validation.pick_from_list');
                }
                return strtoupper($raw);
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
        // a status the list shows as a badge (embryo, breeding, attendance, employee…) is a badge on the record's page too
        if (in_array($type, ['select', 'segment'], true) && ($prefix = $this->badgePrefix($name)) !== null) {
            return status_badge((string) $v, $prefix);
        }
        return match ($type) {
            'checkbox' => $v ? '✓ ' . e(__('common.yes')) : e(__('common.no')),
            'money'    => money($v, !empty($f['currency_field']) ? (string) ($row[$f['currency_field']] ?? 'QAR') : 'QAR'),
            'decimal'  => e(rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.')),
            'date'     => '<span class="' . e(!empty($f['expiry']) ? expiry_class($v) : '') . '">' . e(fmt_date($v)) . '</span>',
            'select', 'segment' => e(opt_label((is_callable($f['options']) ? ($f['options'])() : $f['options'])[$v] ?? (string) $v)),
            'lookup'   => e(loc(DB::row('SELECT value_en, value_ar FROM lookups WHERE id = ?', [$v]), 'value')),
            'picker'   => $this->pickerLink($f['source'], (int) $v),
            'country'  => e((string) Pickers::label(!empty($f['as']) && $f['as'] === 'nationality' ? 'nationalities' : 'countries', $v) ?? $v),
            'encrypted'=> e((string) Crypto::decrypt($v)),
            'email'    => '<a href="mailto:' . e($v) . '">' . e($v) . '</a>',
            'phone'    => '<a href="tel:' . e($v) . '" dir="ltr">' . e($v) . '</a>',
            'url'      => '<a href="' . e($v) . '" target="_blank" rel="noopener">' . e($v) . '</a>',
            'time'     => e(substr((string) $v, 0, 5)),
            'textarea' => nl2br(e($v)),
            default    => e($v),
        };
    }

    /** The badge prefix when the list shows this field (same key, same stored value) as a status badge; null otherwise. */
    protected function badgePrefix(string $name): ?string
    {
        static $cache = [];
        $k = static::class . '|' . $name;
        if (!array_key_exists($k, $cache)) {
            $c = $this->columns()[$name] ?? null;
            $cache[$k] = $c && ($c['fmt'] ?? '') === 'badge' && preg_match('/^t\.`?' . preg_quote($name, '/') . '`?$/', (string) ($c['sql'] ?? '')) ? (string) ($c['prefix'] ?? 'status') : null;
        }
        return $cache[$k];
    }

    protected function pickerLink(string $source, int $id): string
    {
        $label = Pickers::label($source, $id) ?? ('#' . $id);
        $map = ['horses' => 'horses', 'mares' => 'horses', 'stallions' => 'horses', 'employees' => 'employees', 'clients' => 'parties', 'suppliers' => 'parties', 'parties' => 'parties', 'items' => 'items', 'embryos' => 'embryos', 'shows' => 'shows', 'bills' => 'bills', 'accounts' => 'accounts', 'inventories' => 'inventories', 'invoices' => 'invoices', 'purchase_orders' => 'purchase-orders', 'tanks' => 'tanks', 'canisters' => 'canisters'];
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
