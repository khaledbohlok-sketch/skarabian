<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Crypto;
use App\Core\DB;

/**
 * Imports the old SK Arabians portal database into the new system.
 *
 * The old database is only READ. Its exact layout is not documented, so the importer discovers it: for each kind of
 * record it looks for a matching table and matching columns (see ENTITIES); anything it cannot place is listed in the
 * migration report instead of being guessed, and unknown columns are kept in the record notes so nothing is lost.
 * The names can be forced in migration/mapping.php when the discovery is wrong.
 *
 * Preview (the default) performs the complete import inside a transaction and then rolls it back, so the report
 * shows exactly what a real import will do while the new database stays untouched. Commit does the same and keeps it.
 * Re-running is safe: records already imported (table import_map) are skipped.
 */
final class OldImport
{
    /** entity => [candidate table names, [field => candidate column names]] */
    public const ENTITIES = [
        'roles' => [['roles', 'role', 'user_roles', 'tbl_roles'], [
            'id' => ['id', 'role_id'], 'name' => ['role_name', 'name', 'title', 'role'],
        ]],
        'users' => [['users', 'user', 'admins', 'tbl_users', 'portal_users'], [
            'id' => ['id', 'user_id'], 'username' => ['username', 'user_name', 'login', 'user'], 'name' => ['full_name', 'fullname', 'name', 'display_name'],
            'email' => ['email', 'email_address', 'mail'], 'role_id' => ['role_id', 'roleid'], 'role' => ['role', 'role_name', 'user_role', 'type', 'user_type'],
            'active' => ['is_active', 'active', 'status', 'enabled'], 'phone' => ['phone', 'mobile'],
        ]],
        'horses' => [['horses', 'horse', 'tbl_horses', 'horse_list'], [
            'id' => ['id', 'horse_id'], 'name' => ['horse_name', 'name', 'name_en', 'english_name'], 'name_ar' => ['arabic_name', 'name_ar', 'horse_name_ar'],
            'dob' => ['date_of_birth', 'dob', 'birth_date', 'birthdate', 'foaling_date', 'born'], 'sex' => ['gender', 'sex'], 'category' => ['category', 'horse_type', 'type', 'class'],
            'color' => ['color', 'colour'], 'breed' => ['breed', 'breed_name'], 'sire' => ['sire', 'sire_name', 'father', 'father_name'], 'dam' => ['dam', 'dam_name', 'mother', 'mother_name'],
            'sire_id' => ['sire_id', 'father_id'], 'dam_id' => ['dam_id', 'mother_id'], 'microchip' => ['microchip', 'microchip_no', 'chip', 'chip_no'],
            'registration' => ['reg_no', 'registration_no', 'registration', 'reg_number', 'registration_number'], 'passport' => ['passport_no', 'passport', 'passport_number'],
            'status' => ['status', 'horse_status'], 'location' => ['shelter', 'location', 'stable', 'box'], 'breeder' => ['breeder'], 'price' => ['purchase_price', 'price', 'cost'],
            'notes' => ['notes', 'note', 'remarks', 'description'], 'website' => ['show_on_website', 'on_website', 'public', 'is_public'], 'favorite' => ['is_favorite', 'favorite', 'favourite'],
            'owner' => ['owner', 'owner_name'], 'passport_issued' => ['passport_issued', 'passport_issue_date', 'passport_date'],
            'passport_place' => ['passport_issuing_authority', 'passport_issue_place', 'passport_issued_by', 'passport_authority'],
            'pregnant' => ['is_pregnant', 'pregnant', 'in_foal'], 'covered' => ['pregnancy_start', 'cover_date', 'covering_date', 'mating_date'],
            'due' => ['due_date', 'expected_foaling', 'expected_foaling_date'],
        ]],
        'embryos' => [['embryos', 'embryo', 'tbl_embryos'], [
            'id' => ['id', 'embryo_id'], 'code' => ['embryo_code', 'code', 'ref', 'reference', 'embryo_no'], 'name' => ['embryo_name', 'name'],
            'dam' => ['dam_name', 'dam', 'donor', 'donor_mare', 'mare', 'mare_name', 'mother', 'mother_name', 'mother_manual'],
            'sire' => ['sire_name', 'sire', 'stallion', 'stallion_name', 'father', 'father_name', 'father_manual'],
            'dam_id' => ['dam_id', 'donor_id', 'mare_id', 'mother_id'], 'sire_id' => ['sire_id', 'stallion_id', 'father_id'], 'owner' => ['owner', 'owner_name'],
            'flush' => ['collection_date', 'flush_date', 'flushing_date', 'date'], 'expected' => ['date_of_birth', 'dob', 'birth_date', 'expected_date', 'expected_foaling', 'due_date'],
            'status' => ['status'], 'grade' => ['grade', 'quality'], 'stage' => ['stage'], 'location' => ['shelter', 'location', 'storage', 'storage_location'],
            'recipient' => ['recipient', 'recipient_mare', 'recipient_name'], 'recipient_id' => ['recipient_id'], 'transfer' => ['transfer_date'], 'notes' => ['notes', 'note', 'remarks'],
        ]],
        'employees' => [['employees', 'employee', 'staff', 'tbl_employees'], [
            'id' => ['id', 'employee_id'], 'name' => ['name', 'full_name', 'employee_name', 'name_en'], 'name_ar' => ['name_ar', 'arabic_name'],
            'position' => ['position', 'job_title', 'designation', 'title', 'job'], 'department' => ['department', 'dept'], 'nationality' => ['nationality', 'country'],
            'phone' => ['phone', 'mobile', 'phone_number', 'contact', 'mobile_no'], 'email' => ['email'], 'hire' => ['joining_date', 'hire_date', 'join_date', 'date_joined', 'start_date'],
            'status' => ['status'], 'salary' => ['basic_salary', 'salary', 'basic'], 'salary_currency' => ['salary_currency', 'currency'], 'allowances' => ['allowances', 'allowance', 'other_allowance'],
            'housing' => ['housing_allowance', 'housing'], 'transport' => ['transport_allowance', 'transport'],
            'qid' => ['qid', 'qid_no', 'qid_number', 'qatar_id'], 'qid_expiry' => ['qid_expiry', 'qid_exp', 'qid_expiry_date'],
            'passport' => ['passport_no', 'passport', 'passport_number'], 'passport_expiry' => ['passport_expiry', 'passport_exp'], 'visa_expiry' => ['visa_expiry', 'rp_expiry', 'residence_expiry'],
            'health_expiry' => ['health_card_expiry', 'health_expiry'], 'bank' => ['bank', 'bank_name'], 'iban' => ['iban', 'account_no', 'bank_account'],
            'website' => ['show_on_website', 'on_website'], 'notes' => ['notes', 'remarks'],
        ]],
        'categories' => [['categories', 'bill_categories', 'expense_categories', 'finance_categories', 'category', 'transaction_categories'], [
            'id' => ['id', 'category_id'], 'name' => ['name', 'category_name', 'title'], 'type' => ['type', 'category_type'], 'parent_id' => ['parent_id', 'parent'],
        ]],
        'currencies' => [['currencies', 'currency', 'currency_rates'], [
            'code' => ['code', 'currency_code', 'currency'], 'rate' => ['rate', 'exchange_rate', 'rate_to_usd', 'usd_rate', 'rate_to_base', 'value'],
        ]],
        'bills' => [['bills', 'transactions', 'bill', 'expenses', 'finance', 'finance_transactions', 'tbl_bills'], [
            'id' => ['id', 'bill_id', 'transaction_id'], 'number' => ['bill_no', 'bill_number', 'number', 'reference', 'ref', 'invoice_no', 'ref_no'],
            'type' => ['type', 'transaction_type', 'bill_type', 'kind'], 'category_id' => ['category_id', 'cat_id'], 'category' => ['category', 'category_name'],
            'description' => ['description', 'title', 'details', 'item', 'particulars'], 'amount' => ['amount', 'amount_original', 'original_amount', 'total', 'value'],
            'amount_usd' => ['amount_usd', 'usd_amount', 'amount_in_usd', 'usd'], 'amount_qar' => ['amount_qar', 'qar_amount', 'amount_in_qar'],
            'currency' => ['currency', 'currency_code'], 'rate' => ['exchange_rate', 'rate', 'conversion_rate'], 'date' => ['bill_date', 'date', 'transaction_date', 'created_at'],
            'due' => ['due_date', 'due'], 'status' => ['status', 'payment_status'], 'paid' => ['paid_amount', 'amount_paid'], 'paid_at' => ['paid_date', 'payment_date', 'paid_at'],
            'party' => ['supplier', 'vendor', 'client', 'party', 'payee', 'supplier_name', 'customer'], 'horse_id' => ['horse_id'], 'horse' => ['horse', 'horse_name'],
            'method' => ['payment_method', 'method', 'paid_by'], 'notes' => ['notes', 'note', 'remarks'], 'created_by' => ['created_by', 'user_id', 'added_by'],
        ]],
        'inventories' => [['inventories', 'inventory', 'stores', 'warehouses', 'inventory_categories'], [
            'id' => ['id', 'inventory_id'], 'name' => ['name', 'inventory_name', 'title'], 'category' => ['category', 'type'],
        ]],
        'items' => [['inventory_items', 'items', 'products', 'stock_items', 'stock'], [
            'id' => ['id', 'item_id'], 'name' => ['item_name', 'name', 'product_name', 'title'], 'inventory_id' => ['inventory_id', 'store_id', 'inventory', 'category_id'],
            'unit' => ['unit', 'uom'], 'quantity' => ['qty', 'quantity', 'stock', 'in_stock'], 'price' => ['unit_price', 'price', 'cost', 'unit_cost'],
            'min' => ['min_qty', 'min_quantity', 'minimum', 'reorder_level', 'min_stock'], 'expiry' => ['expiry_date', 'expiry', 'exp_date', 'expires'], 'supplier' => ['supplier', 'vendor', 'supplier_name'],
        ]],
        // Horse history kept in separate tables in the old portal (one row per ancestor / visit / session)
        'pedigree' => [['horse_pedigree', 'pedigree', 'pedigrees', 'horse_pedigrees'], [
            'id' => ['id'], 'horse_id' => ['horse_id'], 'relation' => ['relation', 'position', 'path', 'relation_type'], 'name' => ['ancestor_name', 'name', 'horse_name'],
            'country' => ['origin_country', 'country'], 'registration' => ['registration_number', 'registration_no', 'reg_no'], 'color' => ['color', 'colour'], 'breed' => ['breed'],
        ]],
        'farrier' => [['horse_farrier', 'farrier', 'farrier_visits'], [
            'id' => ['id'], 'horse_id' => ['horse_id'], 'date' => ['service_date', 'visit_date', 'date'], 'by' => ['farrier_name', 'farrier'], 'service' => ['service_type', 'type'],
            'shoe' => ['shoe_type', 'shoes'], 'cost' => ['cost', 'amount'], 'next' => ['next_due_date', 'next_due', 'next_visit'], 'notes' => ['notes', 'remarks'],
        ]],
        'medical' => [['horse_medical', 'medical', 'vet_visits', 'horse_vet'], [
            'id' => ['id'], 'horse_id' => ['horse_id'], 'date' => ['visit_date', 'date'], 'by' => ['vet_name', 'vet', 'doctor'], 'diagnosis' => ['diagnosis', 'reason'],
            'treatment' => ['treatment'], 'prescription' => ['prescription', 'medicine'], 'cost' => ['cost', 'amount'], 'next' => ['follow_up_date', 'next_due_date', 'follow_up'], 'notes' => ['notes', 'remarks'],
        ]],
        'vaccinations' => [['horse_vaccinations', 'vaccinations', 'horse_vaccines', 'vaccines'], [
            'id' => ['id'], 'horse_id' => ['horse_id'], 'date' => ['vaccine_date', 'vaccination_date', 'date'], 'name' => ['vaccine_name', 'vaccine', 'name'], 'type' => ['type'],
            'dosage' => ['dosage', 'dose'], 'by' => ['administered_by', 'vet_name', 'given_by'], 'next' => ['next_due_date', 'next_due'], 'batch' => ['batch_number', 'batch'],
            'cost' => ['cost', 'amount'], 'notes' => ['notes', 'remarks'],
        ]],
        'training' => [['horse_training', 'training', 'training_logs', 'horse_trainings'], [
            'id' => ['id'], 'horse_id' => ['horse_id'], 'date' => ['training_date', 'date'], 'trainer' => ['trainer', 'trainer_name'], 'session' => ['session_type', 'activity', 'type'],
            'minutes' => ['duration_minutes', 'duration', 'minutes'], 'intensity' => ['intensity'], 'rating' => ['performance_rating', 'rating'], 'distance' => ['distance_km', 'distance'],
            'notes' => ['notes', 'remarks'],
        ]],
        'measurements' => [['horse_measurements', 'measurements', 'horse_measures'], [
            'id' => ['id'], 'horse_id' => ['horse_id'], 'date' => ['measure_date', 'date'], 'height' => ['height_cm', 'height'], 'weight' => ['weight_kg', 'weight'],
        ]],
    ];

