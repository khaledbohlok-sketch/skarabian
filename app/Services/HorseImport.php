<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\DB;
use App\Core\Lang;
use App\Resources\Horses;

/**
 * Horses → Import from Excel (HorsesController::import).
 *
 * The user fills the template (or any sheet whose headings are recognisable, in English or Arabic), uploads it and sees
 * exactly what would happen: preview() is the real run inside a transaction that is rolled back. import() makes the same
 * run for real in one transaction (all or nothing); the controller makes a backup first.
 *
 * A row is matched to a horse already in the system by registration no., microchip, UELN or passport no., else by name
 * (English, or Arabic when the English name is not found; punctuation does not matter). A new horse is added. A horse
 * already in the system only gets its EMPTY fields filled: a different value in the file is listed, never written (an
 * owner or status changes through "Transfer ownership"), except for a horse known so far only from pedigrees, which
 * becomes one of the stud's horses. Sire and dam are linked to a horse in the file or in the system; an unknown one is
 * added as a pedigree-only horse. A row with a problem is skipped and listed. Every change is in the Activity log.
 */
final class HorseImport
{
    /** Uploaded files wait here (inside storage/, never served) between the preview and the import; the hourly clean-up removes them after a day. */
    public const DIR = 'imports';
    /** Rows a file may have (SheetReader refuses more). */
    public const MAX_ROWS = SheetReader::MAX_ROWS;

    /** The template's columns, in order: key => kind. */
    private const COLUMNS = [
        'name_en' => 'name', 'name_ar' => 'text', 'sex' => 'sex', 'dob' => 'date', 'category' => 'category', 'color' => 'lookup',
        'registration_no' => 'ident', 'microchip' => 'ident', 'ueln' => 'ident', 'passport_no' => 'ident', 'breeder' => 'text',
        'country' => 'country', 'owner' => 'owner', 'location' => 'lookup', 'status' => 'status', 'sire' => 'parent', 'dam' => 'parent',
        'born_at_sk' => 'bool', 'purchase_date' => 'date', 'purchase_price' => 'money', 'notes' => 'text',
    ];
    private const REQUIRED = ['name_en', 'sex'];
    /** The numbers that identify a horse, in the order they are trusted. */
    private const IDENTS = ['registration_no', 'microchip', 'ueln', 'passport_no'];
    private const MAX = ['name_en' => 120, 'name_ar' => 120, 'registration_no' => 60, 'microchip' => 60, 'ueln' => 40, 'passport_no' => 60, 'breeder' => 150, 'notes' => 5000, 'sire' => 120, 'dam' => 120];
    /** Other headings people use for the same column (the template's own headings, in both languages, are always known). */
    private const ALIASES = [
        'name_en' => ['name', 'horse', 'horse name', 'name en', 'english name', 'name english', 'الاسم', 'اسم الحصان', 'الاسم بالانجليزي', 'الاسم بالإنجليزية', 'الاسم انجليزي'],
        'name_ar' => ['arabic name', 'name ar', 'name arabic', 'الاسم العربي', 'الاسم بالعربي', 'الاسم بالعربية', 'الاسم عربي'],
        'sex' => ['gender', 'm/f', 'الجنس', 'النوع'],
        'dob' => ['dob', 'd.o.b', 'birth date', 'date of birth', 'born', 'birthday', 'foaling date', 'foaled', 'year of birth', 'birth year', 'تاريخ الميلاد', 'الميلاد', 'تاريخ الولادة', 'سنة الميلاد'],
        'category' => ['category', 'type', 'الفئة', 'التصنيف'],
        'color' => ['color', 'colour', 'coat', 'اللون'],
        'registration_no' => ['reg no', 'reg. no.', 'reg #', 'registration', 'registration no', 'registration number', 'reg number', 'رقم التسجيل', 'التسجيل', 'رقم القيد'],
        'microchip' => ['chip', 'chip no', 'microchip no', 'microchip number', 'الشريحة', 'رقم الشريحة', 'الشريحة الإلكترونية', 'الرقاقة'],
        'ueln' => ['ueln', 'ueln no', 'ueln number'],
        'passport_no' => ['passport', 'passport no', 'passport number', 'رقم الجواز', 'الجواز', 'رقم جواز السفر'],
        'breeder' => ['breeder', 'bred by', 'المربي'],
        'country' => ['country', 'country of birth', 'origin', 'country of origin', 'born in', 'البلد', 'بلد الولادة', 'بلد المنشأ', 'المنشأ'],
        'owner' => ['owner', 'owned by', 'المالك'],
        'location' => ['stable', 'location', 'stable / location', 'barn', 'الإسطبل', 'الاسطبل', 'الموقع'],
        'status' => ['status', 'الحالة'],
        'sire' => ['sire', 'father', 'الأب', 'الاب', 'الفحل'],
        'dam' => ['dam', 'mother', 'الأم', 'الام'],
        'born_at_sk' => ['born at sk', 'born here', 'home bred', 'homebred', 'bred at sk', 'مولود في sk', 'مولود في الإسطبل'],
        'purchase_date' => ['purchase date', 'date bought', 'bought on', 'تاريخ الشراء'],
        'purchase_price' => ['purchase price', 'price', 'cost', 'سعر الشراء', 'السعر'],
        'notes' => ['notes', 'note', 'comments', 'remarks', 'ملاحظات', 'ملاحظة'],
    ];
    /** Arabic letter forms treated as the same letter when comparing names and headings. */
    private const AR_FOLD = ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي', 'ـ' => ''];
    private const AR_MONTHS = [
        'كانون الثاني' => 'jan', 'كانون الأول' => 'dec', 'تشرين الأول' => 'oct', 'تشرين الثاني' => 'nov', 'يناير' => 'jan', 'فبراير' => 'feb', 'مارس' => 'mar',
        'أبريل' => 'apr', 'ابريل' => 'apr', 'إبريل' => 'apr', 'مايو' => 'may', 'يونيو' => 'jun', 'يونيه' => 'jun', 'يوليو' => 'jul', 'يوليه' => 'jul',
        'أغسطس' => 'aug', 'اغسطس' => 'aug', 'سبتمبر' => 'sep', 'أكتوبر' => 'oct', 'اكتوبر' => 'oct', 'نوفمبر' => 'nov', 'ديسمبر' => 'dec',
        'شباط' => 'feb', 'آذار' => 'mar', 'نيسان' => 'apr', 'أيار' => 'may', 'حزيران' => 'jun', 'تموز' => 'jul', 'آب' => 'aug', 'أيلول' => 'sep',
    ];

    private array $book;
    private array $opt;
    private bool $real;
    private ?int $by;
    private bool $canEdit;
    private bool $canSensitive;
    private bool $hasUeln;
    private bool $ownership;
    /** Horses in the system (and those this run adds): id => basic fields; and the indexes to find them. */
    private array $horses = [];
    private array $byIdent = [];
    private array $byName = [];
    private array $byNameAr = [];
    private ?array $compact = null;
    /** Horses of this file once they have an id: name key / registration key => id. */
    private array $fileName = [];
    private array $fileIdent = [];
    /** Duplicates inside the file: existing horse id / new name key / new number => row. */
    private array $seenId = [];
    private array $seenName = [];
    private array $seenIdent = [];
    private array $newCompact = [];
    private array $lookups = [];
    private ?array $countries = null;
    private ?array $clients = null;
    private int|false|null $breed = false;
    private array $totals = ['new' => 0, 'update' => 0, 'same' => 0, 'skip' => 0, 'ancestors' => 0, 'links' => 0, 'warnings' => 0];
    private static array $lists = [];

    private function __construct(array $book, array $opt, bool $real)
    {
        $this->book = $book;
        $this->opt = $opt;
        $this->real = $real;
        $this->by = Auth::id();
        $this->canEdit = Auth::can('horses', 'edit');
        $this->canSensitive = Auth::can('horses', 'sensitive');
        $this->hasUeln = DB::hasColumn('horses', 'ueln');
        $this->ownership = HorseService::ownershipAvailable();
    }

    // ------------------------------------------------------------------ the uploaded file