    /** Columns never copied into notes. */
    private const IGNORE = ['id', 'password', 'password_hash', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'token', 'remember_token', 'api_token', 'permissions', 'last_login', 'photo', 'image'];

    /** Spelling fixes for category names (old → new). */
    private const CATEGORY_FIXES = ['expenese' => 'expenses', 'expences' => 'expenses', 'puplic' => 'public', 'employees expenses' => 'employee expenses', 'salary' => 'salaries'];

    private const BREEDS = ['arabian breed' => 'Purebred Arabian', 'arabian' => 'Purebred Arabian', 'purebred arabian' => 'Purebred Arabian', 'pure arabian' => 'Purebred Arabian', 'pb arabian' => 'Purebred Arabian', 'asil' => 'Purebred Arabian'];

    private string $run;
    private array $tables = [];      // lowercase name => real name
    private array $columns = [];     // real table => [lowercase col => real col]
    private array $found = [];       // entity => [table, [field => col]]
    private array $report = [];
    private array $map = [];         // entity => [old id => new id]
    private array $horseIndex = [];  // skeleton key => new horse id
    private array $counts = [];
    private ?int $cashAccount = null;
    private ?int $bankAccount = null;
    private ?float $oldQar = null;   // the old system's value of 1 QAR in its USD base (0.2745): how it showed QAR amounts
    private array $pregnant = [];    // mares marked pregnant, turned into breeding records once every horse exists

    public function __construct(private \PDO $src, private bool $commit = false, private array $overrides = [])
    {
        $this->run = ($commit ? 'I-' : 'P-') . date('Ymd-His');
    }

    public static function connect(array $c): \PDO
    {
        return new \PDO('mysql:host=' . ($c['host'] ?? 'localhost') . ';port=' . ($c['port'] ?? 3306) . ';dbname=' . $c['name'] . ';charset=' . ($c['charset'] ?? 'utf8mb4'),
            $c['user'], $c['pass'] ?? '', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
    }

    public static function overridesFile(): array
    {
        $f = APP_ROOT . '/migration/mapping.php';
        return is_file($f) ? (array) require $f : [];
    }

    /** Runs the import. Returns ['run' => id, 'counts' => [...], 'report' => rows written]. */
    public function run(): array
    {
        @set_time_limit(0);
        $this->discover();
        $this->loadOldRates();
        try {
            DB::transaction(function () {
                $this->loadExistingMap();
                $this->users();
                $this->horses();
                $this->pedigree();
                $this->pregnancies();
                $this->embryos();
                $this->employees();
                $this->history();
                $this->categories();
                $this->currencies();
                $this->bills();
                $this->inventory();
                FinanceService::refreshOverdue();
                foreach ($this->counts as $entity => $n) {
                    $this->rep($entity, null, null, 'imported', $n . ' records');
                }
                if (!$this->commit) {
                    throw new PreviewRollback();
                }
                Audit::log('import', 'settings', null, null, null, ['run' => $this->run, 'counts' => $this->counts], 'Old system data imported', ['id' => null, 'name' => 'Import']);
                if (!empty($this->counts['approvals'])) {
                    Notifier::owners('approval', Notifier::msg('migration.approvals_waiting', ['n' => $this->counts['approvals']]), null, '/portal/approvals', true);
                }
            });
        } catch (PreviewRollback) {
            // preview: everything above is undone; only the report is kept
        }
        Cache::bump();
        foreach ($this->report as $r) {
            DB::insert('migration_report', ['run_id' => $this->run] + $r);
        }
        return ['run' => $this->run, 'counts' => $this->counts, 'report' => count($this->report), 'schema' => $this->found];
    }

    // ------------------------------------------------------------------ discovery

    private function discover(): void
    {
        foreach ($this->src->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) as $t) {
            $this->tables[strtolower($t)] = $t;
        }
        $used = [];
        foreach (self::ENTITIES as $entity => [$candidates, $fields]) {
            $ov = $this->overrides[$entity] ?? [];
            $table = null;
            foreach (array_merge(isset($ov['table']) ? [$ov['table']] : [], $candidates) as $c) {
                if (isset($this->tables[strtolower($c)])) {
                    $table = $this->tables[strtolower($c)];
                    break;
                }
            }
            if (!$table) {
                $this->rep('schema', $entity, null, 'warning', 'No table found for ' . $entity . ' (looked for: ' . implode(', ', $candidates) . '). Set it in migration/mapping.php if it exists under another name.');
                continue;
            }
            $cols = $this->cols($table);
            $map = [];
            foreach ($fields as $field => $names) {
                foreach (array_merge(isset($ov['columns'][$field]) ? [$ov['columns'][$field]] : [], $names) as $n) {
                    if (isset($cols[strtolower($n)]) && !in_array($cols[strtolower($n)], $map, true)) {
                        $map[$field] = $cols[strtolower($n)];
                        break;
                    }
                }
            }
            $this->found[$entity] = [$table, $map];
            $used[] = $table;
            $unmapped = array_diff(array_values($cols), array_values($map), self::IGNORE);
            $this->rep('schema', $entity, null, 'linked', 'Table `' . $table . '` (' . $this->count($table) . ' rows). Columns: ' . implode(', ', array_map(fn ($f, $c) => "$c → $f", array_keys($map), $map))
                . ($unmapped ? '. Kept in notes: ' . implode(', ', $unmapped) : ''));
        }
        foreach (array_diff($this->tables, $used) as $t) {
            $this->rep('schema', $t, null, 'skipped', 'Table `' . $t . '` (' . $this->count($t) . ' rows) is not imported' . ($t === 'activity_log' ? ': the old log did not record logins and actions, the new log starts clean.' : '. Check whether it holds anything needed.'));
        }
    }