    /** Checks an upload and keeps it under storage/imports until the import: ['file', 'name', 'at'] for the session. */
    public static function store(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $mb = (int) (SheetReader::MAX_BYTES / 1_000_000);
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || (int) ($file['size'] ?? 0) > SheetReader::MAX_BYTES) {
            throw new \DomainException(__('sheet.too_big', ['mb' => $mb]));
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new \DomainException(__('files.upload_failed'));
        }
        $name = basename(str_replace('\\', '/', (string) ($file['name'] ?? 'file')));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === 'xls') {
            throw new \DomainException(__('sheet.old_xls'));
        }
        if (!in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            throw new \DomainException(__('sheet.wrong_type'));
        }
        $dir = Config::storagePath(self::DIR);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create ' . $dir);
        }
        $stored = 'horses-' . (int) Auth::id() . '-' . bin2hex(random_bytes(8)) . '.' . ($ext === 'xlsx' ? 'xlsx' : 'csv');
        $dest = $dir . '/' . $stored;
        $tmp = (string) ($file['tmp_name'] ?? '');
        // a real upload is moved; the command-line tests have no upload, so they hand over a file of their own
        $ok = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $dest) : (PHP_SAPI === 'cli' && is_file($tmp) && copy($tmp, $dest));
        if (!$ok) {
            throw new \DomainException(__('files.upload_failed'));
        }
        try {
            SheetReader::read($dest, $name);
        } catch (\Throwable $e) {
            @unlink($dest);
            throw $e;
        }
        return ['file' => $stored, 'name' => mb_substr($name, 0, 120), 'at' => time()];
    }

    /** The stored file of a session's upload (only the uploader's own), or null when it is gone. */
    public static function path(?array $state): ?string
    {
        $f = (string) ($state['file'] ?? '');
        if (!preg_match('/^horses-(\d+)-[0-9a-f]{16}\.(xlsx|csv)$/', $f, $m) || (int) $m[1] !== (int) Auth::id()) {
            return null;
        }
        $p = Config::storagePath(self::DIR . '/' . $f);
        return is_file($p) ? $p : null;
    }

    public static function discard(?array $state): void
    {
        if ($p = self::path($state)) {
            @unlink($p);
        }
    }

    /** Hourly clean-up: uploads older than a day (an import that was never finished). */
    public static function purgeOld(): int
    {
        $n = 0;
        foreach (glob(Config::storagePath(self::DIR) . '/horses-*') ?: [] as $f) {
            if (is_file($f) && filemtime($f) < time() - 86400 && @unlink($f)) {
                $n++;
            }
        }
        return $n;
    }

    // ------------------------------------------------------------------ preview and import

    /** What the import would do, changing nothing: the same run inside a transaction that is rolled back. */
    public static function preview(array $state, array $opt = []): array
    {
        $book = self::book($state);
        $pdo = DB::pdo();
        if ($pdo->inTransaction()) { // already inside a transaction (a test): undo to a savepoint instead
            $pdo->exec('SAVEPOINT horse_import_preview');
            try {
                return (new self($book, $opt, false))->run($state);
            } finally {
                $pdo->exec('ROLLBACK TO SAVEPOINT horse_import_preview');
            }
        }
        $report = [];
        try {
            DB::transaction(function () use (&$report, $book, $opt, $state) {
                $report = (new self($book, $opt, false))->run($state);
                throw new HorseImportPreviewDone();
            });
        } catch (HorseImportPreviewDone) {
        }
        return $report;
    }

    /** Imports for real: one transaction, all or nothing. */
    public static function import(array $state, array $opt = []): array
    {
        @set_time_limit(300);
        $book = self::book($state);
        return DB::transaction(function () use ($book, $opt, $state) {
            $report = (new self($book, $opt, true))->run($state);
            $t = $report['totals'];
            if ($t['new'] || $t['update']) {
                Audit::log('import_horses', 'horses', 'horse', null, null, ['file' => $report['file'], 'rows' => count($report['rows'])] + $t,
                    'Horses imported from Excel (' . $report['file'] . '): ' . $t['new'] . ' new, ' . $t['update'] . ' completed, ' . $t['ancestors'] . ' pedigree-only, ' . $t['skip'] . ' rows skipped');
                Cache::bump();
            }
            return $report;
        });
    }

    /** What the result page keeps: the counts and the horses added. */
    public static function summary(array $report): array
    {
        $new = array_values(array_map(fn ($r) => ['id' => $r['id'], 'name' => $r['name']], array_filter($report['rows'], fn ($r) => $r['status'] === 'new')));
        return ['file' => $report['file'], 'totals' => $report['totals'], 'new' => array_slice($new, 0, 200), 'at' => date('Y-m-d H:i:s')];
    }

    private static function book(array $state): array
    {
        $path = self::path($state);
        if ($path === null) {
            throw new \DomainException(__('himport.expired'));
        }
        return SheetReader::read($path, (string) ($state['name'] ?? ''));
    }

    // ------------------------------------------------------------------ the run

    private function run(array $state): array
    {
        [$sheet, $headRow, $map, $ignored, $hidden] = $this->header();
        foreach (self::REQUIRED as $k) {
            if (!in_array($k, $map, true)) {
                throw new \DomainException(__('himport.missing_column', ['col' => self::label($k)]));
            }
        }
        $this->load();
        $rows = [];
        foreach ($this->book['sheets'][$sheet] as $rowNo => $cells) {
            if ($rowNo <= $headRow) {
                continue;
            }
            $raw = [];
            foreach ($map as $i => $k) {
                if (isset($cells[$i]) && $cells[$i] !== '') {
                    $raw[$k] = $cells[$i];
                }
            }
            if ($raw) {
                $rows[] = $this->parse((int) $rowNo, $raw);
            }
        }
        if (!$rows) {
            throw new \DomainException(__('himport.no_rows'));
        }
        foreach ($rows as $i => $r) { // 1. which horse each row is
            if ($r['status'] !== 'skip') {
                $this->match($rows[$i]);
            }
        }
        foreach ($rows as $i => $r) { // 2. add the new ones, complete the others
            if ($r['status'] === 'new') {
                $this->create($rows[$i]);
            } elseif ($r['status'] === 'existing') {
                $this->complete($rows[$i]);
            }
        }
        foreach ($rows as $i => $r) { // 3. sire and dam, once every horse of the file has an id
            if ($r['status'] !== 'skip' && $r['id']) {
                $this->parents($rows[$i]);
            }
        }
        foreach ($rows as $r) {
            $this->totals[$r['status']]++;
            $this->totals['warnings'] += count(array_filter($r['msg'], fn ($m) => $m[0] === 'warn'));
        }
        return [
            'file' => (string) ($state['name'] ?? ''), 'sheet' => (string) $sheet, 'header' => (int) $headRow, 'columns' => array_values($map),
            'ignored' => $ignored, 'hidden' => $hidden, 'totals' => $this->totals,
            'rows' => array_map(fn ($r) => array_intersect_key($r, array_flip(['row', 'name', 'status', 'id', 'msg', 'filled', 'diffs', 'promote'])), $rows),
        ];
    }

    /** The headings row: the first row (of the first sheet that has one) with a heading for the horse's name. */
    private function header(): array
    {
        $aliases = self::aliasMap();
        $available = self::columns();
        foreach ($this->book['sheets'] as $sheet => $rows) {
            $seen = 0;
            foreach ($rows as $rowNo => $cells) {
                if (++$seen > 10) {
                    break;
                }
                $keys = array_map(fn ($v) => is_string($v) ? ($aliases[self::norm($v)] ?? null) : null, $cells);
                if (!in_array('name_en', $keys, true)) {
                    continue;
                }
                $map = $ignored = $hidden = [];
                foreach ($cells as $i => $v) {
                    $k = $keys[$i];
                    if ($k !== null && !isset($available[$k])) {
                        $hidden[] = $k; // the UELN before the database has it, a price without "view sensitive"
                    } elseif ($k === null || in_array($k, $map, true)) {
                        $ignored[] = self::text($v);
                    } else {
                        $map[$i] = $k;
                    }
                }
                return [$sheet, $rowNo, $map, $ignored, $hidden];
            }
        }
        throw new \DomainException(__('himport.no_header'));
    }

    /** The horses in the system and the indexes to find them (the stud's own first). */
    private function load(): void
    {
        $cols = 'id, name_en, name_ar, sex, is_external, registration_no, microchip, passport_no' . ($this->hasUeln ? ', ueln' : '');
        foreach (DB::all("SELECT $cols FROM horses WHERE deleted_at IS NULL ORDER BY is_external, id") as $h) {
            $this->index($h);
        }
    }

    private function index(array $h): void
    {
        $id = (int) $h['id'];
        $this->horses[$id] = $h;
        foreach (self::IDENTS as $f) {
            if (($h[$f] ?? '') !== '' && $h[$f] !== null) {
                $this->byIdent[$f][self::idKey((string) $h[$f])][] = $id;
            }
        }
        $this->byName[HorseCardsImport::key((string) $h['name_en'])][] = $id;
        if (($h['name_ar'] ?? '') !== '' && $h['name_ar'] !== null) {
            $this->byNameAr[self::arKey((string) $h['name_ar'])][] = $id;
        }
    }

    // ------------------------------------------------------------------ reading a row

    private function parse(int $rowNo, array $raw): array
    {
        $r = ['row' => $rowNo, 'name' => '', 'status' => 'new', 'id' => null, 'v' => [], 'msg' => [], 'filled' => [], 'diffs' => [], 'promote' => false];
        foreach ($raw as $k => $value) {
            [$v, $msg] = $this->value($k, self::COLUMNS[$k], $value);
            if ($msg) {
                $r['msg'][] = $msg;
            }
            if ($v !== null) {
                $r['v'][$k] = $v;
            }
        }
        $r['name'] = (string) ($r['v']['name_en'] ?? self::text($raw['name_en'] ?? ($raw['name_ar'] ?? '')));
        if (!isset($raw['name_en'])) {
            $r['msg'][] = ['error', __('himport.e_name')];
        }
        if (!isset($raw['sex'])) {
            $r['msg'][] = ['error', __('himport.e_sex_missing')];
        }
        if (isset($r['v']['category'], $r['v']['sex']) && !self::fits($r['v']['category'], $r['v']['sex'])) {
            $r['msg'][] = ['warn', __('himport.w_category_sex', ['value' => __('horse.cat_' . $r['v']['category'])])];
            unset($r['v']['category']);
        }
        if (array_filter($r['msg'], fn ($m) => $m[0] === 'error')) {
            $r['status'] = 'skip';
        }
        return $r;
    }

    /** @return array{0: mixed, 1: ?array} the value for the database (null = nothing usable) and a message */
    private function value(string $k, string $kind, string|int|float $raw): array
    {
        $field = self::label($k);
        $shown = self::text($raw);
        $bad = fn (string $key = 'himport.w_value') => [null, ['warn', __($key, ['field' => $field, 'value' => $shown])]];
        switch ($kind) {
            case 'name':
            case 'text':
            case 'parent':
                $s = self::text($raw, $k === 'notes');
                $max = self::MAX[$k] ?? 255;
                if (mb_strlen($s) > $max) {
                    return $kind === 'name' ? [null, ['error', __('himport.e_too_long', ['field' => $field, 'max' => $max])]]
                        : [mb_substr($s, 0, $max), ['warn', __('himport.w_cut', ['field' => $field, 'max' => $max])]];
                }
                return [$s !== '' ? $s : null, null];
            case 'ident':
                if (mb_strlen($shown) > self::MAX[$k] || ($k === 'microchip' && !preg_match('/^[0-9A-Za-z\- ]{4,60}$/', $shown))) {
                    return $bad();
                }
                return [$shown, null];
            case 'sex':
                $sex = $this->listValue('sex', $raw);
                return $sex !== null ? [$sex, null] : [null, ['error', __('himport.e_sex', ['value' => $shown])]];
            case 'category':
            case 'status':
            case 'bool':
                $v = $this->listValue($kind, $raw);
                return $v !== null ? [$v, null] : $bad();
            case 'date':
                return $this->date($field, $raw);
            case 'lookup':
                $id = $this->lookupId($k, $raw);
                return $id ? [$id, null] : $bad('himport.w_lookup');
            case 'country':
                $cc = $this->country($raw);
                return $cc !== null ? [$cc, null] : $bad();
            case 'owner':
                return $this->owner($shown);
            case 'money':
                $n = self::number($raw);
                return $n !== null && $n >= 0 && $n < 1e12 ? [round($n, 2), null] : $bad();
        }
        return [null, null];
    }

    /** A date in any of the usual ways (an Excel date, 12/03/2020 = 12 March, 2020-03-12, 12 Mar 2020, "١٢ مارس ٢٠٢٠"); a year alone means 1 January. */
    private function date(string $field, string|int|float $raw): array
    {
        $year = null;
        $d = null;
        if (is_int($raw) || is_float($raw)) {
            if ((float) (int) $raw === (float) $raw && $raw >= 1900 && $raw <= 2100) {
                $year = (int) $raw;
            } else {
                $d = SheetReader::excelDate((float) $raw, (bool) ($this->book['date1904'] ?? false));
            }
        } else {
            $s = strtr(mb_strtolower(self::digits(trim($raw))), self::AR_MONTHS);
            if (preg_match('/^\d{4}$/', $s)) {
                $year = (int) $s;
            } elseif (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})(?:[ t].*)?$/', $s, $m)) {
                $d = self::ymd((int) $m[1], (int) $m[2], (int) $m[3]);
            } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2}|\d{4})$/', $s, $m)) {
                [$a, $b, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
                if ($y < 100) {
                    $y += $y <= (int) date('y') ? 2000 : 1900;
                }
                // the day first, as in Qatar; the month first only when the second number cannot be a month (03/25/2020)
                $d = $a > 12 || $b <= 12 ? self::ymd($y, $b, $a) : self::ymd($y, $a, $b);
            } elseif (preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)/', $s) && preg_match('/\b\d{4}\b/', $s) && ($t = strtotime($s)) !== false) {
                $d = date('Y-m-d', $t);
            }
        }
        if ($year !== null) {
            return [sprintf('%04d-01-01', $year), ['warn', __('himport.w_year_only', ['field' => $field, 'year' => $year])]];
        }
        if ($d === null || $d < '1900-01-01') {
            return [null, ['warn', __('himport.w_value', ['field' => $field, 'value' => self::text($raw)])]];
        }
        if ($d > date('Y-m-d')) {
            return [null, ['warn', __('himport.w_future', ['field' => $field, 'date' => fmt_date($d)])]];
        }
        return [$d, null];
    }

    private static function ymd(int $y, int $m, int $d): ?string
    {
        return $y >= 1000 && checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : null;
    }

    /** Sex, category, status and yes/no as people type them, in English or Arabic. */
    private function listValue(string $list, string|int|float $raw): string|int|null
    {
        if ($list === 'bool' && is_string($raw) && in_array(trim($raw), ['✓', '✔', '☑', 'x', 'X'], true)) {
            return 1;
        }
        return self::lists()[$list][self::norm(self::text($raw))] ?? null;
    }

    private static function lists(): array
    {
        if (self::$lists) {
            return self::$lists;
        }
        $l = ['sex' => [], 'category' => [], 'status' => [], 'bool' => []];
        $add = function (string $list, string $text, string|int $to) use (&$l): void {
            $n = self::norm($text);
            if ($n !== '') {
                $l[$list][$n] ??= $to;
            }
        };
        $catSex = ['colt' => 'male', 'stallion' => 'male', 'filly' => 'female', 'mare' => 'female'];
        foreach (['en', 'ar'] as $lang) {
            foreach (['male', 'female'] as $s) {
                $add('sex', Lang::get('horse.sex_' . $s, [], $lang), $s);
            }
            foreach (array_keys(Horses::CATS) as $c) {
                $add('category', Lang::get('horse.cat_' . $c, [], $lang), $c);
                if (isset($catSex[$c])) {
                    $add('sex', Lang::get('horse.cat_' . $c, [], $lang), $catSex[$c]);
                }
            }
            foreach (array_keys(Horses::statuses()) as $s) {
                $add('status', Lang::get('horse.status_' . $s, [], $lang), $s);
            }
            $add('bool', Lang::get('common.yes', [], $lang), 1);
            $add('bool', Lang::get('common.no', [], $lang), 0);
        }
        foreach (['m' => 'male', 'male' => 'male', 'stallion' => 'male', 'colt' => 'male', 'ذكر' => 'male', 'حصان' => 'male', 'فحل' => 'male', 'مهر' => 'male',
                  'f' => 'female', 'female' => 'female', 'mare' => 'female', 'filly' => 'female', 'أنثى' => 'female', 'انثى' => 'female', 'فرس' => 'female', 'مهرة' => 'female'] as $t => $s) {
            $add('sex', $t, $s);
        }
        foreach (array_keys(Horses::CATS) as $c) {
            $add('category', $c, $c);
        }
        $add('category', 'رضيع', 'foal');
        foreach (array_keys(Horses::statuses()) as $s) {
            $add('status', $s, $s);
        }
        foreach (['dead' => 'deceased', 'died' => 'deceased', 'متوفى' => 'deceased', 'shelter' => 'in_shelter', 'leased' => 'leased_out', 'on lease' => 'leased_out', 'export' => 'exported'] as $t => $s) {
            if (isset(Horses::statuses()[$s])) {
                $add('status', $t, $s);
            }
        }
        foreach (['yes' => 1, 'y' => 1, 'true' => 1, '1' => 1, 'صح' => 1, 'no' => 0, 'n' => 0, 'false' => 0, '0' => 0, 'خطأ' => 0] as $t => $b) {
            $add('bool', (string) $t, $b);
        }
        return self::$lists = $l;
    }

    private static function fits(string $category, string $sex): bool
    {
        return match ($category) {
            'colt', 'stallion' => $sex === 'male',
            'filly', 'mare' => $sex === 'female',
            default => true,
        };
    }

    /** A colour or stable from the dropdown lists, by its English or Arabic name. */
    private function lookupId(string $k, string|int|float $raw): ?int
    {
        $type = $k === 'color' ? 'color' : 'location';
        if (!isset($this->lookups[$type])) {
            $m = [];
            foreach (DB::all('SELECT id, value_en, value_ar FROM lookups WHERE type = ? ORDER BY active DESC, sort, id', [$type]) as $o) {
                foreach ([$o['value_en'], $o['value_ar']] as $v) {
                    $n = self::lookupKey((string) $v);
                    if ($n !== '') {
                        $m[$n] ??= (int) $o['id'];
                    }
                }
            }
            $this->lookups[$type] = $m;
        }
        return $this->lookups[$type][self::lookupKey(self::text($raw))] ?? null;
    }

    private static function lookupKey(string $s): string
    {
        return str_replace('gray', 'grey', self::norm($s));
    }

    /** A country by its code (QA), its name or its nationality, in English or Arabic. */
    private function country(string|int|float $raw): ?string
    {
        if ($this->countries === null) {
            $m = [];
            foreach (DB::all('SELECT id, name_en, name_ar, nationality_en, nationality_ar FROM countries') as $c) {
                foreach ([$c['id'], $c['name_en'], $c['name_ar'], $c['nationality_en'], $c['nationality_ar']] as $v) {
                    $n = self::norm((string) $v);
                    if ($n !== '') {
                        $m[$n] ??= (string) $c['id'];
                    }
                }
            }
            $known = array_flip(array_values($m));
            foreach (['uae' => 'AE', 'emirates' => 'AE', 'الامارات' => 'AE', 'usa' => 'US', 'america' => 'US', 'uk' => 'GB', 'england' => 'GB', 'britain' => 'GB',
                      'great britain' => 'GB', 'ksa' => 'SA', 'saudi' => 'SA', 'السعودية' => 'SA', 'holland' => 'NL'] as $a => $cc) {
                if (isset($known[$cc])) {
                    $m[self::norm($a)] ??= $cc;
                }
            }
            $this->countries = $m;
        }
        return $this->countries[self::norm(self::text($raw))] ?? null;
    }

    /** SK Arabians, a client of the system, or (once ownership history exists) another owner by name. */
    private function owner(string $s): array
    {
        $n = self::norm($s);
        if ($n === '' || isset(self::skNames()[$n])) {
            return [['type' => 'sk', 'party' => null, 'name' => null], null];
        }
        if (!$this->canSensitive) {
            return [null, ['warn', __('himport.w_owner_permission', ['value' => $s])]];
        }
        if ($this->clients === null) {
            $this->clients = [];
            $where = "type IN ('client','both')" . (DB::hasColumn('parties', 'deleted_at') ? ' AND deleted_at IS NULL' : '');
            foreach (DB::all("SELECT id, name_en, name_ar FROM parties WHERE $where ORDER BY id") as $p) {
                foreach ([$p['name_en'], $p['name_ar']] as $v) {
                    $k = self::norm((string) $v);
                    if ($k !== '') {
                        $this->clients[$k] ??= (int) $p['id'];
                    }
                }
            }
        }
        if (isset($this->clients[$n])) {
            return [['type' => 'client', 'party' => $this->clients[$n], 'name' => null], null];
        }
        if ($this->ownership) {
            return [['type' => 'other', 'party' => null, 'name' => mb_substr($s, 0, 150)], null];
        }
        return [null, ['warn', __('himport.w_owner_unknown', ['value' => $s])]];
    }

    private static function skNames(): array
    {
        $out = [];
        foreach (['sk', 'sk arabians', 'sk arabian', 'sk arabian stud', 'sk arabians stud', 'sk stud', 'إس كي', 'اس كي', Lang::get('horses.owner_sk', [], 'en'), Lang::get('horses.owner_sk', [], 'ar')] as $s) {
            $out[self::norm($s)] = true;
        }
        return $out;
    }

    // ------------------------------------------------------------------ which horse

    private function match(array &$r): void
    {
        $v = $r['v'];
        $key = HorseCardsImport::key((string) $v['name_en']);
        $found = [];
        foreach (self::IDENTS as $f) {
            foreach (isset($v[$f]) ? ($this->byIdent[$f][self::idKey((string) $v[$f])] ?? []) : [] as $id) {
                $found[$id] = true;
            }
        }
        if (count($found) > 1) {
            $this->skip($r, __('himport.e_ident_conflict', ['names' => self::quoted(array_map(fn ($id) => (string) $this->horses[$id]['name_en'], array_keys($found)))]));
            return;
        }
        $id = null;
        if ($found) {
            $id = (int) array_key_first($found);
            if (HorseCardsImport::key((string) $this->horses[$id]['name_en']) !== $key) {
                $r['msg'][] = ['warn', __('himport.w_name_differs', ['have' => $this->horses[$id]['name_en'], 'file' => $v['name_en']])];
            }
        } else {
            $ids = $this->byName[$key] ?? [];
            $byArabic = false;
            if (!$ids && isset($v['name_ar'])) {
                $ids = $this->byNameAr[self::arKey((string) $v['name_ar'])] ?? [];
                $byArabic = (bool) $ids;
            }
            if (count($ids) > 1) { // the stud's own horse rather than an ancestor of the same name; then the one of the same sex
                foreach ([fn ($i) => !(int) $this->horses[$i]['is_external'], fn ($i) => $this->horses[$i]['sex'] === ($v['sex'] ?? null)] as $pref) {
                    $better = array_values(array_filter($ids, $pref));
                    if ($better) {
                        $ids = $better;
                    }
                }
            }
            if (count($ids) > 1) {
                $this->skip($r, __('himport.e_name_ambiguous', ['name' => $v['name_en'], 'n' => count($ids)]));
                return;
            }
            if ($ids) {
                $id = (int) $ids[0];
                $h = $this->horses[$id];
                foreach (self::IDENTS as $f) { // another number on file: a different horse that happens to have the same name
                    $have = (string) ($h[$f] ?? '');
                    if (isset($v[$f]) && $have !== '' && self::idKey($have) !== self::idKey((string) $v[$f])) {
                        $this->skip($r, __('himport.e_same_name_other_id', ['name' => $h['name_en'], 'field' => self::label($f), 'have' => $have]));
                        return;
                    }
                }
                if (isset($v['sex']) && $h['sex'] !== $v['sex']) {
                    $this->skip($r, __('himport.e_same_name_other_sex', ['name' => $h['name_en'], 'sex' => mb_strtolower(__('horse.sex_' . $h['sex']))]));
                    return;
                }
                if ($byArabic) {
                    $r['msg'][] = ['warn', __('himport.w_name_differs', ['have' => $h['name_en'], 'file' => $v['name_en']])];
                }
            }
        }
        if ($id) {
            if (isset($this->seenId[$id])) {
                $this->skip($r, __('himport.e_duplicate_row', ['row' => $this->seenId[$id]]));
                return;
            }
            $this->seenId[$id] = $r['row'];
            $r['status'] = 'existing';
            $r['id'] = $id;
            return;
        }
        $dupe = $this->seenName[$key] ?? null;
        foreach (self::IDENTS as $f) {
            if (isset($v[$f])) {
                $dupe ??= $this->seenIdent[$f . ':' . self::idKey((string) $v[$f])] ?? null;
            }
        }
        if ($dupe) {
            $this->skip($r, __('himport.e_duplicate_row', ['row' => $dupe]));
            return;
        }
        $this->seenName[$key] = $r['row'];
        foreach (self::IDENTS as $f) {
            if (isset($v[$f])) {
                $this->seenIdent[$f . ':' . self::idKey((string) $v[$f])] = $r['row'];
            }
        }
        $this->lookAlike($r, $key);
    }

    private function skip(array &$r, string $message): void
    {
        $r['msg'][] = ['error', $message];
        $r['status'] = 'skip';
    }

    /** A new name one or two letters away from a horse already in the system (or another row): maybe the same horse spelled differently. */
    private function lookAlike(array &$r, string $key): void
    {
        $c = self::compactKey($key);
        if (strlen($c) < 4) {
            return;
        }
        $like = $this->similar($c, 3);
        if ($like) {
            $r['msg'][] = ['warn', __('himport.w_looks_like', ['names' => self::quoted($like)])];
        }
        $max = strlen($c) >= 8 ? 2 : 1;
        foreach ($this->newCompact as $other => [$row, $name]) {
            $other = (string) $other; // PHP turns a key like "4219" into a number
            if ($other !== $c && abs(strlen($other) - strlen($c)) <= $max && !self::onlyDigitsDiffer($other, $c) && levenshtein($other, $c) <= $max) {
                $r['msg'][] = ['warn', __('himport.w_looks_like_file', ['name' => $name, 'row' => $row])];
                break;
            }
        }
        $this->newCompact[$c] ??= [$r['row'], (string) $r['v']['name_en']];
    }

    /** Names in the system within one letter (short names) or two (8+ letters) of $c. */
    private function similar(string $c, int $limit): array
    {
        if ($this->compact === null) {
            $this->compact = [];
            foreach ($this->horses as $id => $h) {
                $this->compact[self::compactKey(HorseCardsImport::key((string) $h['name_en']))] ??= (string) $h['name_en'];
            }
        }
        $max = strlen($c) >= 8 ? 2 : 1;
        $out = [];
        foreach ($this->compact as $other => $name) {
            $other = (string) $other; // PHP turns a key like "4219" (a horse known by a number) into a number
            if ($other !== $c && $other !== '' && abs(strlen($other) - strlen($c)) <= $max && !self::onlyDigitsDiffer($other, $c) && levenshtein($other, $c) <= $max) {
                $out[] = $name;
                if (count($out) >= $limit) {
                    break;
                }
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ writing

    private function create(array &$r): void
    {
        $v = $r['v'];
        $rule = HorseRules::category($v['sex'], $v['dob'] ?? null);
        $cat = $v['category'] ?? $rule;
        $owner = $v['owner'] ?? ['type' => 'sk', 'party' => null, 'name' => null];
        $status = $v['status'] ?? 'active';
        $born = (int) ($v['born_at_sk'] ?? 0);
        $publish = !empty($this->opt['publish']) && in_array($status, ['active', 'in_shelter'], true);
        $data = [
            'name_en' => $v['name_en'], 'name_ar' => $v['name_ar'] ?? null, 'slug' => HorseService::uniqueSlug((string) $v['name_en']), 'sex' => $v['sex'],
            'dob' => $v['dob'] ?? null, 'category' => $cat, 'category_locked' => $cat !== $rule ? 1 : 0, 'color_id' => $v['color'] ?? null, 'breed_id' => $this->breedId(),
            'breeder' => $v['breeder'] ?? null, 'origin_country' => $v['country'] ?? null, 'registration_no' => $v['registration_no'] ?? null,
            'microchip' => $v['microchip'] ?? null, 'passport_no' => $v['passport_no'] ?? null, 'owner_type' => $owner['type'], 'owner_party_id' => $owner['party'],
            'location_id' => $v['location'] ?? null, 'status' => $status, 'is_external' => 0, 'born_at_sk' => $born, 'purchase_date' => $v['purchase_date'] ?? null,
            'purchase_price_qar' => $v['purchase_price'] ?? null, 'notes' => $v['notes'] ?? null, 'show_on_website' => $publish && !$born ? 1 : 0, 'created_by' => $this->by,
        ];
        if ($this->hasUeln) {
            $data['ueln'] = $v['ueln'] ?? null;
        }
        if ($this->ownership) {
            $data['owner_name'] = $owner['name'];
        }
        $data = array_filter($data, fn ($x) => $x !== null);
        $id = DB::insert('horses', $data);
        if ($publish && $born) { // a foal born at the stud is shown once the Owner approves, as when added by hand
            if ($this->real) {
                HorseService::requestWebsite($id);
            }
            $r['msg'][] = ['info', __('himport.i_foal_website')];
        }
        if ($this->real) {
            HorseService::ensureOwnership($id);
        }
        $this->audit('create', $id, null, array_diff_key($data, ['slug' => 1, 'created_by' => 1]), 'Added from Excel import: ' . $v['name_en']);
        $r['id'] = $id;
        $this->index(['id' => $id, 'name_en' => $v['name_en'], 'name_ar' => $v['name_ar'] ?? null, 'sex' => $v['sex'], 'is_external' => 0]
            + array_intersect_key($v, array_flip(self::IDENTS)) + array_fill_keys(self::IDENTS, null));
        $this->remember($r, (string) $v['name_en']);
    }

    /** A horse already in the system: its empty fields filled in, anything different listed. */
    private function complete(array &$r): void
    {
        $id = (int) $r['id'];
        $h = DB::row('SELECT * FROM horses WHERE id = ?', [$id]);
        $v = $r['v'];
        $set = [];
        $promote = (int) $h['is_external'] === 1;
        $empty = fn ($x) => $x === null || $x === '';
        $fill = function (string $k, string $col, mixed $file, bool $same, string $have, string $shown) use (&$set, &$r, $h, $empty): void {
            if ($empty($h[$col] ?? null)) {
                $set[$col] = $file;
                $r['filled'][] = $k;
            } elseif (!$same) {
                $r['diffs'][] = [self::label($k), $have, $shown];
            }
        };
        foreach (['name_ar', 'registration_no', 'microchip', 'ueln', 'passport_no', 'breeder', 'dob', 'purchase_date'] as $k) {
            if (!isset($v[$k]) || ($k === 'ueln' && !$this->hasUeln)) {
                continue;
            }
            $have = (string) ($h[$k] ?? '');
            $same = match ($k) {
                'name_ar' => self::arKey($have) === self::arKey((string) $v[$k]),
                'breeder' => self::norm($have) === self::norm((string) $v[$k]),
                'dob', 'purchase_date' => $have === $v[$k],
                default => self::idKey($have) === self::idKey((string) $v[$k]),
            };
            $isDate = in_array($k, ['dob', 'purchase_date'], true);
            $fill($k, $k, $v[$k], $same, $isDate ? fmt_date($have) : $have, $isDate ? fmt_date((string) $v[$k]) : (string) $v[$k]);
        }
        foreach (['color' => 'color_id', 'location' => 'location_id', 'country' => 'origin_country'] as $k => $col) {
            if (isset($v[$k])) {
                $fill($k, $col, $v[$k], (string) ($h[$col] ?? '') === (string) $v[$k], $this->shown($k, $h[$col] ?? null), $this->shown($k, $v[$k]));
            }
        }
        if (isset($v['sex']) && $v['sex'] !== $h['sex']) {
            $r['diffs'][] = [self::label('sex'), __('horse.sex_' . $h['sex']), __('horse.sex_' . $v['sex'])];
        }
        if (isset($v['category']) && $v['category'] !== $h['category']) {
            $r['diffs'][] = [self::label('category'), __('horse.cat_' . $h['category']), __('horse.cat_' . $v['category'])];
        }
        // owner and status change through "Transfer ownership" — except on a horse known so far only from pedigrees
        $history = $promote && $this->ownership && DB::value('SELECT 1 FROM horse_ownership WHERE horse_id = ? AND deleted_at IS NULL LIMIT 1', [$id]);
        if (isset($v['owner'])) {
            $o = $v['owner'];
            $haveShown = $this->ownerShown((string) $h['owner_type'], $h['owner_party_id'] ? (int) $h['owner_party_id'] : null, (string) ($h['owner_name'] ?? ''));
            $fileShown = $this->ownerShown($o['type'], $o['party'], (string) $o['name']);
            if (self::norm($haveShown) !== self::norm($fileShown)) {
                if ($promote && !$history) {
                    $set += ['owner_type' => $o['type'], 'owner_party_id' => $o['party']] + ($this->ownership ? ['owner_name' => $o['name']] : []);
                    $r['filled'][] = 'owner';
                } else {
                    $r['diffs'][] = [self::label('owner'), $haveShown, $fileShown];
                }
            }
        }
        if (isset($v['status']) && $v['status'] !== $h['status']) {
            if ($promote && !$history) {
                $set['status'] = $v['status'];
                $r['filled'][] = 'status';
            } else {
                $r['diffs'][] = [self::label('status'), __('horse.status_' . $h['status']), __('horse.status_' . $v['status'])];
            }
        }
        if (isset($v['born_at_sk'])) {
            if ($v['born_at_sk'] && !(int) $h['born_at_sk']) {
                $set['born_at_sk'] = 1;
                $r['filled'][] = 'born_at_sk';
            } elseif (!$v['born_at_sk'] && (int) $h['born_at_sk']) {
                $r['diffs'][] = [self::label('born_at_sk'), __('common.yes'), __('common.no')];
            }
        }
        if (isset($v['purchase_price'])) {
            $have = $h['purchase_price_qar'];
            $fill('purchase_price', 'purchase_price_qar', $v['purchase_price'], $have !== null && abs((float) $have - (float) $v['purchase_price']) < 0.005,
                $have !== null ? money($have, 'QAR', false) : '', money($v['purchase_price'], 'QAR', false));
        }
        if (isset($v['notes'])) { // added below what is there, unless already there
            $have = trim((string) $h['notes']);
            if ($have === '') {
                $set['notes'] = $v['notes'];
                $r['filled'][] = 'notes';
            } elseif (!str_contains(self::flat($have), self::flat((string) $v['notes']))) {
                $set['notes'] = $have . "\n\n" . $v['notes'];
                $r['filled'][] = 'notes';
            }
        }
        if ($promote) {
            $set['is_external'] = 0;
            $r['promote'] = true;
        }
        if (isset($set['dob']) && !(int) $h['category_locked']) { // the category follows the age, unless set by hand
            $cat = HorseRules::category((string) $h['sex'], (string) $set['dob']);
            if ($cat !== $h['category']) {
                $set['category'] = $cat;
            }
        }
        $r['name'] = (string) $h['name_en'];
        if ($set && !$this->canEdit) {
            $r['filled'] = [];
            $r['promote'] = false;
            $this->skip($r, __('himport.e_no_edit'));
            return;
        }
        if ($set) {
            DB::update('horses', $set + ['updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
            $this->audit('update', $id, array_intersect_key($h, $set), $set, 'Details added from Excel import: ' . $h['name_en']);
            if ($promote && $this->real) {
                HorseService::ensureOwnership($id);
            }
            $this->horses[$id]['is_external'] = 0;
            $r['status'] = 'update';
        } else {
            $r['status'] = 'same';
        }
        $this->remember($r, (string) $v['name_en']);
        $this->remember($r, (string) $h['name_en']);
    }

    /** A horse of this file, findable as a sire or dam of another row (by the name in the file, or its registration no.). */
    private function remember(array $r, string $name): void
    {
        $this->fileName[HorseCardsImport::key($name)] = (int) $r['id'];
        if (isset($r['v']['registration_no'])) {
            $this->fileIdent[self::idKey((string) $r['v']['registration_no'])] = (int) $r['id'];
        }
    }

    /** Sire and dam: linked when empty; a different one already on file is listed. */
    private function parents(array &$r): void
    {
        $id = (int) $r['id'];
        $h = DB::row('SELECT id, name_en, sire_id, dam_id FROM horses WHERE id = ?', [$id]);
        $set = [];
        foreach (['sire' => 'male', 'dam' => 'female'] as $k => $sex) {
            $val = $r['v'][$k] ?? null;
            if ($val === null) {
                continue;
            }
            $col = $k . '_id';
            $role = mb_strtolower(self::label($k));
            if ($h[$col]) {
                $p = DB::row('SELECT name_en, name_ar, registration_no FROM horses WHERE id = ?', [$h[$col]]);
                $same = $p && (HorseCardsImport::key((string) $p['name_en']) === HorseCardsImport::key($val)
                    || ($p['name_ar'] && self::arKey((string) $p['name_ar']) === self::arKey($val))
                    || ($p['registration_no'] && self::idKey((string) $p['registration_no']) === self::idKey($val)));
                if (!$same) {
                    $r['diffs'][] = [self::label($k), (string) ($p['name_en'] ?? ''), $val];
                }
                continue;
            }
            if ($r['status'] !== 'new' && !$this->canEdit) {
                $r['msg'][] = ['warn', __('himport.w_parent_no_edit', ['role' => $role])];
                continue;
            }
            $pid = $this->parentId($val, $sex, $r, $role);
            if (!$pid) {
                continue;
            }
            if ($pid === $id || HorseService::isSelfOrDescendant($id, $pid)) {
                $r['msg'][] = ['warn', __('himport.w_parent_loop', ['name' => $val, 'role' => $role])];
                continue;
            }
            $set[$col] = $pid;
            if ($r['status'] !== 'new') {
                $r['filled'][] = $k;
            }
            $this->totals['links']++;
        }
        if ($set) {
            DB::update('horses', $set + ['updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
            $this->audit('update', $id, array_intersect_key($h, $set), $set, 'Pedigree from Excel import: ' . $h['name_en']);
            if ($r['status'] === 'same') {
                $r['status'] = 'update';
            }
        }
    }

    /** The horse a sire/dam cell names: in this file, then in the system (registration no., then name); else a new pedigree-only horse. */
    private function parentId(string $val, string $sex, array &$r, string $role): ?int
    {
        $key = HorseCardsImport::key($val);
        $pid = $this->fileName[$key] ?? $this->fileIdent[self::idKey($val)] ?? null;
        if (!$pid) {
            $ids = $this->byIdent['registration_no'][self::idKey($val)] ?? [];
            if (!$ids) {
                $ids = $this->byName[$key] ?? ($this->byNameAr[self::arKey($val)] ?? []);
            }
            if (count($ids) > 1) {
                $ids = array_values(array_filter($ids, fn ($i) => ($this->horses[$i]['sex'] ?? '') === $sex)) ?: $ids;
            }
            if (count($ids) > 1) {
                $r['msg'][] = ['warn', __('himport.w_parent_ambiguous', ['name' => $val, 'role' => $role])];
                return null;
            }
            $pid = $ids ? (int) $ids[0] : null;
        }
        if (!$pid) {
            if (self::looksLikeNumber($val)) { // a registration no. that is not in the system, not a name
                $r['msg'][] = ['warn', __('himport.w_parent_number', ['value' => $val, 'role' => $role])];
                return null;
            }
            return $this->ancestor($val, $sex, $r, $role);
        }
        $p = $this->horses[$pid] ?? DB::row('SELECT id, name_en, sex FROM horses WHERE id = ?', [$pid]);
        if ($p && in_array($p['sex'] ?? '', ['male', 'female'], true) && $p['sex'] !== $sex) {
            $r['msg'][] = ['warn', __('himport.w_parent_sex', ['name' => $p['name_en'], 'role' => $role, 'sex' => mb_strtolower(__('horse.sex_' . $p['sex']))])];
            return null;
        }
        return $pid;
    }

    /** A sire or dam not in the system: added as a pedigree-only horse, like the pedigree editor's "+ Add new". */
    private function ancestor(string $name, string $sex, array &$r, string $role): int
    {
        $name = mb_substr($name, 0, 120);
        $like = $this->similar(self::compactKey(HorseCardsImport::key($name)), 2);
        $id = DB::insert('horses', ['name_en' => $name, 'slug' => HorseService::uniqueSlug($name), 'sex' => $sex, 'category' => HorseRules::category($sex, null),
            'is_external' => 1, 'status' => 'active', 'created_by' => $this->by]);
        $this->audit('create', $id, null, ['name_en' => $name, 'is_external' => 1], 'Pedigree-only horse added from Excel import: ' . $name);
        $r['msg'][] = ['info', $like ? __('himport.i_parent_new_like', ['role' => $role, 'name' => $name, 'like' => self::quoted($like)]) : __('himport.i_parent_new', ['role' => $role, 'name' => $name])];
        $this->index(['id' => $id, 'name_en' => $name, 'name_ar' => null, 'sex' => $sex, 'is_external' => 1] + array_fill_keys(self::IDENTS, null));
        $this->fileName[HorseCardsImport::key($name)] = $id; // the next row naming the same horse links to this one
        $this->totals['ancestors']++;
        return $id;
    }

    /** Activity log entries for the real import only: the preview's would be rolled back anyway, and would hold the log's lock meanwhile. */
    private function audit(string $action, int $id, ?array $old, ?array $new, string $summary): void
    {
        if ($this->real) {
            Audit::log($action, 'horses', 'horse', $id, $old, $new, $summary);
        }
    }

    private function breedId(): ?int
    {
        if ($this->breed === false) {
            $this->breed = (int) DB::value("SELECT id FROM lookups WHERE type = 'breed' AND value_en = 'Purebred Arabian'") ?: null;
        }
        return $this->breed ?: null;
    }

    /** A colour, stable or country as the user reads it. */
    private function shown(string $k, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if ($k === 'country') {
            $c = DB::row('SELECT name_en, name_ar FROM countries WHERE id = ?', [$value]);
            return $c ? loc($c) : (string) $value;
        }
        $o = DB::row('SELECT value_en, value_ar FROM lookups WHERE id = ?', [(int) $value]);
        return $o ? loc($o, 'value') : '';
    }

    private function ownerShown(string $type, ?int $party, string $name): string
    {
        return match ($type) {
            'client' => $party ? (string) (loc(DB::row('SELECT name_en, name_ar FROM parties WHERE id = ?', [$party]))) : '',
            'other' => $name,
            default => __('horses.owner_sk'),
        };
    }

    // ------------------------------------------------------------------ the template

    /** The columns this user's template has: the UELN once the database has it, the purchase price only with "view sensitive". */
    public static function columns(): array
    {
        $cols = self::COLUMNS;
        if (!DB::hasColumn('horses', 'ueln')) {
            unset($cols['ueln']);
        }
        if (!Auth::can('horses', 'sensitive')) {
            unset($cols['purchase_price']);
        }
        return $cols;
    }

    public static function required(string $key): bool
    {
        return in_array($key, self::REQUIRED, true);
    }

    /** A column's name, as on the horse form. */
    public static function label(string $key, ?string $lang = null): string
    {
        return Lang::get(match ($key) {
            'name_en' => 'horses.name_en', 'name_ar' => 'horses.name_ar', 'sex' => 'horse.sex', 'dob' => 'horses.dob', 'category' => 'horses.category',
            'color' => 'horse.color', 'registration_no' => 'horses.registration_no', 'microchip' => 'horses.microchip', 'ueln' => 'horses.ueln',
            'passport_no' => 'horses.passport_no', 'breeder' => 'horse.breeder', 'country' => 'horses.origin_country', 'owner' => 'horses.owner_type',
            'location' => 'horses.location', 'status' => 'common.status', 'sire' => 'horse.sire', 'dam' => 'horse.dam', 'born_at_sk' => 'himport.col_born_at_sk',
            'purchase_date' => 'horses.purchase_date', 'purchase_price' => 'horses.purchase_price', default => 'common.notes',
        }, [], $lang);
    }

    /** Every heading the import knows (the template's own in both languages, and ALIASES), loosely compared => column key. */
    private static function aliasMap(): array
    {
        $map = [];
        foreach (array_keys(self::COLUMNS) as $k) {
            foreach (['en', 'ar'] as $lang) {
                $map[self::norm(self::label($k, $lang))] ??= $k;
            }
            foreach (self::ALIASES[$k] ?? [] as $alias) {
                $map[self::norm($alias)] ??= $k;
            }
        }
        unset($map['']);
        return $map;
    }

    /** The template's heading of a column: its name, with * when it must be filled in. */
    public static function heading(string $key, ?string $lang = null): string
    {
        return self::label($key, $lang) . (self::required($key) ? ' *' : '');
    }

    /**
     * The Excel template in English or Arabic: the Horses sheet (empty, or with the stud's horses to complete), a Help
     * sheet and the Lists the drop-downs use. $count = the horses written.
     */
    public static function template(string $lang, bool $withHorses = false, ?int &$count = null): string
    {
        $keys = array_keys(self::columns());
        $at = array_flip($keys);
        $listSheet = Xlsx::sheetName(Lang::get('himport.sheet_lists', [], $lang));
        $listHeaders = $listRows = $ranges = [];
        $c = 0;
        foreach (self::templateLists($lang) as $k => [$title, $values]) {
            if (!$values || !isset($at[$k])) {
                continue;
            }
            $listHeaders[] = $title;
            foreach (array_values($values) as $i => $val) {
                $listRows[$i][$c] = $val;
            }
            $col = Xlsx::col($c);
            $ranges[$at[$k]] = "'" . str_replace("'", "''", $listSheet) . "'!\$" . $col . '$2:$' . $col . '$' . (count($values) + 1);
            $c++;
        }
        foreach ($c > 0 ? $listRows : [] as $i => $row) {
            $listRows[$i] = array_map(fn ($j) => $row[$j] ?? '', range(0, $c - 1));
        }
        $help = [];
        foreach ($keys as $k) {
            $help[] = [self::heading($k, $lang), Lang::get(self::required($k) ? 'common.yes' : 'common.no', [], $lang), Lang::get('himport.help_' . $k, [], $lang), Lang::get('himport.ex_' . $k, [], $lang)];
        }
        $rows = $withHorses ? self::ourHorses($keys, $lang) : [];
        $count = count($rows);
        $text = $dates = $widths = [];
        foreach ($keys as $i => $k) {
            $kind = self::COLUMNS[$k];
            if (in_array($kind, ['name', 'text', 'ident', 'parent'], true)) {
                $text[] = $i; // typed as text: Excel does not turn a microchip into 9.85E+14 or a name like "MAY 1" into a date
            } elseif ($kind === 'date') {
                $dates[] = $i;
            }
            $widths[$i] = match ($kind) { 'name', 'parent', 'owner' => 26, 'ident' => 20, 'date' => 14, 'text' => $k === 'notes' ? 40 : 22, default => 16 };
        }
        return Xlsx::workbook([
            ['name' => Lang::get('himport.sheet_horses', [], $lang), 'headers' => array_map(fn ($k) => self::heading($k, $lang), $keys), 'rows' => $rows,
             'text' => $text, 'dates' => $dates, 'lists' => $ranges, 'widths' => $widths, 'filter' => (bool) $rows],
            ['name' => Lang::get('himport.sheet_help', [], $lang), 'headers' => [Lang::get('himport.col_heading', [], $lang), Lang::get('himport.col_required', [], $lang),
             Lang::get('himport.col_what', [], $lang), Lang::get('himport.col_example', [], $lang)], 'rows' => $help, 'widths' => [28, 10, 70, 26], 'wrap' => [2]],
            ['name' => $listSheet, 'headers' => $listHeaders, 'rows' => $listRows, 'widths' => array_fill(0, max(1, $c), 24)],
        ], $lang === 'ar', [Lang::get('himport.list_error_title', [], $lang), Lang::get('himport.list_error', [], $lang)]);
    }

    /** The drop-down lists: column key => [heading, values in the template's language]. */
    private static function templateLists(string $lang): array
    {
        $ar = $lang === 'ar';
        $t = fn (string $key) => Lang::get($key, [], $lang);
        $owners = [$t('horses.owner_sk')];
        if (Auth::can('horses', 'sensitive')) {
            $where = "type IN ('client','both')" . (DB::hasColumn('parties', 'deleted_at') ? ' AND deleted_at IS NULL' : '');
            foreach (DB::all("SELECT name_en, name_ar FROM parties WHERE $where ORDER BY name_en") as $p) {
                $owners[] = $ar && $p['name_ar'] ? $p['name_ar'] : $p['name_en'];
            }
        }
        $lookup = fn (string $type) => array_map(fn ($o) => $ar && $o['value_ar'] ? $o['value_ar'] : $o['value_en'],
            DB::all('SELECT value_en, value_ar FROM lookups WHERE type = ? AND active = 1 ORDER BY sort, value_en', [$type]));
        $countries = array_map(fn ($o) => $ar ? $o['name_ar'] : $o['name_en'], DB::all('SELECT name_en, name_ar FROM countries ORDER BY common DESC, sort, ' . ($ar ? 'name_ar' : 'name_en')));
        return [
            'sex' => [self::label('sex', $lang), [$t('horse.sex_male'), $t('horse.sex_female')]],
            'category' => [self::label('category', $lang), array_map(fn ($c) => $t('horse.cat_' . $c), array_keys(Horses::CATS))],
            'color' => [self::label('color', $lang), $lookup('color')],
            'country' => [self::label('country', $lang), $countries],
            'owner' => [self::label('owner', $lang), array_values(array_unique($owners))],
            'location' => [self::label('location', $lang), $lookup('location')],
            'status' => [self::label('status', $lang), array_map(fn ($s) => $t('horse.status_' . $s), array_keys(Horses::statuses()))],
            'born_at_sk' => [$t('himport.list_yes_no'), [$t('common.yes'), $t('common.no')]],
        ];
    }

    /** The stud's horses, one row each, in the template's columns: to fill in what is missing and import again. */
    private static function ourHorses(array $keys, string $lang): array
    {
        $ar = $lang === 'ar';
        $sens = Auth::can('horses', 'sensitive');
        $pick = fn ($en, $arv) => (string) ($ar && $arv ? $arv : ($en ?? ''));
        $rows = DB::all('SELECT h.*, s.name_en AS sire_name, d.name_en AS dam_name, p.name_en AS party_en, p.name_ar AS party_ar, c.name_en AS country_en, c.name_ar AS country_ar,
                col.value_en AS color_en, col.value_ar AS color_ar, loc.value_en AS loc_en, loc.value_ar AS loc_ar
            FROM horses h LEFT JOIN horses s ON s.id = h.sire_id LEFT JOIN horses d ON d.id = h.dam_id LEFT JOIN parties p ON p.id = h.owner_party_id
            LEFT JOIN countries c ON c.id = h.origin_country LEFT JOIN lookups col ON col.id = h.color_id LEFT JOIN lookups loc ON loc.id = h.location_id
            WHERE h.deleted_at IS NULL AND h.is_external = 0 ORDER BY h.name_en');
        $out = [];
        foreach ($rows as $h) {
            $line = [];
            foreach ($keys as $k) {
                $line[] = match ($k) {
                    'sex' => Lang::get('horse.sex_' . $h['sex'], [], $lang),
                    'category' => Lang::get('horse.cat_' . $h['category'], [], $lang),
                    'status' => Lang::get('horse.status_' . $h['status'], [], $lang),
                    'color' => $pick($h['color_en'], $h['color_ar']),
                    'location' => $pick($h['loc_en'], $h['loc_ar']),
                    'country' => $pick($h['country_en'], $h['country_ar']),
                    'owner' => match ((string) $h['owner_type']) {
                        'sk' => Lang::get('horses.owner_sk', [], $lang),
                        'client' => $sens ? $pick($h['party_en'], $h['party_ar']) : '',
                        default => $sens ? (string) ($h['owner_name'] ?? '') : '',
                    },
                    'sire' => (string) ($h['sire_name'] ?? ''),
                    'dam' => (string) ($h['dam_name'] ?? ''),
                    'born_at_sk' => Lang::get((int) $h['born_at_sk'] ? 'common.yes' : 'common.no', [], $lang),
                    'purchase_price' => $h['purchase_price_qar'] !== null ? (float) $h['purchase_price_qar'] : '',
                    default => (string) ($h[$k] ?? ''),
                };
            }
            $out[] = $line;
        }
        return $out;
    }

    // ------------------------------------------------------------------ comparing text

    /** Headings and list values compared loosely: lower case, Arabic letter forms unified, Arabic digits read, only letters and digits kept. */
    public static function norm(string $s): string
    {
        $s = strtr(mb_strtolower(self::digits(trim($s))), self::AR_FOLD);
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $s);
    }

    /** An Arabic name compared whatever its hamza, final ya/ta marbuta, vowel marks or spacing. */
    private static function arKey(string $s): string
    {
        $s = strtr(trim($s), self::AR_FOLD);
        $s = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $s);
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $s)));
    }

    /** A registration, microchip, UELN or passport number compared without spaces, dashes, dots or slashes. */
    private static function idKey(string $s): string
    {
        return strtoupper((string) preg_replace('/[\s\-.\/]+/u', '', self::digits(trim($s))));
    }

    private static function compactKey(string $key): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', $key);
    }

    /** "HORSE 0001" and "HORSE 0002": numbered on purpose, not a spelling slip. */
    private static function onlyDigitsDiffer(string $a, string $b): bool
    {
        return preg_replace('/\d+/', '', $a) === preg_replace('/\d+/', '', $b);
    }

    /** Mostly digits (QAT 12345, 2019-0153): a registration no., not a horse's name. */
    private static function looksLikeNumber(string $v): bool
    {
        $c = (string) preg_replace('/[\s\-.\/]+/u', '', self::digits($v));
        $d = (int) preg_match_all('/\d/', $c);
        return $d >= 4 && $d * 2 >= mb_strlen($c);
    }

    private static function flat(string $s): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $s)));
    }

    private static function digits(string $s): string
    {
        return strtr($s, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
                          '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
    }

    /** A cell as clean text: whole numbers without ".0", single spaces (notes keep their lines), the apostrophe the export puts before = + - @ removed. */
    private static function text(string|int|float $v, bool $lines = false): string
    {
        if (is_float($v)) {
            $v = floor($v) === $v && abs($v) < 1e15 ? sprintf('%.0f', $v) : (string) $v;
        }
        $s = trim((string) $v);
        if (preg_match("/^'[=+\-@]/", $s)) {
            $s = substr($s, 1);
        }
        if ($lines) {
            $s = implode("\n", array_map(fn ($l) => trim((string) preg_replace('/[ \t]+/', ' ', $l)), explode("\n", $s)));
            return trim((string) preg_replace("/\n{3,}/", "\n\n", $s));
        }
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    /** "QAR 150,000" / "١٥٠٬٠٠٠" → 150000. */
    private static function number(string|int|float $v): ?float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = str_replace(['٫', '٬', ',', ' ', "\u{00A0}"], ['.', '', '', '', ''], self::digits($v));
        $s = (string) preg_replace('/[^\d.\-]/', '', $s);
        return is_numeric($s) ? (float) $s : null;
    }

    private static function quoted(array $names): string
    {
        return '“' . implode('”, “', $names) . '”';
    }
}

/** Thrown inside the preview's transaction so that everything it did is rolled back. */
final class HorseImportPreviewDone extends \RuntimeException
{
}