    private function cols(string $table): array
    {
        if (!isset($this->columns[$table])) {
            $this->columns[$table] = [];
            foreach ($this->src->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`')->fetchAll() as $c) {
                $this->columns[$table][strtolower($c['Field'])] = $c['Field'];
            }
        }
        return $this->columns[$table];
    }

    private function count(string $table): int
    {
        return (int) $this->src->query('SELECT COUNT(*) FROM `' . str_replace('`', '', $table) . '`')->fetchColumn();
    }

    /** Rows of an entity's table, each with a helper to read mapped fields. */
    private function rows(string $entity): array
    {
        if (!isset($this->found[$entity])) {
            return [];
        }
        [$table, $map] = $this->found[$entity];
        $order = isset($map['id']) ? ' ORDER BY `' . $map['id'] . '`' : '';
        return $this->src->query('SELECT * FROM `' . str_replace('`', '', $table) . '`' . $order)->fetchAll();
    }

    private function v(string $entity, array $row, string $field): mixed
    {
        $col = $this->found[$entity][1][$field] ?? null;
        if ($col === null || !array_key_exists($col, $row)) {
            return null;
        }
        $v = $row[$col];
        if (is_string($v)) {
            $v = trim(preg_replace('/\s+/u', ' ', $v));
            if ($v === '' || in_array(strtolower($v), ['null', 'n/a', '-', '0000-00-00', '0000-00-00 00:00:00'], true)) {
                return null;
            }
        }
        return $v;
    }

    /** Old values with no place in the new record, kept as text so nothing is lost. */
    private function leftovers(string $entity, array $row, array $alsoUsed = []): string
    {
        $mapped = array_values($this->found[$entity][1] ?? []);
        $out = [];
        foreach ($row as $col => $val) {
            if (in_array($col, $mapped, true) || in_array(strtolower($col), self::IGNORE, true) || in_array($col, $alsoUsed, true) || $val === null || trim((string) $val) === '') {
                continue;
            }
            $out[] = $col . ': ' . trim((string) $val);
        }
        return $out ? 'Old system — ' . implode('; ', $out) : '';
    }

    private function oldId(string $entity, array $row): string
    {
        return (string) ($this->v($entity, $row, 'id') ?? md5(json_encode($row)));
    }

    // ------------------------------------------------------------------ report & map

    private function rep(string $entity, int|string|null $oldId, ?int $newId, string $action, string $details): void
    {
        $this->report[] = ['entity' => $entity, 'old_id' => $oldId !== null ? mb_substr((string) $oldId, 0, 40) : null, 'new_id' => $newId, 'action' => $action, 'details' => $details];
    }

    private function remember(string $entity, string $oldId, int $newId, bool $count = true): void
    {
        $this->map[$entity][$oldId] = $newId;
        DB::run('INSERT IGNORE INTO import_map (entity, old_id, new_id) VALUES (?, ?, ?)', [$entity, $oldId, $newId]);
        if ($count) {
            $this->counts[$entity] = ($this->counts[$entity] ?? 0) + 1;
        }
    }

    /** Records imported by an earlier (committed) run are not imported again. */
    private function loadExistingMap(): void
    {
        foreach (DB::all('SELECT entity, old_id, new_id FROM import_map') as $m) {
            $this->map[$m['entity']][$m['old_id']] = (int) $m['new_id'];
        }
        foreach (DB::all('SELECT id, name_en FROM horses WHERE deleted_at IS NULL') as $h) {
            $this->horseIndex[self::key($h['name_en'])] ??= (int) $h['id'];
        }
    }

    /** Approval request without one notification per record; a single summary is sent after the import. */
    private function approval(string $type, string $recordType, int $recordId, string $title, ?float $amount = null): void
    {
        DB::insert('approvals', ['type' => $type, 'record_type' => $recordType, 'record_id' => $recordId, 'title' => mb_substr($title, 0, 200), 'amount_qar' => $amount, 'requested_by' => 0]);
        $this->counts['approvals'] = ($this->counts['approvals'] ?? 0) + 1;
    }

    private function already(string $entity, string $oldId): ?int
    {
        return $this->map[$entity][$oldId] ?? null;
    }

    // ------------------------------------------------------------------ text helpers

    /**
     * Spelling-tolerant key for Arabic names written in Latin letters: upper case, vowels removed (except the first
     * letter of each word), double letters collapsed and a final "H" after a vowel dropped.
     * AJ RAZNEH = AJ RAZENAH, SG SHAMMAH = SG SHAMMA = SG SHAMA, BDOOR AL BAYHA = BADOOR AL BAHIYA, D TMOUH = D TUMOOH.
     * Dots and apostrophes join, not split: EXCALIBUR E.A. = EXCALIBUR EA, D A'BAQ = D ABAQ.
     */
    public static function key(string $name): string
    {
        $name = preg_replace("/[.'’`]+/u", '', $name);
        $name = strtoupper(preg_replace('/[^A-Za-z0-9 ]+/', ' ', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name));
        $words = [];
        foreach (preg_split('/\s+/', trim($name)) as $w) {
            if ($w === '') {
                continue;
            }
            $w = preg_replace('/([AEIOUY])H$/', '$1', $w);
            $first = $w[0];
            $rest = preg_replace('/[AEIOUYW]/', '', substr($w, 1));
            $words[] = preg_replace('/(.)\1+/', '$1', $first . $rest);
        }
        return implode(' ', $words);
    }

    public static function similar(string $a, string $b): bool
    {
        $ka = self::key($a);
        $kb = self::key($b);
        if ($ka === '' || $kb === '') {
            return false;
        }
        return $ka === $kb || (min(strlen($ka), strlen($kb)) >= 5 && levenshtein($ka, $kb) <= 1);
    }

    private static function date(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $v = trim((string) $v);
        foreach (['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d', 'm/d/Y'] as $f) {
            $d = \DateTime::createFromFormat('!' . $f, $v);
            if ($d && $d->format($f) === $v && (int) $d->format('Y') > 1950) {
                return $d->format('Y-m-d');
            }
        }
        $t = strtotime($v);
        return $t && (int) date('Y', $t) > 1950 ? date('Y-m-d', $t) : null;
    }

    private static function num(mixed $v): ?float
    {
        return $v === null ? null : Money::parse($v);
    }

    private function lookup(string $type, ?string $value, ?string $entity = null, ?string $oldId = null): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = trim($value);
        if ($type === 'breed' && isset(self::BREEDS[strtolower($value)])) {
            $fixed = self::BREEDS[strtolower($value)];
            if ($fixed !== $value && $entity) {
                $this->rep($entity, $oldId, null, 'fixed', "Breed \"$value\" → \"$fixed\"");
            }
            $value = $fixed;
        }
        foreach (DB::all('SELECT id, value_en FROM lookups WHERE type = ?', [$type]) as $l) {
            if (strcasecmp($l['value_en'], $value) === 0 || ($type !== 'location' && self::similar($l['value_en'], $value))) {
                return (int) $l['id'];
            }
        }
        $id = DB::insert('lookups', ['type' => $type, 'value_en' => mb_substr(ucwords(strtolower($value)), 0, 120), 'sort' => 50]);
        $this->rep('lookups', null, $id, 'created', "New $type in the lists: $value");
        return $id;
    }

    /**
     * Matches a free-text nationality/country from the old system against the fixed `countries` table
     * (ISO code, e.g. "QA") by English or Arabic name, either form. Unlike lookup(), it never invents a
     * new row — an unmatched value is reported so it can be fixed by hand, and the field is left empty.
     */
    private function countryCode(?string $value, ?string $entity = null, ?string $oldId = null): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        foreach (DB::all('SELECT id, name_en, name_ar, nationality_en, nationality_ar FROM countries') as $c) {
            foreach ([$c['name_en'], $c['name_ar'], $c['nationality_en'], $c['nationality_ar']] as $candidate) {
                if (strcasecmp($candidate, $value) === 0 || self::similar($candidate, $value)) {
                    return $c['id'];
                }
            }
        }
        if ($entity) {
            $this->rep($entity, $oldId, null, 'skipped', "Nationality/country \"$value\" did not match any country — left empty, set it by hand.");
        }
        return null;
    }

    // ------------------------------------------------------------------ users & roles

    private function roleSlug(?string $name): ?string
    {
        $n = strtolower((string) $name);
        return match (true) {
            $n === '' => null,
            (bool) preg_match('/own|onw|admin/', $n) => 'owner',
            (bool) preg_match('/manag|\bgm\b|director/', $n) => 'general_manager',
            (bool) preg_match('/account|financ/', $n) => 'accountant',
            (bool) preg_match('/\bhr\b|human/', $n) => 'hr_officer',
            (bool) preg_match('/vet|doctor/', $n) => 'veterinarian',
            (bool) preg_match('/train|instruct|coach|riding/', $n) => 'trainer',
            (bool) preg_match('/web|editor|content|media/', $n) => 'website_editor',
            (bool) preg_match('/program|develop|\bit\b|tech|support/', $n) => 'tech_support',
            (bool) preg_match('/groom|stable|staff|worker/', $n) => 'groom',
            default => null,
        };
    }

    private function users(): void
    {
        $roleNames = [];
        foreach ($this->rows('roles') as $r) {
            $roleNames[(string) $this->v('roles', $r, 'id')] = (string) $this->v('roles', $r, 'name');
        }
        $roles = DB::pairs('SELECT slug, id FROM roles');
        foreach ($roleNames as $oid => $name) {
            $slug = $this->roleSlug($name);
            $label = $slug ? DB::value('SELECT name_en FROM roles WHERE slug = ?', [$slug]) : null;
            $note = match (true) {
                !$slug => 'No matching role: users with it get "Groom / Stable Staff" until you change them',
                strtolower($name) === strtolower((string) $label) => "→ $label",
                default => "\"$name\" → $label" . ($slug === 'owner' && strcasecmp($name, 'owner') !== 0 && self::similar($name, 'owner') ? ' (spelling fixed)' : '') . ($slug === 'tech_support' ? ' (no finance, salary or HR access unless the Owner grants it)' : ''),
            };
            $this->rep('roles', $oid, $slug ? (int) $roles[$slug] : null, $slug ? 'linked' : 'warning', $note);
        }
        foreach ($this->rows('users') as $r) {
            $oid = $this->oldId('users', $r);
            if ($this->already('users', $oid)) {
                continue;
            }
            $email = strtolower((string) $this->v('users', $r, 'email'));
            $username = strtolower(preg_replace('/[^a-z0-9._-]/i', '', (string) ($this->v('users', $r, 'username') ?? explode('@', $email)[0])));
            $name = (string) ($this->v('users', $r, 'name') ?? $username);
            $existing = DB::row('SELECT id, username FROM users WHERE (email <> \'\' AND LOWER(email) = ?) OR LOWER(username) = ?', [$email, $username]);
            if ($existing) {
                $this->remember('users', $oid, (int) $existing['id'], false);
                $this->rep('users', $oid, (int) $existing['id'], 'merged', "\"$name\" is already a user here ({$existing['username']}); linked, not copied");
                continue;
            }
            $roleName = $this->v('users', $r, 'role') ?? ($roleNames[(string) $this->v('users', $r, 'role_id')] ?? null);
            $slug = $this->roleSlug($roleName) ?? 'groom';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = $username . '@invalid.local';
                $this->rep('users', $oid, null, 'warning', "\"$name\" has no valid email; enter it before activating the account");
            }
            if (strlen($username) < 3) {
                $username .= '_' . $oid;
            }
            $id = DB::insert('users', [
                'username' => $username, 'email' => $email, 'name' => mb_substr($name, 0, 120), 'phone' => $this->v('users', $r, 'phone'),
                'password_hash' => Passwords::hash(Passwords::generate(20)), 'role_id' => $roles[$slug], 'status' => 'pending', 'must_change_password' => 1, 'lang' => 'en',
            ]);
            $this->approval('new_user', 'user', $id, __('users.approval_title', ['name' => $name]));
            $active = $this->v('users', $r, 'active');
            $this->remember('users', $oid, $id);
            $this->rep('users', $oid, $id, 'imported', "$name ($username) as " . DB::value('SELECT name_en FROM roles WHERE slug = ?', [$slug])
                . '. Waiting for Owner approval; a new password must be set (old passwords are not copied)'
                . ($active !== null && in_array(strtolower((string) $active), ['0', 'inactive', 'disabled', 'no'], true) ? '. Was inactive in the old system' : ''));
        }
    }

    // ------------------------------------------------------------------ horses

    /** Geldings are recorded as male: the new system does not track gelding as a separate sex/category. */
    private function sex(?string $gender, ?string $category): string
    {
        $g = strtolower(($gender ?? '') . ' ' . ($category ?? ''));
        return (bool) preg_match('/\b(f|female|mare|filly)\b/', $g) ? 'female' : 'male';
    }

    /** Set by findHorse(): true when the last match was only "close" (one letter apart), not the same spelling key. */
    private bool $fuzzy = false;

    /**
     * The horse with this name (same spelling key), else, when $close, one a single letter away (sets $fuzzy). With $sex,
     * a horse of the other sex never counts: a sire position is never filled with a mare.
     */
    private function findHorse(?string $name, ?string $sex = null, bool $close = true): ?int
    {
        $this->fuzzy = false;
        if ($name === null || trim($name) === '') {
            return null;
        }
        $fits = fn (int $id) => $sex === null || DB::value('SELECT sex FROM horses WHERE id = ?', [$id]) === $sex;
        $k = self::key($name);
        if (isset($this->horseIndex[$k]) && $fits($this->horseIndex[$k])) {
            return $this->horseIndex[$k];
        }
        if (!$close) {
            return null;
        }
        foreach ($this->horseIndex as $key => $id) {
            if (min(strlen($k), strlen((string) $key)) >= 5 && levenshtein($k, (string) $key) <= 1 && $fits($id)) {
                $this->fuzzy = true;
                return $id;
            }
        }
        return null;
    }

    /** Pedigree-only record for a parent or donor that is not one of our horses ($extra: origin_country, registration_no, colour, breed). */
    private function externalHorse(string $name, string $sex, string $entity, ?string $oldId, string $why, array $extra = []): int
    {
        $name = mb_substr(trim($name), 0, 120);
        $id = DB::insert('horses', [
            'name_en' => $name, 'slug' => HorseService::uniqueSlug($name), 'sex' => $sex, 'category' => $sex === 'male' ? 'stallion' : 'mare',
            'is_external' => 1, 'owner_type' => 'client', 'status' => 'active', 'notes' => 'Added by the import (' . $why . ')',
        ] + array_filter($extra, fn ($v) => $v !== null && $v !== ''));
        $this->horseIndex[self::key($name)] = $id;
        $this->rep($entity, $oldId, $id, 'created', "\"$name\" is not in the horse list; added as an external horse ($why)");
        return $id;
    }

    private function horses(): void
    {
        $rows = $this->rows('horses');
        $created = [];
        foreach ($rows as $r) {
            $oid = $this->oldId('horses', $r);
            if ($this->already('horses', $oid)) {
                continue;
            }
            $name = (string) $this->v('horses', $r, 'name');
            if ($name === '') {
                $this->rep('horses', $oid, null, 'skipped', 'Horse without a name');
                continue;
            }
            $dob = self::date($this->v('horses', $r, 'dob'));
            // Same horse entered twice with a different spelling: keep one record
            $dup = $this->findHorse($name);
            if ($dup) {
                $other = DB::row('SELECT name_en, dob FROM horses WHERE id = ?', [$dup]);
                if (!$dob || !$other['dob'] || $dob === $other['dob']) {
                    $this->remember('horses', $oid, $dup, false);
                    $this->rep('horses', $oid, $dup, 'merged', "\"$name\" is the same horse as \"{$other['name_en']}\"; merged into one record");
                    continue;
                }
                $this->rep('horses', $oid, null, 'warning', "\"$name\" looks like \"{$other['name_en']}\" but the birth dates differ; kept as two horses — check");
            }
            $sex = $this->sex($this->v('horses', $r, 'sex'), $this->v('horses', $r, 'category'));
            $category = HorseRules::category($sex, $dob);
            $oldCat = strtolower((string) $this->v('horses', $r, 'category'));
            $status = strtolower((string) $this->v('horses', $r, 'status'));
            $archived = null;
            $notes = [];
            $newStatus = match (true) {
                (bool) preg_match('/dead|deceas|died/', $status) => 'deceased',
                (bool) preg_match('/shelter/', $status) => 'in_shelter',
                default => 'active',
            };
            if (preg_match('/sold|transfer/', $status)) {
                $archived = date('Y-m-d H:i:s');
                $notes[] = 'Status in the old system: ' . $this->v('horses', $r, 'status');
            }
            $price = self::num($this->v('horses', $r, 'price'));
            $left = $this->leftovers('horses', $r);
            [$ownerType, $ownerParty] = $this->owner($this->v('horses', $r, 'owner'), 'horses', $oid, $name);
            $id = DB::insert('horses', [
                'name_en' => mb_substr($name, 0, 120), 'name_ar' => $this->v('horses', $r, 'name_ar'), 'slug' => HorseService::uniqueSlug($name),
                'registration_no' => $this->v('horses', $r, 'registration'), 'microchip' => $this->v('horses', $r, 'microchip'), 'passport_no' => $this->v('horses', $r, 'passport'),
                'passport_issue_date' => self::date($this->v('horses', $r, 'passport_issued')),
                'passport_issue_place' => ($pp = $this->v('horses', $r, 'passport_place')) !== null ? mb_substr((string) $pp, 0, 120) : null,
                'dob' => $dob, 'sex' => $sex, 'category' => $category, 'color_id' => $this->lookup('color', $this->v('horses', $r, 'color')),
                'breed_id' => $this->lookup('breed', $this->v('horses', $r, 'breed') ?? 'Purebred Arabian', 'horses', $oid), 'breeder' => $this->v('horses', $r, 'breeder'),
                'location_id' => $this->lookup('location', $this->v('horses', $r, 'location')), 'status' => $newStatus, 'archived_at' => $archived,
                'owner_type' => $ownerType, 'owner_party_id' => $ownerParty, 'born_at_sk' => $dob && $dob >= '2025-01-01' ? 1 : 0, 'purchase_price_qar' => $price,
                'show_on_website' => in_array(strtolower((string) $this->v('horses', $r, 'website')), ['1', 'yes', 'true'], true) ? 1 : 0,
                'is_favorite' => in_array(strtolower((string) $this->v('horses', $r, 'favorite')), ['1', 'yes', 'true'], true) ? 1 : 0,
                'notes' => trim(implode("\n", array_filter([$this->v('horses', $r, 'notes'), ...$notes, $left]))) ?: null,
            ]);
            $this->horseIndex[self::key($name)] = $id;
            $this->remember('horses', $oid, $id);
            $created[] = [$id, $oid, $r];
            if ($sex === 'female' && in_array(strtolower((string) $this->v('horses', $r, 'pregnant')), ['1', 'yes', 'true', 'y'], true)) {
                $this->pregnant[] = [$id, $oid, $name, self::date($this->v('horses', $r, 'covered')), self::date($this->v('horses', $r, 'due')), (string) $this->v('horses', $r, 'notes')];
            }
            if ($oldCat !== '' && $oldCat !== $category) {
                $this->rep('horses', $oid, $id, 'fixed', "$name: category \"" . $this->v('horses', $r, 'category') . "\" → " . __('horse.cat_' . $category) . ($dob ? ' (from age, born ' . $dob . ')' : ''));
            }
            if ($archived) {
                $this->rep('horses', $oid, $id, 'warning', "$name was \"" . $this->v('horses', $r, 'status') . "\" in the old system. The new system keeps no sales, so the horse is archived (still in the records and pedigrees)");
            }
            if (!$dob) {
                $this->rep('horses', $oid, $id, 'warning', "$name has no date of birth");
            }
        }
        // Second pass: sire and dam
        foreach ($created as [$id, $oid, $r]) {
            $set = [];
            foreach (['sire' => 'male', 'dam' => 'female'] as $p => $sex) {
                $pid = null;
                $byId = $this->v('horses', $r, $p . '_id');
                if ($byId !== null) {
                    $pid = $this->already('horses', (string) $byId);
                }
                $pname = $this->v('horses', $r, $p);
                if (!$pid && $pname) {
                    $pid = $this->findHorse($pname);
                    if ($pid && strcasecmp((string) DB::value('SELECT name_en FROM horses WHERE id = ?', [$pid]), $pname) !== 0) {
                        $this->rep('horses', $oid, $id, $this->fuzzy ? 'warning' : 'linked', DB::value('SELECT name_en FROM horses WHERE id = ?', [$id]) . ': ' . $p . " \"$pname\" → " . DB::value('SELECT name_en FROM horses WHERE id = ?', [$pid]) . ($this->fuzzy ? ' (similar name — check)' : ''));
                    }
                    $pid ??= $this->externalHorse($pname, $sex, 'horses', $oid, $p . ' of ' . DB::value('SELECT name_en FROM horses WHERE id = ?', [$id]));
                }
                if ($pid && $pid !== $id && !HorseService::isSelfOrDescendant($id, $pid)) {
                    $set[$p . '_id'] = $pid;
                }
            }
            if ($set) {
                DB::update('horses', $set, 'id = :id', ['id' => $id]);
            }
        }
        if ($created) {
            $this->rep('horses', null, null, 'warning', 'Choose which horses appear on the public website in Website → Horses on website, and add photos (the old system had none).');
        }
    }

    /**
     * Who owns an old horse or embryo: "Sk Arabian" in any spelling (or nobody) is the stud itself; any other name is a
     * client, added to Clients & Suppliers so the record shows whose it is.
     */
    private function owner(?string $name, string $entity, string $oid, string $label): array
    {
        $name = trim((string) $name);
        if ($name === '' || preg_match('/^s\.?\s*k\.?(\s*arabians?)?$/i', $name)) {
            return ['sk', null];
        }
        $party = $this->party($name, 'client');
        $this->rep($entity, $oid, null, 'linked', "$label: owner \"$name\" → a client in Clients & Suppliers");
        return ['client', $party];
    }

    // ------------------------------------------------------------------ pedigree (old: one row per ancestor)

    /** Old relation names ("sire", "dam_sire", "sire_dam_dam") as pedigree paths ("s", "ds", "sdd"). */
    private static function pedigreePath(string $relation): ?string
    {
        $path = '';
        foreach (preg_split('/[^a-z]+/', strtolower($relation), -1, PREG_SPLIT_NO_EMPTY) as $w) {
            $path .= match ($w) { 'sire', 'father', 's' => 's', 'dam', 'mother', 'd' => 'd', default => '?' };
        }
        return in_array($path, HorseService::pedigreePaths(3), true) ? $path : null; // the old table goes 3 generations back
    }

    /** "Al Rasheed Al Rayyan (BE)" → ["Al Rasheed Al Rayyan", "BE"] when BE is a known country code. */
    private function nameAndCountry(string $raw, ?string $countryField, string $oid): array
    {
        if (preg_match('/^(.*\S)\s*\(([A-Za-z]{2})\)$/', trim($raw), $m) && DB::value('SELECT id FROM countries WHERE id = ?', [strtoupper($m[2])])) {
            return [trim($m[1]), strtoupper($m[2])];
        }
        $c = strtoupper(trim((string) $countryField));
        $country = $c === '' ? null : (strlen($c) === 2 && DB::value('SELECT id FROM countries WHERE id = ?', [$c]) ? $c : $this->countryCode($countryField, 'pedigree', $oid));
        return [trim($raw), $country];
    }

    /**
     * The old pedigree table (one row per ancestor, relation "sire" … "dam_dam_dam") → the first 3 generations of the pedigree. Each ancestor
     * is found among the horses (ours, or one this import added earlier) or added as an external horse, then linked as
     * sire or dam. A position that already holds a different horse is kept and reported, never overwritten.
     */
    private function pedigree(): void
    {
        $byHorse = [];
        foreach ($this->rows('pedigree') as $r) {
            $path = self::pedigreePath((string) $this->v('pedigree', $r, 'relation'));
            if ($path === null || $this->v('pedigree', $r, 'name') === null) {
                $this->rep('pedigree', $this->oldId('pedigree', $r), null, 'skipped', 'Pedigree row without a known position or name ("' . $this->v('pedigree', $r, 'relation') . '")');
                continue;
            }
            $byHorse[(string) $this->v('pedigree', $r, 'horse_id')][$path] = $r;
        }
        $edges = ['' => ['s', 'd'], 's' => ['ss', 'sd'], 'd' => ['ds', 'dd'], 'ss' => ['sss', 'ssd'], 'sd' => ['sds', 'sdd'], 'ds' => ['dss', 'dsd'], 'dd' => ['dds', 'ddd']];
        $nameOf = fn (int $id) => (string) DB::value('SELECT name_en FROM horses WHERE id = ?', [$id]);
        foreach ($byHorse as $oldHorse => $rows) {
            $horseId = $this->already('horses', (string) $oldHorse);
            if (!$horseId) {
                $this->rep('pedigree', $oldHorse, null, 'warning', count($rows) . " pedigree rows belong to horse #$oldHorse, which is not in the old horse list; not imported");
                continue;
            }
            $horseName = $nameOf($horseId);
            $resolved = ['' => $horseId];
            $added = 0;
            foreach (HorseService::pedigreePaths(3) as $path) {
                if (!isset($rows[$path])) {
                    continue;
                }
                $r = $rows[$path];
                [$name, $country] = $this->nameAndCountry((string) $this->v('pedigree', $r, 'name'), $this->v('pedigree', $r, 'country'), (string) $oldHorse);
                // Same spelling and sex only: in pedigrees, close spellings ("Miznah" / "Marwan Al Shaqab") are different horses
                $sex = str_ends_with($path, 's') ? 'male' : 'female';
                $id = $this->findHorse($name, $sex, false);
                if (!$id) {
                    $reg = $this->v('pedigree', $r, 'registration');
                    $id = $this->externalHorse($name, $sex, 'pedigree', (string) $oldHorse, 'pedigree of ' . $horseName, [
                        'origin_country' => $country, 'registration_no' => $reg !== null ? mb_substr((string) $reg, 0, 60) : null,
                        'color_id' => $this->lookup('color', $this->v('pedigree', $r, 'color')),
                    ]);
                    $added++;
                }
                $resolved[$path] = $id;
            }
            foreach ($edges as $child => [$sirePath, $damPath]) {
                if (!isset($resolved[$child])) {
                    continue;
                }
                $c = DB::row('SELECT name_en, sire_id, dam_id FROM horses WHERE id = ?', [$resolved[$child]]);
                foreach (['sire_id' => $sirePath, 'dam_id' => $damPath] as $col => $p) {
                    $pid = $resolved[$p] ?? null;
                    if (!$pid || (int) $c[$col] === $pid) {
                        continue;
                    }
                    $role = $col === 'sire_id' ? 'sire' : 'dam';
                    if ($c[$col]) {
                        $this->rep('pedigree', $oldHorse, $horseId, 'warning', "$horseName: the old pedigree gives \"" . $nameOf($pid) . "\" as $role of \"{$c['name_en']}\", which already has \"" . $nameOf((int) $c[$col]) . '"; kept — check');
                    } elseif (HorseService::isSelfOrDescendant($resolved[$child], $pid)) {
                        $this->rep('pedigree', $oldHorse, $horseId, 'warning', "$horseName: \"" . $nameOf($pid) . "\" cannot be the $role of \"{$c['name_en']}\" (it would make a loop); left empty — check");
                    } else {
                        DB::update('horses', [$col => $pid], 'id = :id', ['id' => $resolved[$child]]);
                    }
                }
            }
            $this->counts['pedigree'] = ($this->counts['pedigree'] ?? 0) + 1;
            $this->rep('pedigree', $oldHorse, $horseId, 'linked', "$horseName: pedigree, " . (count($resolved) - 1) . ' of 14 ancestors' . ($added ? " ($added added as external horses)" : ''));
        }
    }

    /**
     * Mares marked pregnant in the old system → breeding records with the covering and due dates and the stallion named
     * in the notes ("Pregnant from LAMAR BHM"). The old system did not record how the mare was covered.
     */
    private function pregnancies(): void
    {
        foreach ($this->pregnant as [$mareId, $oid, $name, $covered, $due, $notes]) {
            $stallion = preg_match('/\bpregnant\s+(?:from|by|to|with)\s+([^\n\r.,;()]+)/i', $notes, $m) ? trim($m[1]) : null;
            $stallionId = null;
            if ($stallion) {
                $stallionId = $this->findHorse($stallion, 'male', false) ?? $this->externalHorse($stallion, 'male', 'breeding', $oid, 'stallion that covered ' . $name);
            }
            $start = $covered ?? ($due ? date('Y-m-d', strtotime($due . ' -340 days')) : null);
            if (!$start) {
                $this->rep('breeding', $oid, $mareId, 'warning', "$name was marked pregnant without any date; add the covering in Breeding & pregnancies");
                continue;
            }
            $expected = $due ?? date('Y-m-d', strtotime($start . ' +340 days'));
            $id = DB::insert('breeding_records', [
                'mare_id' => $mareId, 'stallion_id' => $stallionId, 'method' => 'natural', 'start_date' => $start, 'status' => 'pregnant', 'expected_foaling_date' => $expected,
                'notes' => 'Imported from the old system (marked pregnant' . ($stallion ? ' from ' . $stallion : '') . '). The old system did not record the covering method; check it.',
            ]);
            $this->counts['breeding'] = ($this->counts['breeding'] ?? 0) + 1;
            $this->rep('breeding', $oid, $id, 'imported', "$name: pregnant" . ($stallion ? " from $stallion" : ', stallion not recorded') . ", covered $start" . ($covered ? '' : ' (estimated from the due date)')
                . ", due $expected. Saved as natural cover because the old system did not record the method — correct it if needed");
        }
    }

    // ------------------------------------------------------------------ embryos

    private function embryoLocation(?string $field, ?string $notes): array
    {
        if ($field) {
            return [$field, false];
        }
        $n = strtolower((string) $notes);
        if (preg_match('/stable\s*(\d+)/', $n, $m)) {
            return ['Stable ' . $m[1], true];
        }
        if (str_contains($n, 'tank') || str_contains($n, 'nitrogen')) {
            return ['Nitrogen Tank', true];
        }
        if (str_contains($n, 'clinic')) {
            return ['Clinic', true];
        }
        return [null, false];
    }

    private function embryos(): void
    {
        foreach ($this->rows('embryos') as $r) {
            $oid = $this->oldId('embryos', $r);
            if ($this->already('embryos', $oid)) {
                continue;
            }
            $oldCode = (string) $this->v('embryos', $r, 'code');
            $flush = self::date($this->v('embryos', $r, 'flush'));
            $code = preg_match('/^EMB-\d{4}-\d{3}$/', $oldCode) && !DB::value('SELECT id FROM embryos WHERE code = ?', [$oldCode]) ? $oldCode : Sequence::embryo($flush);
            $label = $oldCode ?: $code;
            $ids = [];
            foreach (['dam' => 'female', 'sire' => 'male'] as $p => $sex) {
                $byId = $this->v('embryos', $r, $p . '_id');
                $pid = $byId !== null ? $this->already('horses', (string) $byId) : null;
                $pname = $this->v('embryos', $r, $p);
                if (!$pid && $pname) {
                    $pid = $this->findHorse($pname, $sex);
                    $real = $pid ? (string) DB::value('SELECT name_en FROM horses WHERE id = ?', [$pid]) : null;
                    if ($pid && strcasecmp($real, $pname) !== 0) {
                        $this->rep('embryos', $oid, null, $this->fuzzy ? 'warning' : 'linked', "$label: " . ($p === 'dam' ? 'donor mare' : 'sire') . " \"$pname\" → $real" . ($this->fuzzy ? ' (similar name — check this is the right mare)' : ' (same name, different spelling)'));
                    }
                    $pid ??= $this->externalHorse($pname, $sex, 'embryos', $oid, ($p === 'dam' ? 'donor mare' : 'sire') . ' of embryo ' . $label);
                }
                if (!$pid) {
                    $pid = $this->externalHorse('UNKNOWN ' . ($p === 'dam' ? 'DAM' : 'SIRE') . ' ' . $label, $sex, 'embryos', $oid, 'the old record had no ' . $p);
                    $this->rep('embryos', $oid, null, 'warning', "$label had no " . ($p === 'dam' ? 'donor mare' : 'sire') . '. A placeholder was added: open the embryo and choose the right horse');
                }
                $ids[$p] = $pid;
            }
            $notes = $this->v('embryos', $r, 'notes');
            [$loc, $fromNotes] = $this->embryoLocation($this->v('embryos', $r, 'location'), $notes);
            if ($fromNotes) {
                $this->rep('embryos', $oid, null, 'fixed', "$label: storage location \"$loc\" taken from the notes (\"$notes\")");
            }
            $expected = self::date($this->v('embryos', $r, 'expected'));
            $status = strtolower((string) $this->v('embryos', $r, 'status'));
            $status = match (true) {
                (bool) preg_match('/froz|stor/', $status) => 'frozen',
                (bool) preg_match('/transf/', $status) => 'transferred',
                (bool) preg_match('/preg/', $status) => 'pregnant',
                (bool) preg_match('/foal|born/', $status) => 'foaled',
                (bool) preg_match('/fail|lost|dead/', $status) => 'failed',
                default => 'fresh',
            };
            $recName = $this->v('embryos', $r, 'recipient');
            $recId = $this->v('embryos', $r, 'recipient_id') !== null ? $this->already('horses', (string) $this->v('embryos', $r, 'recipient_id')) : null;
            if (!$recId && $recName) {
                $recId = $this->findHorse($recName) ?? $this->externalHorse($recName, 'female', 'embryos', $oid, 'recipient mare of ' . $label);
            }
            [$ownerType, $ownerParty] = $this->owner($this->v('embryos', $r, 'owner'), 'embryos', $oid, $label);
            $id = DB::insert('embryos', [
                'code' => $code, 'name' => $this->v('embryos', $r, 'name'), 'donor_mare_id' => $ids['dam'], 'sire_id' => $ids['sire'], 'owner_type' => $ownerType, 'owner_party_id' => $ownerParty,
                'flush_date' => $flush, 'grade' => $this->v('embryos', $r, 'grade'), 'stage' => $this->v('embryos', $r, 'stage'), 'status' => $status,
                'location_id' => $this->lookup('location', $loc), 'recipient_mare_id' => $recId, 'transfer_date' => self::date($this->v('embryos', $r, 'transfer')),
                'expected_foaling_date' => $expected,
                'notes' => trim(implode("\n", array_filter([$notes, $code !== $oldCode && $oldCode !== '' ? 'Old code: ' . $oldCode : null, $this->leftovers('embryos', $r)]))) ?: null,
            ]);
            $this->remember('embryos', $oid, $id);
            if ($code !== $oldCode) {
                $this->rep('embryos', $oid, $id, 'fixed', "Code $label → $code");
            }
            if ($expected) {
                $this->rep('embryos', $oid, $id, 'fixed', "$code: \"Date of Birth\" $expected moved to Expected Foaling");
            }
            if ($recId && in_array($status, ['pregnant', 'transferred'], true) && $expected) {
                DB::run("INSERT INTO breeding_records (mare_id, stallion_id, embryo_id, method, start_date, status, expected_foaling_date, created_by)
                    SELECT ?, ?, ?, 'embryo_transfer', ?, 'pregnant', ?, NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM breeding_records WHERE embryo_id = ?)",
                    [$recId, $ids['sire'], $id, $flush ?? date('Y-m-d'), $expected, $id]);
            }
        }
        // Spellings close enough to be one horse but not to merge automatically: same sex only, compared without the words
        // both names share ("BADRAH AL SHAQAB" and "NIMRA AL SHAQAB" differ in BADRAH / NIMRA, so they are not similar).
        // The UNKNOWN placeholders are meant to differ.
        $horses = DB::all("SELECT id, name_en, sex FROM horses WHERE deleted_at IS NULL AND name_en NOT LIKE 'UNKNOWN %'");
        $words = array_map(fn ($h) => explode(' ', self::key($h['name_en'])), $horses);
        foreach ($horses as $i => $x) {
            for ($j = $i + 1; $j < count($horses); $j++) {
                if ($x['sex'] !== $horses[$j]['sex']) {
                    continue;
                }
                [$a, $b] = self::differingWords($words[$i], $words[$j]);
                $short = min(strlen($a), strlen($b));
                $d = levenshtein($a, $b);
                if ($short >= 3 && $d >= 1 && $d <= ($short >= 6 ? 2 : 1)) {
                    $this->rep('horses', null, (int) $x['id'], 'warning', "\"{$x['name_en']}\" and \"{$horses[$j]['name_en']}\" have similar names. If they are the same horse, keep one and archive the other");
                }
            }
        }
    }

    /** Two names' spelling-key words without the words they share at the start and at the end, as strings. */
    private static function differingWords(array $wa, array $wb): array
    {
        while (count($wa) > 1 && count($wb) > 1 && end($wa) === end($wb)) {
            array_pop($wa);
            array_pop($wb);
        }
        while (count($wa) > 1 && count($wb) > 1 && $wa[0] === $wb[0]) {
            array_shift($wa);
            array_shift($wb);
        }
        return [implode(' ', $wa), implode(' ', $wb)];
    }

    // ------------------------------------------------------------------ employees

    private function employees(): void
    {
        foreach ($this->rows('employees') as $r) {
            $oid = $this->oldId('employees', $r);
            if ($this->already('employees', $oid)) {
                continue;
            }
            $name = (string) $this->v('employees', $r, 'name');
            if ($name === '') {
                continue;
            }
            $existing = DB::value('SELECT id FROM employees WHERE LOWER(name_en) = ? AND deleted_at IS NULL', [mb_strtolower($name)]);
            if ($existing) {
                $this->remember('employees', $oid, (int) $existing, false);
                $this->rep('employees', $oid, (int) $existing, 'merged', "$name already exists here; linked, not copied");
                continue;
            }
            $position = $this->v('employees', $r, 'position');
            $phone = $this->v('employees', $r, 'phone');
            if ($position !== null && preg_match('/^[+\d\s\-()]+$/', $position) && strlen(preg_replace('/\D/', '', $position)) >= 7) {
                $this->rep('employees', $oid, null, 'fixed', "$name: phone number \"$position\" was in the Position field; moved to Phone" . ($phone ? ' (the phone field already had ' . $phone . '; the number was kept in notes)' : ''));
                $extra = $phone ? 'Other phone: ' . $position : null;
                $phone ??= $position;
                $position = null;
            }
            $status = strtolower((string) $this->v('employees', $r, 'status'));
            $status = match (true) {
                (bool) preg_match('/left|resign|terminat|former|inactive/', $status) => 'left',
                (bool) preg_match('/leave|vacation/', $status) => 'on_leave',
                (bool) preg_match('/suspend/', $status) => 'suspended',
                default => 'active',
            };
            // Pay in QAR: the old portal kept salaries in another currency (its USD base) when salary_currency says so
            $payCur = strtoupper(trim((string) ($this->v('employees', $r, 'salary_currency') ?? 'QAR')));
            $pay = [];
            foreach (['salary' => ['basic_salary_qar', 'salary'], 'housing' => ['housing_allowance', 'housing allowance'],
                      'transport' => ['transport_allowance', 'transport allowance'], 'allowances' => ['other_allowance', 'other allowance']] as $f => [$col, $what]) {
                $amount = self::num($this->v('employees', $r, $f));
                $pay[$col] = $amount;
                if ($amount === null || $amount <= 0 || $payCur === 'QAR') {
                    continue;
                }
                $conv = $this->moneyToQar($payCur, $amount);
                if ($conv) {
                    $pay[$col] = $conv[0];
                    $this->rep('employees', $oid, null, 'converted', "$name: $what " . $conv[2] . ' = ' . number_format($conv[0], 2) . ' QAR');
                } else {
                    $this->rep('employees', $oid, null, 'warning', "$name: $what $amount $payCur could not be converted (unknown currency); entered as QAR — correct it");
                }
            }
            $id = DB::insert('employees', [
                'emp_no' => HrService::nextEmpNo(), 'name_en' => mb_substr($name, 0, 120), 'name_ar' => $this->v('employees', $r, 'name_ar'),
                'position_id' => $this->lookup('position', $position), 'department_id' => $this->lookup('department', $this->v('employees', $r, 'department')),
                'nationality' => $this->countryCode($this->v('employees', $r, 'nationality'), 'employees', $oid), 'phone' => $phone !== null ? mb_substr($phone, 0, 30) : null,
                'email' => filter_var($this->v('employees', $r, 'email'), FILTER_VALIDATE_EMAIL) ?: null, 'hire_date' => self::date($this->v('employees', $r, 'hire')), 'status' => $status,
                'basic_salary_qar' => $pay['basic_salary_qar'], 'housing_allowance' => $pay['housing_allowance'],
                'transport_allowance' => $pay['transport_allowance'], 'other_allowance' => $pay['other_allowance'],
                'bank_name' => Crypto::encrypt($this->v('employees', $r, 'bank')), 'bank_iban' => Crypto::encrypt($this->v('employees', $r, 'iban')),
                'qid_no' => Crypto::encrypt($this->v('employees', $r, 'qid')), 'qid_expiry' => self::date($this->v('employees', $r, 'qid_expiry')),
                'passport_no' => Crypto::encrypt($this->v('employees', $r, 'passport')), 'passport_expiry' => self::date($this->v('employees', $r, 'passport_expiry')),
                'visa_expiry' => self::date($this->v('employees', $r, 'visa_expiry')), 'health_card_expiry' => self::date($this->v('employees', $r, 'health_expiry')),
                'show_on_website' => in_array(strtolower((string) $this->v('employees', $r, 'website')), ['1', 'yes', 'true'], true) ? 1 : 0,
                'notes' => trim(implode("\n", array_filter([$this->v('employees', $r, 'notes'), $extra ?? null, $this->leftovers('employees', $r)]))) ?: null,
            ]);
            unset($extra);
            $this->remember('employees', $oid, $id);
            $exp = self::date($this->v('employees', $r, 'qid_expiry'));
            if ($exp && $exp < date('Y-m-d') && $status !== 'left') {
                $this->rep('employees', $oid, $id, 'warning', "$name: Qatar ID expired on $exp");
            }
        }
    }

    // ------------------------------------------------------------------ horse history (old separate tables)

    /** Type, title and details of one old farrier / vet / vaccination row as a Health record; $used = columns already said. */
    private function healthText(string $entity, array $r, array &$used): array
    {
        if ($entity === 'farrier') {
            $service = strtolower((string) $this->v('farrier', $r, 'service'));
            $shoe = $this->v('farrier', $r, 'shoe');
            $feet = [];
            foreach (['front_left' => 'front left', 'front_right' => 'front right', 'rear_left' => 'hind left', 'rear_right' => 'hind right'] as $col => $foot) {
                if (array_key_exists($col, $r)) {
                    $used[] = $col;
                    if ((int) $r[$col] === 1) {
                        $feet[] = $foot;
                    }
                }
            }
            $what = ['trim' => 'Trim', 'new_shoes' => 'New shoes', 'reset' => 'Shoes reset', 'remove' => 'Shoes removed'][$service] ?? 'Farrier visit';
            return ['farrier', $what . ($shoe ? " ($shoe)" : ''), $feet ? 'Feet: ' . implode(', ', $feet) : ''];
        }
        if ($entity === 'medical') {
            $lines = [];
            foreach (['treatment' => 'Treatment', 'prescription' => 'Prescription'] as $f => $label) {
                if (($t = $this->v('medical', $r, $f)) !== null) {
                    $lines[] = "$label: $t";
                }
            }
            return ['vet_visit', (string) ($this->v('medical', $r, 'diagnosis') ?? 'Vet visit'), implode("\n", $lines)];
        }
        $type = match (strtolower((string) $this->v('vaccinations', $r, 'type'))) {
            'deworming' => 'deworming', 'medication' => 'medicine', 'supplement' => 'other', default => 'vaccination',
        };
        $dose = $this->v('vaccinations', $r, 'dosage');
        $batch = $this->v('vaccinations', $r, 'batch');
        return [$type, (string) ($this->v('vaccinations', $r, 'name') ?? 'Vaccination') . ($dose ? " — $dose" : ''), $batch ? "Batch: $batch" : ''];
    }

    /**
     * Horse history the old portal kept in separate tables: farrier, vet and vaccination rows → Health records (costs in
     * QAR), training sessions → Training logs, the latest measured height → the horse. Feeding rows are not imported:
     * the new diet log needs a stock item for every feed.
     */
    private function history(): void
    {
        $costCur = $this->oldQar ? 'USD' : 'QAR'; // the old portal kept every amount in its USD base
        $horseOf = function (string $entity, array $r, string $oid): ?int {
            $old = (string) $this->v($entity, $r, 'horse_id');
            $id = $old !== '' ? $this->already('horses', $old) : null;
            if (!$id) {
                $this->rep($entity, $oid, null, 'warning', "Not imported: its horse #$old is not in the old horse list");
            }
            return $id;
        };
        foreach (['farrier', 'medical', 'vaccinations'] as $entity) {
            foreach ($this->rows($entity) as $r) {
                $oid = $this->oldId($entity, $r);
                if ($this->already($entity, $oid) || !($horseId = $horseOf($entity, $r, $oid))) {
                    continue;
                }
                $date = self::date($this->v($entity, $r, 'date'));
                if (!$date) {
                    $this->rep($entity, $oid, null, 'warning', 'Not imported: no date');
                    continue;
                }
                $used = [];
                [$type, $title, $details] = $this->healthText($entity, $r, $used);
                $cost = self::num($this->v($entity, $r, 'cost'));
                $costQar = null;
                if ($cost !== null && $cost > 0) {
                    $conv = $this->moneyToQar($costCur, $cost);
                    $costQar = $conv[0] ?? null;
                    if ($conv && $conv[2]) {
                        $this->rep($entity, $oid, null, 'converted', DB::value('SELECT name_en FROM horses WHERE id = ?', [$horseId]) . " $date, $title: cost " . $conv[2] . ' = ' . number_format($conv[0], 2) . ' QAR');
                    }
                }
                $by = $this->v($entity, $r, 'by');
                $id = DB::insert('health_records', [
                    'horse_id' => $horseId, 'type' => $type, 'record_date' => $date, 'title' => mb_substr($title, 0, 150),
                    'details' => trim(implode("\n", array_filter([$details, $this->v($entity, $r, 'notes'), $this->leftovers($entity, $r, $used)]))) ?: null,
                    'vet_name' => $by !== null ? mb_substr((string) $by, 0, 120) : null, 'cost_qar' => $costQar, 'next_due_date' => self::date($this->v($entity, $r, 'next')),
                ]);
                $this->remember($entity, $oid, $id);
            }
        }
        foreach ($this->rows('training') as $r) {
            $oid = $this->oldId('training', $r);
            if ($this->already('training', $oid) || !($horseId = $horseOf('training', $r, $oid))) {
                continue;
            }
            $date = self::date($this->v('training', $r, 'date'));
            if (!$date) {
                $this->rep('training', $oid, null, 'warning', 'Not imported: no date');
                continue;
            }
            $trainer = $this->v('training', $r, 'trainer');
            $emp = $trainer !== null ? DB::value('SELECT id FROM employees WHERE LOWER(name_en) = ? AND deleted_at IS NULL', [mb_strtolower((string) $trainer)]) : null;
            $rating = $this->v('training', $r, 'rating');
            $km = $this->v('training', $r, 'distance');
            $notes = array_filter([
                $trainer !== null && !$emp ? "Trainer: $trainer" : null, ($i = $this->v('training', $r, 'intensity')) !== null ? "Intensity: $i" : null,
                $rating !== null ? "Performance: $rating/5" : null, $km !== null ? "Distance: $km km" : null, $this->v('training', $r, 'notes'), $this->leftovers('training', $r),
            ]);
            $minutes = $this->v('training', $r, 'minutes');
            $id = DB::insert('training_logs', [
                'horse_id' => $horseId, 'log_date' => $date, 'trainer_employee_id' => $emp ?: null,
                'activity' => mb_substr((string) ($this->v('training', $r, 'session') ?? 'Training'), 0, 150),
                'duration_min' => $minutes !== null && (int) $minutes > 0 ? (int) $minutes : null, 'notes' => implode("\n", $notes) ?: null,
            ]);
            $this->remember('training', $oid, $id);
        }
        $height = [];
        foreach ($this->rows('measurements') as $r) {
            $cm = self::num($this->v('measurements', $r, 'height'));
            $d = self::date($this->v('measurements', $r, 'date')) ?? '';
            $h = (string) $this->v('measurements', $r, 'horse_id');
            if ($cm !== null && $cm >= 50 && $cm <= 250 && $d >= ($height[$h][0] ?? '')) {
                $height[$h] = [$d, (int) round($cm)];
            }
        }
        foreach ($height as $old => [$d, $cm]) {
            $horseId = $this->already('horses', (string) $old);
            if ($horseId && !DB::value('SELECT height_cm FROM horses WHERE id = ?', [$horseId])) {
                DB::update('horses', ['height_cm' => $cm], 'id = :id', ['id' => $horseId]);
                $this->rep('measurements', $old, $horseId, 'imported', DB::value('SELECT name_en FROM horses WHERE id = ?', [$horseId]) . ": height $cm cm (measured $d). Weight and other measurements are not kept by the new system");
            }
        }
    }

    // ------------------------------------------------------------------ categories & currencies

    private function categories(): void
    {
        $existing = DB::all('SELECT id, name_en, type, parent_id FROM finance_categories WHERE deleted_at IS NULL');
        $norm = fn (string $s) => preg_replace('/[^a-z]/', '', strtolower($s));
        foreach ($this->rows('categories') as $r) {
            $oid = $this->oldId('categories', $r);
            if ($this->already('categories', $oid)) {
                continue;
            }
            $name = (string) $this->v('categories', $r, 'name');
            $fixed = strtolower($name);
            foreach (self::CATEGORY_FIXES as $bad => $good) {
                $fixed = str_replace($bad, $good, $fixed);
            }
            $fixed = ucwords($fixed);
            // Exact name first (main categories before sub-categories), then small spelling differences on longer names
            usort($existing, fn ($x, $y) => ($x['parent_id'] !== null) <=> ($y['parent_id'] !== null));
            $b = $norm($fixed);
            $match = null;
            foreach ($existing as $c) {
                if ($norm($c['name_en']) === $b) {
                    $match = $c;
                    break;
                }
            }
            foreach ($match ? [] : $existing as $c) {
                $a = $norm($c['name_en']);
                $len = min(strlen($a), strlen($b));
                if (levenshtein($a, $b) <= ($len >= 10 ? 2 : ($len >= 6 ? 1 : 0))) {
                    $match = $c;
                    break;
                }
            }
            if ($match) {
                $this->remember('categories', $oid, (int) $match['id'], false);
                if (strcasecmp($match['name_en'], $name) !== 0) {
                    $this->rep('categories', $oid, (int) $match['id'], 'fixed', "\"$name\" → \"{$match['name_en']}\"");
                }
                continue;
            }
            $type = strtolower((string) $this->v('categories', $r, 'type'));
            $type = in_array($type, ['income', 'expense', 'liability'], true) ? $type : 'expense';
            $id = DB::insert('finance_categories', ['type' => $type, 'name_en' => mb_substr($fixed, 0, 100), 'sort' => 90]);
            $existing[] = ['id' => $id, 'name_en' => $fixed, 'type' => $type, 'parent_id' => null];
            $this->remember('categories', $oid, $id);
            $this->rep('categories', $oid, $id, 'created', "New category \"$fixed\"" . ($fixed !== $name ? " (was \"$name\")" : ''));
        }
    }

    /** How to read an old exchange rate: as "1 unit = X QAR", as "1 unit = X USD", or not at all. */
    private function rateKind(string $code, float $old): ?string
    {
        if ($old <= 0) {
            return null;
        }
        $q = $code === 'QAR' ? 1.0 : (float) (DB::value('SELECT rate_to_qar FROM currencies WHERE code = ?', [$code]) ?: 0);
        if ($q <= 0) {
            return null;
        }
        if (abs($old - $q) / $q < 0.15) {
            return 'qar';
        }
        if (abs($old - $q / 3.64) / ($q / 3.64) < 0.15) {
            return 'usd';
        }
        return null;
    }

    private array $oldRates = [];

    /** The old system's exchange rates, read once before anything needs them (salaries and history costs come before bills). */
    private function loadOldRates(): void
    {
        foreach ($this->rows('currencies') as $r) {
            $code = strtoupper((string) $this->v('currencies', $r, 'code'));
            $rate = (float) self::num($this->v('currencies', $r, 'rate'));
            if (strlen($code) === 3 && $rate > 0) {
                $this->oldRates[$code] = $rate;
            }
        }
        // A USD base with 1 QAR worth about 0.27 USD (the old portal: 0.2745): the rate it showed every QAR amount with
        $q = $this->oldRates['QAR'] ?? 0.0;
        if (abs(($this->oldRates['USD'] ?? 0.0) - 1.0) < 0.000001 && $q > 0.25 && $q < 0.30) {
            $this->oldQar = $q;
        }
    }

    /**
     * An old amount in $cur as QAR: [qar, rate, how] (how = null when nothing was converted), or null for an unknown
     * currency. USD, the old base, goes through the old system's own QAR rate, so amounts come out as the QAR figures
     * the old system showed (1,800.00 rather than 1,798.52); without that rate, the 3.64 peg.
     */
    private function moneyToQar(string $cur, float $amount): ?array
    {
        $cur = strtoupper($cur);
        if ($cur === 'QAR' || $cur === '') {
            return [round($amount, 2), 1.0, null];
        }
        if ($cur === 'USD') {
            return $this->oldQar
                ? [round($amount / $this->oldQar, 2), round(1 / $this->oldQar, 6), "$amount USD ÷ {$this->oldQar} (the old system's QAR rate)"]
                : [round($amount * 3.64, 2), 3.64, "$amount USD × 3.64"];
        }
        if ($this->oldQar && !empty($this->oldRates[$cur])) {
            $rate = round($this->oldRates[$cur] / $this->oldQar, 6);
            return [round($amount * $rate, 2), $rate, "$amount $cur × $rate (the old system's rates)"];
        }
        $rate = (float) (DB::value('SELECT rate_to_qar FROM currencies WHERE code = ?', [$cur]) ?: 0);
        return $rate > 0 ? [round($amount * $rate, 2), $rate, "$amount $cur × $rate (today's rate)"] : null;
    }

    private function currencies(): void
    {
        foreach ($this->rows('currencies') as $r) {
            $code = strtoupper((string) $this->v('currencies', $r, 'code'));
            $old = (float) self::num($this->v('currencies', $r, 'rate'));
            if (strlen($code) !== 3) {
                continue;
            }
            $this->oldRates[$code] = $old;
            $correct = $code === 'QAR' ? 1.0 : DB::value('SELECT rate_to_qar FROM currencies WHERE code = ?', [$code]);
            if ($correct === null) {
                $this->rep('currencies', $code, null, 'warning', "$code is not set up in the new system; add it in Settings → Currencies if it is still used");
                continue;
            }
            $kind = $this->rateKind($code, $old);
            $this->rep('currencies', $code, null, 'fixed', match ($kind) {
                'usd' => "Old rate 1 $code = $old USD (correct). New base is QAR: 1 $code = " . round((float) $correct, 6) . ' QAR',
                'qar' => "Old rate 1 $code = $old was stored as a USD rate but it is a QAR value (wrong). Now 1 $code = " . round((float) $correct, 6) . ' QAR',
                default => "Old rate 1 $code = $old does not match either base. Now 1 $code = " . round((float) $correct, 6) . ' QAR',
            });
        }
        if (isset($this->found['currencies'])) {
            $this->rep('currencies', null, null, 'skipped', 'The old conversion history is not copied: it used USD as the base with wrong values. Each imported bill keeps the rate used for its QAR amount.');
        }
    }

    // ------------------------------------------------------------------ bills

    private function party(?string $name, string $type): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }
        $clean = fn (string $s) => trim(preg_replace('/\b(co|company|llc|wll|w l l|est|establishment|trading|for|and)\b/i', '', preg_replace('/[^a-z0-9 ]/i', ' ', strtolower($s))));
        foreach (DB::all('SELECT id, name_en, type FROM parties WHERE deleted_at IS NULL') as $p) {
            if ($clean($p['name_en']) === $clean($name) || self::similar($clean($p['name_en']), $clean($name))) {
                if ($p['type'] !== $type && $p['type'] !== 'both') {
                    DB::update('parties', ['type' => 'both'], 'id = :id', ['id' => $p['id']]);
                }
                if (strcasecmp($p['name_en'], $name) !== 0 && !isset($this->map['party_alias'][$name])) {
                    $this->map['party_alias'][$name] = 1;
                    $this->rep('parties', $name, (int) $p['id'], 'merged', "\"$name\" → \"{$p['name_en']}\"");
                }
                return (int) $p['id'];
            }
        }
        $id = DB::insert('parties', ['type' => $type, 'name_en' => mb_substr($name, 0, 150), 'notes' => 'Added by the import from bill names']);
        $this->counts['parties'] = ($this->counts['parties'] ?? 0) + 1;
        return $id;
    }

    private function accounts(): void
    {
        $this->cashAccount = (int) DB::value("SELECT id FROM accounts WHERE type = 'cash' AND deleted_at IS NULL ORDER BY id LIMIT 1") ?: null;
        $this->bankAccount = (int) DB::value("SELECT id FROM accounts WHERE type = 'bank' AND deleted_at IS NULL ORDER BY id LIMIT 1") ?: null;
        if (!$this->cashAccount) {
            $this->cashAccount = DB::insert('accounts', ['name' => 'Cash', 'type' => 'cash', 'opening_balance_qar' => 0]);
            $this->rep('accounts', null, $this->cashAccount, 'created', 'Account "Cash" created for imported cash payments. Set its opening balance in Finance → Accounts');
        }
        if (!$this->bankAccount) {
            $this->bankAccount = DB::insert('accounts', ['name' => 'Bank', 'type' => 'bank', 'opening_balance_qar' => 0]);
            $this->rep('accounts', null, $this->bankAccount, 'created', 'Account "Bank" created for imported bank / cheque payments. Set its name and opening balance in Finance → Accounts');
        }
    }

    /** QAR value of an old bill and how it was worked out. */
    private function toQar(array $r): array
    {
        $cur = strtoupper((string) ($this->v('bills', $r, 'currency') ?? 'QAR'));
        $amount = self::num($this->v('bills', $r, 'amount'));
        $usd = self::num($this->v('bills', $r, 'amount_usd'));
        $qar = self::num($this->v('bills', $r, 'amount_qar'));
        $oldRate = self::num($this->v('bills', $r, 'rate')) ?? ($this->oldRates[$cur] ?? null);
        $correct = $cur === 'QAR' ? 1.0 : (float) (DB::value('SELECT rate_to_qar FROM currencies WHERE code = ?', [$cur]) ?: 0);
        if (!$correct) {
            $cur = 'QAR';
            $correct = 1.0;
        }
        if ($qar !== null && $qar > 0) {
            return [$cur, $amount ?? $qar, $amount ? round($qar / $amount, 6) : 1.0, round($qar, 2), null];
        }
        if ($amount !== null && $cur === 'QAR') {
            return ['QAR', $amount, 1.0, round($amount, 2), abs($amount - round($amount, 2)) > 0.0001 ? "rounded $amount → " . round($amount, 2) : null];
        }
        if ($amount !== null && $cur === 'USD') {
            [$q, $rate, $how] = $this->moneyToQar('USD', $amount);
            return ['USD', $amount, $rate, $q, $how];
        }
        if ($amount !== null) {
            $kind = $oldRate ? $this->rateKind($cur, (float) $oldRate) : null;
            $rate = match ($kind) {
                'qar' => (float) $oldRate,             // the saved rate was really a QAR rate
                'usd' => $this->oldQar ? round((float) $oldRate / $this->oldQar, 6) : round((float) $oldRate * 3.64, 6),
                default => $correct,
            };
            return [$cur, $amount, $rate, round($amount * $rate, 2), "$amount $cur × $rate" . ($kind === null ? ' (old rate unusable; current rate used)' : ' (rate saved at the time' . ($kind === 'usd' ? ', converted from USD' : '') . ')')];
        }
        if ($usd !== null) {
            [$q, $rate, $how] = $this->moneyToQar('USD', $usd);
            return ['USD', $usd, $rate, $q, 'stored ' . $how];
        }
        return ['QAR', 0.0, 1.0, 0.0, 'no amount found'];
    }

    private function bills(): void
    {
        $rows = $this->rows('bills');
        if (!$rows) {
            return;
        }
        $this->accounts();
        $catNames = [];
        foreach ($this->rows('categories') as $c) {
            $catNames[(string) $this->v('categories', $c, 'id')] = (string) $this->v('categories', $c, 'name');
        }
        $missingHorse = [];
        foreach ($rows as $r) {
            $oid = $this->oldId('bills', $r);
            if ($this->already('bills', $oid)) {
                continue;
            }
            $date = self::date($this->v('bills', $r, 'date')) ?? date('Y-m-d');
            $typeRaw = strtolower((string) $this->v('bills', $r, 'type'));
            $type = match (true) {
                (bool) preg_match('/income|revenue|sale|receipt/', $typeRaw) => 'income',
                (bool) preg_match('/liab|loan|payable|fund/', $typeRaw) => 'liability',
                default => 'expense',
            };
            $catId = null;
            $oldCat = $this->v('bills', $r, 'category_id');
            if ($oldCat !== null) {
                $catId = $this->already('categories', (string) $oldCat);
            }
            if (!$catId && ($cn = $this->v('bills', $r, 'category') ?? ($catNames[(string) $oldCat] ?? null))) {
                foreach (DB::all('SELECT id, name_en FROM finance_categories WHERE deleted_at IS NULL') as $c) {
                    if (self::similar($c['name_en'], $cn)) {
                        $catId = (int) $c['id'];
                        break;
                    }
                }
            }
            [$cur, $orig, $rate, $qar, $how] = $this->toQar($r);
            $statusRaw = strtolower((string) $this->v('bills', $r, 'status'));
            $paidAmount = self::num($this->v('bills', $r, 'paid'));
            $status = match (true) {
                (bool) preg_match('/cancel|void|reject/', $statusRaw) => 'cancelled',
                (bool) preg_match('/draft/', $statusRaw) => 'draft',
                (bool) preg_match('/pend|wait|review/', $statusRaw) && $type !== 'income' && $qar > FinanceService::limit() => 'pending', // same rule whoever runs the import
                default => 'approved',
            };
            $horseId = null;
            if (($oh = $this->v('bills', $r, 'horse_id')) !== null && (int) $oh > 0) {
                $horseId = $this->already('horses', (string) $oh);
                if (!$horseId) {
                    $missingHorse[(string) $oh][] = $this->v('bills', $r, 'number') ?? '#' . $oid;
                }
            } elseif ($hn = $this->v('bills', $r, 'horse')) {
                $horseId = $this->findHorse($hn);
            }
            $method = $this->v('bills', $r, 'method');
            $oldNo = $this->v('bills', $r, 'number');
            $id = DB::insert('bills', [
                'number' => Sequence::bill($date), 'type' => $type, 'category_id' => $catId, 'party_id' => $this->party($this->v('bills', $r, 'party'), $type === 'income' ? 'client' : 'supplier'),
                'description' => mb_substr((string) ($this->v('bills', $r, 'description') ?? ''), 0, 255) ?: null, 'currency' => $cur, 'exchange_rate' => $rate,
                'amount_original' => round((float) $orig, 2), 'amount_qar' => $qar, 'paid_qar' => 0, 'bill_date' => $date, 'due_date' => self::date($this->v('bills', $r, 'due')),
                'payment_method_id' => $this->lookup('payment_method', $method), 'reference_no' => $oldNo ? mb_substr((string) $oldNo, 0, 80) : 'OLD-' . $oid,
                'status' => $status, 'horse_id' => $horseId, 'approved_at' => in_array($status, ['approved'], true) ? $date . ' 00:00:00' : null,
                'notes' => trim('Imported from the old system' . ($how ? ' — ' . $how : '') . "\n" . $this->leftovers('bills', $r)),
                'created_by' => ($cb = $this->v('bills', $r, 'created_by')) !== null ? $this->already('users', (string) $cb) : null,
            ]);
            $this->remember('bills', $oid, $id);
            if ($how && $how !== 'no amount found') {
                $this->rep('bills', $oid, $id, 'converted', ($oldNo ?? '#' . $oid) . ': ' . $how . ' = ' . number_format($qar, 2) . ' QAR');
            } elseif ($how) {
                $this->rep('bills', $oid, $id, 'warning', ($oldNo ?? '#' . $oid) . ': no amount found');
            }
            if ($status === 'pending') {
                $this->approval('bill', 'bill', $id, FinanceService::title(DB::row('SELECT * FROM bills WHERE id = ?', [$id])), $qar);
            }
            // Payments: paid in full, or the paid amount the old system recorded
            $pay = preg_match('/^paid|complete|settled/', $statusRaw) ? $qar : ($paidAmount !== null && $paidAmount > 0 ? min($qar, round($paidAmount * ($qar / max(0.01, (float) $orig)), 2)) : 0);
            if ($pay > 0 && $status !== 'cancelled') {
                DB::insert('bill_payments', [
                    'bill_id' => $id, 'payment_date' => self::date($this->v('bills', $r, 'paid_at')) ?? $date, 'amount_qar' => $pay,
                    'account_id' => preg_match('/bank|transfer|cheque|check|card/i', (string) $method) ? $this->bankAccount : $this->cashAccount,
                    'payment_method_id' => $this->lookup('payment_method', $method), 'notes' => 'Imported payment',
                ]);
                FinanceService::recalc($id);
            }
        }
        foreach ($missingHorse as $oh => $nos) {
            $this->rep('bills', null, null, 'warning', count($nos) . " bills point to horse #$oh, which does not exist in the old data; they were imported without a horse: " . implode(', ', $nos));
        }
    }

    // ------------------------------------------------------------------ inventory

    private function inventory(): void
    {
        foreach ($this->rows('inventories') as $r) {
            $oid = $this->oldId('inventories', $r);
            if ($this->already('inventories', $oid)) {
                continue;
            }
            $name = (string) $this->v('inventories', $r, 'name');
            $c = strtolower(($this->v('inventories', $r, 'category') ?? '') . ' ' . $name);
            $cat = match (true) {
                str_contains($c, 'clinic') || str_contains($c, 'medic') => 'clinic',
                str_contains($c, 'cosmet') => 'cosmetics',
                str_contains($c, 'equip') => 'equipment',
                str_contains($c, 'feed') || str_contains($c, 'food') => 'feed',
                str_contains($c, 'shav') || str_contains($c, 'groom') || str_contains($c, 'bedding') => 'grooming',
                str_contains($c, 'vitam') || str_contains($c, 'supplement') => 'vitamins',
                default => 'other',
            };
            $existing = DB::value('SELECT id FROM inventories WHERE LOWER(name_en) = ? AND deleted_at IS NULL', [mb_strtolower($name)]);
            $id = $existing ? (int) $existing : DB::insert('inventories', ['name_en' => mb_substr($name, 0, 100), 'category' => $cat]);
            $this->remember('inventories', $oid, $id, !$existing);
        }
        $fallback = null;
        foreach ($this->rows('items') as $r) {
            $oid = $this->oldId('items', $r);
            if ($this->already('items', $oid)) {
                continue;
            }
            $inv = ($o = $this->v('items', $r, 'inventory_id')) !== null ? $this->already('inventories', (string) $o) : null;
            if (!$inv) {
                $fallback ??= DB::insert('inventories', ['name_en' => 'Imported items', 'category' => 'other']);
                $inv = $fallback;
                $this->rep('items', $oid, null, 'warning', '"' . $this->v('items', $r, 'name') . '" had no inventory; placed in "Imported items"');
            }
            $qty = (float) (self::num($this->v('items', $r, 'quantity')) ?? 0);
            $price = round((float) (self::num($this->v('items', $r, 'price')) ?? 0), 2);
            $id = DB::insert('inventory_items', [
                'inventory_id' => $inv, 'name_en' => mb_substr((string) $this->v('items', $r, 'name'), 0, 150), 'unit' => mb_substr((string) ($this->v('items', $r, 'unit') ?? 'pcs'), 0, 30),
                'quantity' => $qty, 'unit_price_qar' => $price, 'min_quantity' => (float) (self::num($this->v('items', $r, 'min')) ?? 0),
                'expiry_date' => self::date($this->v('items', $r, 'expiry')), 'supplier_id' => $this->party($this->v('items', $r, 'supplier'), 'supplier'),
                'notes' => mb_substr($this->leftovers('items', $r), 0, 255) ?: null,
            ]);
            if ($qty > 0) {
                DB::insert('stock_movements', ['item_id' => $id, 'direction' => 'in', 'quantity' => $qty, 'unit_price_qar' => $price, 'total_qar' => round($qty * $price, 2),
                    'movement_date' => date('Y-m-d'), 'note' => 'Opening stock from the old system']);
            }
            $this->remember('items', $oid, $id);
        }
    }
}

/** Thrown at the end of a preview so the transaction is rolled back. */
final class PreviewRollback extends \RuntimeException
{
}
