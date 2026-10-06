<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Crypto;
use App\Core\DB;
use App\Resources\Registry;

/**
 * SK Arabian Studio: permissions, filling documents from linked records, the numbered archive and the
 * public verification token. Documents are stored as the template's data (JSON) and re-rendered with the
 * Studio renderer, so a re-print is always identical to the first print.
 */
final class Studio
{
    private static ?array $allowed = null;

    /** Record kinds a document can be filled from: kind => [resource key, picker source]. */
    public const KINDS = [
        'horse'          => ['horses', 'horses'],
        'breeding'       => ['breeding-records', null],
        'party'          => ['parties', 'clients'],
        'embryo'         => ['embryos', 'embryos'],
        'employee'       => ['employees', 'employees'],
        'purchase_order' => ['purchase-orders', 'purchase_orders'],
        'invoice'        => ['invoices', 'invoices'],
        'payroll_line'   => [null, 'payroll_lines'],
        'leave_request'  => ['leave-requests', 'leave_requests'],
        'bill_payment'   => ['bill-payments', 'bill_payments'],
    ];

    /** The record the editor offers to pick for each type: [kind, picker source]. */
    public const PICK = [
        'profile' => ['horse', 'horses'], 'diet' => ['horse', 'horses'], 'vet' => ['horse', 'horses'], 'cover' => ['horse', 'mares'], 'board' => ['party', 'clients'],
        'embryo' => ['embryo', 'embryos'], 'sal' => ['employee', 'employees'], 'letters' => ['employee', 'employees'], 'offer' => ['employee', 'employees'],
        'idcard' => ['employee', 'employees'], 'po' => ['purchase_order', 'purchase_orders'], 'inv' => ['invoice', 'invoices'], 'pay' => ['payroll_line', 'payroll_lines'],
    ];

    /** Which record kinds fill each document type (the first one is offered in the editor's picker). */
    public const SOURCES = [
        'profile' => ['horse'], 'diet' => ['horse'], 'vet' => ['horse'], 'cover' => ['breeding', 'horse'], 'board' => ['party', 'horse'],
        'embryo' => ['embryo'], 'sal' => ['employee'], 'letters' => ['employee', 'leave_request'], 'offer' => ['employee'], 'idcard' => ['employee'],
        'po' => ['purchase_order'], 'inv' => ['invoice', 'bill_payment'], 'pay' => ['payroll_line'],
    ];

    /** Owner may create everything; other roles need Studio "create", the per-type permission and the record module's "view". */
    public static function canCreate(string $type): bool
    {
        if (!StudioDocs::exists($type) || !Auth::check()) {
            return false;
        }
        if (Auth::isOwner()) {
            return true;
        }
        if (!Auth::can('studio', 'create') || !Auth::can(StudioDocs::TYPES[$type]['module'], 'view')) {
            return false;
        }
        if (self::$allowed === null) {
            self::$allowed = DB::column('SELECT doc_type FROM studio_doc_permissions WHERE role_id = ?', [Auth::user()['role_id']]);
        }
        return in_array($type, self::$allowed, true);
    }

    /** Manual edits to the filled-in fields are for roles with Studio "edit"; others print what the records say. */
    public static function canEditFields(): bool
    {
        return Auth::isOwner() || Auth::can('studio', 'edit');
    }

    public static function canView(array $doc): bool
    {
        return Auth::isOwner() || self::isOwnDoc($doc) || (Auth::can('studio', 'view') && Auth::can(StudioDocs::TYPES[$doc['doc_type']]['module'] ?? 'studio', 'view'));
    }

    /** Staff may always open their own payslips and HR letters (My HR page), even without Studio access. */
    public static function isOwnDoc(array $doc): bool
    {
        $emp = (int) (Auth::user()['employee_id'] ?? 0);
        if (!$emp || $doc['is_void'] || !$doc['record_id']) {
            return false;
        }
        if ($doc['doc_type'] === 'pay' && $doc['record_type'] === 'payroll_line') {
            return (int) DB::value("SELECT l.employee_id FROM payroll_lines l JOIN payroll_runs r ON r.id = l.payroll_run_id
                WHERE l.id = ? AND r.status IN ('approved','paid')", [$doc['record_id']]) === $emp;
        }
        return in_array($doc['doc_type'], self::OWN_TYPES, true) && $doc['record_type'] === 'employee' && (int) $doc['record_id'] === $emp;
    }

    public const OWN_TYPES = ['sal', 'letters', 'offer'];

    // ------------------------------------------------------------------ filling from records

    /**
     * Values taken from a linked record for a new document. Returns [data, record_type, record_id, label].
     * The record is loaded through its Resource, so record-level limits (e.g. a groom's horses) apply.
     */
    public static function prefill(string $type, ?string $kind, ?int $id, array $params = []): array
    {
        $data = [];
        if (!$kind || !$id || !in_array($kind, self::SOURCES[$type] ?? [], true)) {
            return [self::prefillFree($type, $params), null, null, null];
        }
        $row = self::load($kind, $id);
        if (!$row) {
            return [self::prefillFree($type, $params), null, null, null];
        }
        $sens = Auth::can('hr', 'sensitive');
        $label = null;
        switch ($kind) {
            case 'horse':
                $h = self::horse((int) $row['id']);
                $label = $h['name'];
                if ($type === 'profile') {
                    $data = self::profile($h);
                } elseif ($type === 'diet') {
                    $data = ['horse' => $h['name'], 'dob' => $h['dob'], 'sire' => $h['sire'], 'dam' => $h['dam'], 'box' => $h['box'], 'date' => date('Y-m-d'),
                             'preg' => $h['preg'], 'pregAr' => $h['pregAr'], 'items' => self::dietItems((int) $row['id'])];
                } elseif ($type === 'vet') {
                    $data = ['horse' => $h['name'], 'sex' => $h['sex'], 'dob' => $h['dob'], 'sire' => $h['sire'], 'dam' => $h['dam'], 'chip' => $h['chip'], 'box' => $h['box'],
                             'entries' => self::vetEntries((int) $row['id'])];
                } elseif ($type === 'cover') {
                    $br = DB::value('SELECT id FROM breeding_records WHERE mare_id = ? AND deleted_at IS NULL ORDER BY start_date DESC, id DESC LIMIT 1', [$row['id']]);
                    return $br ? self::prefill('cover', 'breeding', (int) $br, $params) : [self::coverMare($h), 'horse', (int) $row['id'], $label];
                } elseif ($type === 'board' && $row['owner_party_id']) {
                    return self::prefill('board', 'party', (int) $row['owner_party_id'], $params);
                }
                break;
            case 'breeding':
                $mare = self::horse((int) $row['mare_id']);
                $label = $mare['name'];
                $data = self::coverMare($mare);
                if ($row['stallion_id']) {
                    $st = self::horse((int) $row['stallion_id']);
                    $data += ['stallion' => $st['name'], 'stallionReg' => $st['passport'], 'stallionSire' => $st['sire'], 'stallionDam' => $st['dam'], 'stOwnerEn' => $st['owner'], 'stOwnerAr' => $st['ownerAr']];
                }
                $data['method'] = ['natural' => 'natural', 'ai_fresh' => 'fresh', 'ai_chilled' => 'chilled', 'ai_frozen' => 'frozen', 'embryo_transfer' => 'et', 'icsi' => 'icsi'][$row['method']] ?? 'natural';
                $data['covers'] = [['date' => $row['start_date']]];
                $data['season'] = substr((string) $row['start_date'], 0, 4);
                $chk = DB::row('SELECT * FROM pregnancy_checks WHERE breeding_record_id = ? AND deleted_at IS NULL ORDER BY check_date DESC, id DESC LIMIT 1', [$row['id']]);
                if ($chk) {
                    $data += ['checkDate' => $chk['check_date'], 'checkResult' => ['positive' => 'pos', 'negative' => 'neg'][$chk['result']] ?? 'none', 'notes' => (string) $chk['notes']];
                }
                break;
            case 'party':
                $label = $row['name_en'];
                $data = ['ownerEn' => $row['name_en'], 'ownerAr' => (string) $row['name_ar'], 'ownerId' => Auth::can('finance', 'sensitive') ? (string) $row['id_number'] : '',
                         'ownerPhone' => Auth::can('finance', 'sensitive') ? (string) $row['phone'] : '', 'start' => date('Y-m-d')];
                $horses = DB::all("SELECT h.name_en, h.sex, h.category, l.value_en AS box FROM horses h LEFT JOIN lookups l ON l.id = h.location_id
                    WHERE h.owner_type = 'client' AND h.owner_party_id = ? AND h.deleted_at IS NULL AND h.archived_at IS NULL AND h.status <> 'deceased' ORDER BY h.name_en", [$row['id']]);
                $data['horses'] = $horses ? array_map(fn ($x) => ['name' => $x['name_en'], 'sex' => self::sex($x['sex'], $x['category']), 'box' => (string) $x['box']], $horses) : [['name' => '', 'sex' => 'mare', 'box' => '']];
                break;
            case 'embryo':
                $label = $row['code'];
                $data = self::embryo($row);
                break;
            case 'employee':
            case 'leave_request':
                $leave = $kind === 'leave_request' ? $row : null;
                $emp = DB::row('SELECT * FROM employees WHERE id = ?', [$leave ? $leave['employee_id'] : $row['id']]);
                if (!$emp) {
                    break;
                }
                $label = $emp['name_en'];
                $kind = 'employee';
                $id = (int) $emp['id'];
                $data = self::employee($emp, $type, $sens);
                if ($type === 'letters') {
                    $leave ??= !empty($params['leave_id']) ? DB::row("SELECT * FROM leave_requests WHERE id = ? AND employee_id = ? AND deleted_at IS NULL", [(int) $params['leave_id'], $emp['id']]) : null;
                    if ($leave) {
                        $data += ['type' => 'leave', 'leaveType' => $leave['leave_type'], 'from' => $leave['start_date'], 'to' => $leave['end_date'], 'reason' => (string) $leave['reason'],
                                  'decision' => $leave['status'] === 'approved' ? 'approved' : ''];
                    } elseif (in_array($params['type'] ?? '', ['exp', 'noc', 'leave', 'eos'], true)) {
                        $data['type'] = $params['type'];
                    }
                }
                if ($type === 'idcard') {
                    $data = ['pick' => [$emp['name_en']]];
                }
                break;
            case 'purchase_order':
                $label = $row['number'];
                $data = self::po($row);
                break;
            case 'invoice':
                $label = $row['number'];
                $data = self::invoice($row);
                break;
            case 'bill_payment':
                $bill = DB::row('SELECT * FROM bills WHERE id = ?', [$row['bill_id']]);
                if (!$bill || $bill['type'] !== 'income') {
                    break;
                }
                $label = $bill['number'];
                $data = self::receipt($row, $bill);
                break;
            case 'payroll_line':
                $label = (string) DB::value('SELECT e.name_en FROM payroll_lines l JOIN employees e ON e.id = l.employee_id WHERE l.id = ?', [$row['id']]);
                $data = self::payslip([(int) $row['id']]);
                break;
        }
        return [$data, $kind, $id, $label];
    }

    /** Documents that are not about one record (period reports, lists). */
    private static function prefillFree(string $type, array $params): array
    {
        if ($type === 'fin' && Auth::can('finance')) {
            // Outstanding payables: approved bills not fully paid, one line per supplier
            $rows = DB::all("SELECT COALESCE(p.name_en, b.description, '—') AS en, p.name_ar AS ar, COUNT(*) AS qty, SUM(b.amount_qar - b.paid_qar) AS amt,
                    MAX(b.payroll_run_id IS NOT NULL) AS salary, SUM(b.paid_qar) AS paid
                FROM bills b LEFT JOIN parties p ON p.id = b.party_id
                WHERE b.deleted_at IS NULL AND b.type <> 'income' AND b.status IN ('approved','partially_paid','overdue')
                GROUP BY COALESCE(CONCAT('p', p.id), CONCAT('b', b.id)), en, ar ORDER BY amt DESC");
            return ['date' => date('Y-m-d'), 'currency' => 'QAR', 'rows' => array_map(fn ($r) => ['en' => $r['en'], 'ar' => (string) $r['ar'], 'qty' => (int) $r['qty'],
                    'amt' => round((float) $r['amt'], 2), 'type' => $r['salary'] ? 'w' : 's', 'status' => (float) $r['paid'] > 0 ? 'partial' : 'pending'], $rows)];
        }
        if ($type === 'pay' && !empty($params['run_id']) && Auth::can('payroll', 'sensitive')) {
            return self::payslip(DB::column('SELECT id FROM payroll_lines WHERE payroll_run_id = ?', [(int) $params['run_id']]), true);
        }
        return [];
    }

    private static function load(string $kind, int $id): ?array
    {
        if ($kind === 'payroll_line') {
            return Auth::can('payroll', 'sensitive') ? DB::row('SELECT * FROM payroll_lines WHERE id = ?', [$id]) : null;
        }
        $res = Registry::get(self::KINDS[$kind][0]);
        $row = $res?->find($id);
        return $row && $res->canView($row) ? $row : null;
    }

    private static function sex(string $sex, ?string $category): string
    {
        return match ($category) {
            'stallion', 'mare', 'gelding', 'colt', 'filly' => $category,
            default => $sex === 'female' ? ($category === 'foal' ? 'filly' : 'mare') : ($category === 'foal' ? 'colt' : 'stallion'),
        };
    }

    /** One horse with the labels the templates use. */
    private static function horse(int $id): array
    {
        $h = DB::row("SELECT h.*, c.value_en AS color_en, c.value_ar AS color_ar, b.value_en AS breed_en, b.value_ar AS breed_ar, l.value_en AS box,
                s.name_en AS sire, d.name_en AS dam, p.name_en AS party_en, p.name_ar AS party_ar
            FROM horses h LEFT JOIN lookups c ON c.id = h.color_id LEFT JOIN lookups b ON b.id = h.breed_id LEFT JOIN lookups l ON l.id = h.location_id
            LEFT JOIN horses s ON s.id = h.sire_id LEFT JOIN horses d ON d.id = h.dam_id LEFT JOIN parties p ON p.id = h.owner_party_id WHERE h.id = ?", [$id]) ?? [];
        $preg = $h && $h['sex'] === 'female' ? DB::row("SELECT status, expected_foaling_date FROM breeding_records WHERE mare_id = ? AND deleted_at IS NULL AND status IN ('pregnant','open') ORDER BY start_date DESC LIMIT 1", [$id]) : null;
        $client = ($h['owner_type'] ?? 'sk') === 'client';
        return [
            'id' => $id, 'name' => (string) ($h['name_en'] ?? ''), 'nameAr' => (string) ($h['name_ar'] ?? ''), 'sex' => self::sex((string) ($h['sex'] ?? 'male'), $h['category'] ?? null),
            'dob' => !empty($h['dob']) ? date('M j, Y', strtotime($h['dob'])) : '', 'colourEn' => (string) ($h['color_en'] ?? ''), 'colourAr' => (string) ($h['color_ar'] ?? ''),
            'breed' => (string) ($h['breed_en'] ?? 'Purebred Arabian'), 'breedAr' => (string) ($h['breed_ar'] ?? 'عربي أصيل'), 'breeder' => (string) ($h['breeder'] ?? ''),
            'owner' => $client ? (string) $h['party_en'] : 'SK Arabians', 'ownerAr' => $client ? (string) $h['party_ar'] : 'اس كي ارابيان', 'origin' => (string) ($h['origin_country'] ?? ''),
            'height' => !empty($h['height_cm']) ? $h['height_cm'] . ' cm' : '', 'chip' => (string) ($h['microchip'] ?? ''),
            'passport' => (string) ($h['passport_no'] ?: ($h['registration_no'] ?? '')), 'box' => (string) ($h['box'] ?? ''), 'sire' => (string) ($h['sire'] ?? ''), 'dam' => (string) ($h['dam'] ?? ''),
            'status' => ($h['status'] ?? '') === 'deceased' ? 'deceased' : ($client ? 'boarding' : 'stud'), 'photo' => $h['main_photo_id'] ?? null,
            'preg' => $preg ? ($preg['status'] === 'pregnant' ? 'In foal' . ($preg['expected_foaling_date'] ? ' — due ' . $preg['expected_foaling_date'] : '') : 'Covered, not checked yet') : '',
            'pregAr' => $preg ? ($preg['status'] === 'pregnant' ? 'عشار' . ($preg['expected_foaling_date'] ? ' — الولادة المتوقعة ' . $preg['expected_foaling_date'] : '') : 'مغطاة، لم تُفحص بعد') : '',
            'story' => (string) ($h['story_en'] ?? ''), 'storyAr' => (string) ($h['story_ar'] ?? ''),
        ];
    }

    private static function profile(array $h): array
    {
        $d = ['nameEn' => $h['name'], 'nameAr' => $h['nameAr'], 'sex' => $h['sex'], 'dob' => $h['dob'], 'colourEn' => $h['colourEn'], 'colourAr' => $h['colourAr'],
              'breed' => $h['breed'], 'breedAr' => $h['breedAr'], 'breeder' => $h['breeder'], 'owner' => $h['owner'], 'origin' => $h['origin'], 'height' => $h['height'],
              'chip' => $h['chip'], 'passport' => $h['passport'], 'status' => $h['status'], 'box' => $h['box'], 'notesEn' => $h['story'], 'notesAr' => $h['storyAr'],
              'photo' => $h['photo'] ? url('/portal/files/' . (int) $h['photo']) : ''];
        // Pedigree: 4 generations, keys p_s, p_d, p_ss ... (s = sire side, d = dam side)
        $walk = function (?int $id, string $path, int $depth) use (&$walk, &$d) {
            if (!$id || $depth > 4) {
                return;
            }
            $r = DB::row('SELECT name_en, sire_id, dam_id FROM horses WHERE id = ?', [$id]);
            if (!$r) {
                return;
            }
            if ($path !== '') {
                $d['p_' . $path] = $r['name_en'];
            }
            $walk($r['sire_id'] ? (int) $r['sire_id'] : null, $path . 's', $depth + 1);
            $walk($r['dam_id'] ? (int) $r['dam_id'] : null, $path . 'd', $depth + 1);
        };
        $walk($h['id'], '', 0);
        $d['shows'] = array_map(fn ($r) => ['date' => (string) $r['start_date'], 'show' => $r['show'], 'cls' => (string) $r['class_name'], 'result' => trim(($r['title_en'] ?: '') . ($r['placing'] ? ' — ' . self::ordinal((int) $r['placing']) : ''), ' —')],
            DB::all('SELECT s.start_date, s.name_en AS `show`, r.class_name, r.title_en, r.placing FROM show_results r JOIN shows s ON s.id = r.show_id WHERE r.horse_id = ? AND r.deleted_at IS NULL ORDER BY s.start_date DESC LIMIT 20', [$h['id']]));
        $male = in_array($h['sex'], ['stallion', 'colt', 'gelding'], true);
        $plan = ['open' => 'covered', 'pregnant' => 'infoal', 'not_pregnant' => 'notinfoal', 'foaled' => 'foaled', 'lost' => 'notinfoal', 'cancelled' => 'cancelled'];
        $meth = ['natural' => 'natural', 'ai_fresh' => 'fresh', 'ai_chilled' => 'chilled', 'ai_frozen' => 'frozen', 'embryo_transfer' => 'et', 'icsi' => 'icsi'];
        $d['plan'] = array_map(fn ($r) => ['season' => substr((string) $r['start_date'], 0, 4), 'partner' => (string) $r['partner'], 'method' => $meth[$r['method']] ?? '',
            'date' => (string) $r['start_date'], 'status' => $plan[$r['status']] ?? 'covered', 'notes' => $r['expected_foaling_date'] ? 'Due ' . $r['expected_foaling_date'] : ''],
            DB::all('SELECT b.*, p.name_en AS partner FROM breeding_records b LEFT JOIN horses p ON p.id = ' . ($male ? 'b.mare_id' : 'b.stallion_id') . ' WHERE ' . ($male ? 'b.stallion_id' : 'b.mare_id') . ' = ? AND b.deleted_at IS NULL ORDER BY b.start_date DESC LIMIT 12', [$h['id']]));
        $est = ['fresh' => 'frozen', 'frozen' => 'frozen', 'transferred' => 'transferred', 'pregnant' => 'pregnant', 'foaled' => 'born', 'failed' => 'lost'];
        $d['embryos'] = array_map(fn ($r) => ['date' => (string) $r['flush_date'], 'partner' => (string) $r['partner'], 'method' => 'et', 'stage' => (string) $r['stage'], 'grade' => (string) $r['grade'],
            'status' => $est[$r['status']] ?? 'frozen', 'recipient' => (string) $r['recipient'], 'storage' => (string) $r['storage']],
            DB::all('SELECT e.*, p.name_en AS partner, r.name_en AS recipient, l.value_en AS storage FROM embryos e LEFT JOIN horses p ON p.id = ' . ($male ? 'e.donor_mare_id' : 'e.sire_id') . '
                LEFT JOIN horses r ON r.id = e.recipient_mare_id LEFT JOIN lookups l ON l.id = e.location_id WHERE ' . ($male ? 'e.sire_id' : 'e.donor_mare_id') . ' = ? AND e.deleted_at IS NULL ORDER BY e.flush_date DESC LIMIT 12', [$h['id']]));
        $d['progeny'] = array_map(fn ($r) => ['name' => $r['name_en'], 'year' => substr((string) $r['dob'], 0, 4), 'sex' => self::sex($r['sex'], $r['category']), 'other' => (string) $r['other']],
            DB::all('SELECT c.name_en, c.dob, c.sex, c.category, o.name_en AS other FROM horses c LEFT JOIN horses o ON o.id = ' . ($male ? 'c.dam_id' : 'c.sire_id') . ' WHERE ' . ($male ? 'c.sire_id' : 'c.dam_id') . ' = ? AND c.deleted_at IS NULL ORDER BY c.dob DESC LIMIT 20', [$h['id']]));
        return $d;
    }

    private static function ordinal(int $n): string
    {
        return $n . (in_array($n % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th'));
    }

    /** Diet plan grid: one row per feed, 5 feeding times (s0..s4). */
    private static function dietItems(int $horseId): array
    {
        $slot = ['early_morning' => 's0', 'late_morning' => 's1', 'afternoon' => 's2', 'evening' => 's3', 'late_evening' => 's4'];
        $items = [];
        foreach (DB::all('SELECT p.feeding, p.quantity, i.name_en, i.unit FROM diet_plans p JOIN inventory_items i ON i.id = p.item_id WHERE p.horse_id = ? AND p.deleted_at IS NULL ORDER BY i.name_en', [$horseId]) as $r) {
            $items[$r['name_en']] ??= ['name' => $r['name_en'], 's0' => '', 's1' => '', 's2' => '', 's3' => '', 's4' => ''];
            if (isset($slot[$r['feeding']])) {
                $items[$r['name_en']][$slot[$r['feeding']]] = rtrim(rtrim((string) $r['quantity'], '0'), '.') . ' ' . $r['unit'];
            }
        }
        return array_values($items);
    }

    private static function vetEntries(int $horseId): array
    {
        $map = ['vaccination' => 'vacc', 'deworming' => 'worm', 'farrier' => 'farr', 'dental' => 'dent', 'vet_visit' => 'vet'];
        return array_map(fn ($r) => ['date' => $r['record_date'], 'type' => $map[$r['type']] ?? 'other', 'what' => trim($r['title'] . ($r['details'] ? ' — ' . mb_strimwidth($r['details'], 0, 80, '…') : '')),
            'by' => (string) ($r['vet'] ?: $r['vet_name']), 'next' => (string) $r['next_due_date']],
            DB::all('SELECT r.*, e.name_en AS vet FROM health_records r LEFT JOIN employees e ON e.id = r.vet_employee_id WHERE r.horse_id = ? AND r.deleted_at IS NULL ORDER BY r.record_date DESC LIMIT 40', [$horseId]));
    }

    private static function coverMare(array $m): array
    {
        return ['mare' => $m['name'], 'mareDob' => $m['dob'], 'mareReg' => $m['passport'], 'mareChip' => $m['chip'], 'mareSire' => $m['sire'], 'mareDam' => $m['dam'],
                'ownerEn' => $m['owner'], 'ownerAr' => $m['ownerAr'], 'date' => date('Y-m-d')];
    }

    private static function embryo(array $e): array
    {
        $donor = self::horse((int) $e['donor_mare_id']);
        $sire = self::horse((int) $e['sire_id']);
        $rec = $e['recipient_mare_id'] ? self::horse((int) $e['recipient_mare_id']) : null;
        $owner = $e['owner_type'] === 'client' ? DB::row('SELECT name_en, name_ar FROM parties WHERE id = ?', [$e['owner_party_id']]) : null;
        $checks = DB::all('SELECT c.check_date, c.result, c.notes FROM pregnancy_checks c JOIN breeding_records b ON b.id = c.breeding_record_id WHERE b.embryo_id = ? AND c.deleted_at IS NULL ORDER BY c.check_date', [$e['id']]);
        return [
            'code' => $e['code'], 'name' => (string) $e['name'], 'flushDate' => (string) $e['flush_date'], 'grade' => (string) $e['grade'], 'stage' => (string) $e['stage'], 'status' => $e['status'],
            'storage' => (string) DB::value('SELECT value_en FROM lookups WHERE id = ?', [$e['location_id']]),
            'ownerEn' => $owner ? $owner['name_en'] : 'SK Arabian for Trading', 'ownerAr' => $owner ? (string) $owner['name_ar'] : 'اس كي ارابيان للتجارة',
            'donor' => $donor['name'], 'donorReg' => $donor['passport'], 'donorSire' => $donor['sire'], 'donorDam' => $donor['dam'], 'sire' => $sire['name'], 'sireReg' => $sire['passport'],
            'recipient' => $rec['name'] ?? '', 'recipientReg' => $rec['passport'] ?? '', 'transferDate' => (string) $e['transfer_date'], 'expected' => (string) $e['expected_foaling_date'],
            'checks' => array_map(fn ($c) => ['date' => $c['check_date'], 'result' => in_array($c['result'], ['positive', 'negative'], true) ? $c['result'] : 'inconclusive', 'notes' => (string) $c['notes']], $checks),
            'notes' => (string) $e['notes'], 'date' => date('Y-m-d'),
        ];
    }

    private static function employee(array $e, string $type, bool $sens): array
    {
        $nat = $e['nationality_id'] ? DB::row('SELECT value_en, value_ar FROM lookups WHERE id = ?', [$e['nationality_id']]) : null;
        $pos = $e['position_id'] ? DB::row('SELECT value_en, value_ar FROM lookups WHERE id = ?', [$e['position_id']]) : null;
        $f = $e['gender'] === 'f';
        $hire = $e['hire_date'] ? strtotime($e['hire_date']) : null;
        $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $monthsAr = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        $gross = round((float) $e['basic_salary_qar'] + (float) $e['housing_allowance'] + (float) $e['transport_allowance'] + (float) $e['other_allowance'], 2);
        $d = [
            'gender' => $f ? 'f' : 'm', 'nameEn' => $e['name_en'], 'nameAr' => (string) $e['name_ar'], 'natEn' => (string) ($nat['value_en'] ?? ''), 'natAr' => (string) ($nat['value_ar'] ?? ''),
            'posEn' => (string) ($pos['value_en'] ?? ''), 'posAr' => (string) ($pos['value_ar'] ?? ''), 'qid' => $sens ? (string) Crypto::decrypt($e['qid_no']) : '',
            'qidExp' => $e['qid_expiry'] ? date('d/m/Y', strtotime($e['qid_expiry'])) : '', 'joinDate' => (string) $e['hire_date'], 'date' => date('Y-m-d'),
        ];
        if ($type === 'sal') {
            $d += ['sinceEn' => $hire ? $months[(int) date('n', $hire) - 1] . ' ' . date('Y', $hire) : '', 'sinceAr' => $hire ? $monthsAr[(int) date('n', $hire) - 1] . ' ' . date('Y', $hire) : '',
                   'salary' => $sens ? $gross : ''];
        } elseif ($type === 'letters') {
            $d += ['basic' => $sens ? (float) $e['basic_salary_qar'] : '', 'endDate' => (string) $e['end_date'], 'leaveDays' => (float) $e['leave_balance']];
        } elseif ($type === 'offer') {
            $d += ['honEn' => $f ? 'Ms.' : 'Mr.', 'honAr' => $f ? 'السيدة' : 'السيد', 'passport' => $sens ? (string) Crypto::decrypt($e['passport_no']) : '',
                   'salAmt' => $sens ? $gross : '', 'salCur' => 'QAR', 'salQar' => $sens ? $gross : ''];
        }
        return $d;
    }

    private static function po(array $po): array
    {
        $s = DB::row('SELECT * FROM parties WHERE id = ?', [$po['supplier_id']]);
        $items = DB::all('SELECT l.quantity, l.unit_price, i.name_en, i.name_ar, i.unit FROM purchase_order_items l JOIN inventory_items i ON i.id = l.item_id WHERE l.purchase_order_id = ? ORDER BY l.id', [$po['id']]);
        $sens = Auth::can('finance', 'sensitive');
        return ['no' => $po['number'], 'date' => $po['order_date'], 'delivery' => (string) $po['expected_date'], 'currency' => $po['currency'],
                'supplier' => (string) ($s['name_en'] ?? ''), 'supplierAr' => (string) ($s['name_ar'] ?? ''), 'contact' => (string) ($s['contact_person'] ?? ''),
                'phone' => $sens ? (string) ($s['phone'] ?? '') : '', 'email' => $sens ? (string) ($s['email'] ?? '') : '', 'discount' => 0,
                'items' => array_map(fn ($i) => ['d' => $i['name_en'], 'dAr' => (string) $i['name_ar'], 'q' => (float) $i['quantity'], 'u' => $i['unit'], 'p' => (float) $i['unit_price']], $items)];
    }

    private static function invoice(array $inv): array
    {
        $c = DB::row('SELECT * FROM parties WHERE id = ?', [$inv['party_id']]);
        $sens = Auth::can('finance', 'sensitive');
        $paidQar = $inv['bill_id'] ? (float) DB::value('SELECT paid_qar FROM bills WHERE id = ?', [$inv['bill_id']]) : 0.0;
        return ['mode' => 'invoice', 'no' => $inv['number'], 'date' => $inv['invoice_date'], 'due' => (string) $inv['due_date'], 'currency' => $inv['currency'],
                'cName' => (string) ($c['name_en'] ?? ''), 'cNameAr' => (string) ($c['name_ar'] ?? ''), 'cPhone' => $sens ? (string) ($c['phone'] ?? '') : '',
                'cEmail' => $sens ? (string) ($c['email'] ?? '') : '', 'cAddr' => $sens ? (string) ($c['address'] ?? '') : '', 'discount' => 0,
                'paid' => (float) $inv['exchange_rate'] > 0 ? round($paidQar / (float) $inv['exchange_rate'], 2) : 0,
                'items' => array_map(fn ($i) => ['d' => $i['description'], 'q' => (float) $i['quantity'], 'p' => (float) $i['unit_price']],
                    DB::all('SELECT description, quantity, unit_price FROM invoice_items WHERE invoice_id = ? ORDER BY id', [$inv['id']]))];
    }

    /** Receipt voucher for money received (a payment on an income bill / invoice). */
    private static function receipt(array $pay, array $bill): array
    {
        $c = $bill['party_id'] ? DB::row('SELECT * FROM parties WHERE id = ?', [$bill['party_id']]) : null;
        $method = strtolower((string) DB::value('SELECT value_en FROM lookups WHERE id = ?', [$pay['payment_method_id']]));
        $inv = $bill['invoice_id'] ? (string) DB::value('SELECT number FROM invoices WHERE id = ?', [$bill['invoice_id']]) : '';
        return ['mode' => 'receipt', 'rno' => '', 'date' => $pay['payment_date'], 'currency' => 'QAR', 'rAmount' => (float) $pay['amount_qar'],
                'cName' => (string) ($c['name_en'] ?? ''), 'cNameAr' => (string) ($c['name_ar'] ?? ''),
                'rFor' => $inv !== '' ? 'Invoice ' . $inv : (string) ($bill['description'] ?: $bill['number']), 'rForAr' => $inv !== '' ? 'الفاتورة رقم ' . $inv : '',
                'rMethod' => str_contains($method, 'cheque') ? 'cheque' : (str_contains($method, 'card') ? 'card' : (str_contains($method, 'transfer') ? 'transfer' : 'cash')),
                'rRef' => (string) $pay['reference_no'], 'rBank' => (string) DB::value('SELECT bank_name FROM accounts WHERE id = ?', [$pay['account_id']])];
    }

    /** Payslips: one line, or every line of a payroll run with the summary page. */
    private static function payslip(array $lineIds, bool $summary = false): array
    {
        if (!$lineIds) {
            return [];
        }
        $p = [];
        $in = DB::in(array_map('intval', $lineIds), 'l', $p);
        $rows = DB::all("SELECT l.*, r.period, e.name_en, e.name_ar, pos.value_en AS pos, pos.value_ar AS pos_ar FROM payroll_lines l JOIN payroll_runs r ON r.id = l.payroll_run_id
            JOIN employees e ON e.id = l.employee_id LEFT JOIN lookups pos ON pos.id = e.position_id WHERE l.id IN $in ORDER BY e.name_en", $p);
        $paid = array_filter(array_column($rows, 'paid_at'));
        return ['month' => $rows[0]['period'] ?? date('Y-m'), 'payDate' => $paid ? substr((string) max($paid), 0, 10) : '', 'summary' => $summary ? 'yes' : 'no',
                'staff' => array_map(fn ($r) => ['name' => $r['name_en'], 'nameAr' => (string) $r['name_ar'], 'pos' => (string) $r['pos'], 'posAr' => (string) $r['pos_ar'],
                    'basic' => (float) $r['basic_qar'], 'allow' => (float) $r['allowances_qar'], 'ot' => (float) $r['overtime_qar'], 'ded' => (float) $r['deductions_qar'], 'adv' => (float) $r['advances_qar']], $rows)];
    }

    // ------------------------------------------------------------------ lists for the list documents

    /** Data for "All horses", "All employees", ID cards, reminders and the register. */
    public static function context(string $type): array
    {
        $ctx = [];
        if (in_array($type, ['horses'], true) && Auth::can('horses')) {
            $ctx['horses'] = array_map(fn ($h) => ['name' => $h['name_en'], 'nameAr' => (string) $h['name_ar'], 'sex' => self::sex($h['sex'], $h['category']), 'dob' => (string) $h['dob'],
                'sire' => (string) $h['sire'], 'dam' => (string) $h['dam'], 'chip' => (string) $h['microchip'], 'box' => (string) $h['box']],
                DB::all("SELECT h.*, s.name_en AS sire, d.name_en AS dam, l.value_en AS box FROM horses h LEFT JOIN horses s ON s.id = h.sire_id LEFT JOIN horses d ON d.id = h.dam_id
                    LEFT JOIN lookups l ON l.id = h.location_id WHERE h.deleted_at IS NULL AND h.archived_at IS NULL AND h.is_external = 0 AND h.status <> 'deceased' ORDER BY h.name_en"));
        }
        if (in_array($type, ['staff', 'idcard'], true) && Auth::can('hr')) {
            $sens = Auth::can('hr', 'sensitive');
            $ctx['employees'] = array_map(fn ($e) => ['nameEn' => $e['name_en'], 'nameAr' => (string) $e['name_ar'], 'posEn' => (string) $e['pos'], 'posAr' => (string) $e['pos_ar'],
                'natEn' => (string) $e['nat'], 'qid' => $sens ? (string) Crypto::decrypt($e['qid_no']) : '', 'qidExp' => (string) $e['qid_expiry'], 'joinDate' => (string) $e['hire_date'],
                'empNo' => (string) $e['emp_no'], 'blood' => (string) $e['blood_group'], 'emergency' => (string) $e['emergency_contact'], 'status' => $e['status'] === 'on_leave' ? 'leave' : 'active',
                'photo' => $e['photo_id'] ? url('/portal/files/' . (int) $e['photo_id']) : ''],
                DB::all("SELECT e.*, p.value_en AS pos, p.value_ar AS pos_ar, n.value_en AS nat FROM employees e LEFT JOIN lookups p ON p.id = e.position_id LEFT JOIN lookups n ON n.id = e.nationality_id
                    WHERE e.deleted_at IS NULL AND e.archived_at IS NULL AND e.status <> 'left' ORDER BY e.name_en"));
        }
        if ($type === 'rem') {
            $ctx['reminders'] = Reminders::all(180);
        }
        if ($type === 'reg') {
            $ctx['register'] = array_map(fn ($d) => ['at' => str_replace(' ', 'T', $d['created_at']), 'doc' => $d['doc_type'], 'ref' => $d['ref_no'], 'date' => $d['doc_date'], 'title' => $d['title']],
                DB::all('SELECT * FROM documents WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 500'));
        }
        return $ctx;
    }

    // ------------------------------------------------------------------ archive

    /** Saves (issues) a document: reference number, verification token, archive and Documents tab of the record. */
    public static function create(string $type, array $data, string $lang, ?string $recordType, ?int $recordId, ?int $userId = null): int
    {
        $code = StudioDocs::TYPES[$type]['code'];
        $date = (isset($data['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data['date'])) ? $data['date'] : date('Y-m-d');
        $ref = Sequence::document($code, $date);
        // The document's own number fields show the archive reference unless a business number was filled in (PO, invoice)
        foreach (['ref', 'no', 'rno'] as $k) {
            if (array_key_exists($k, $data) && trim((string) $data[$k]) === '' && !($type === 'inv' && $k === 'no' && ($data['mode'] ?? '') === 'receipt') && !($type === 'inv' && $k === 'rno' && ($data['mode'] ?? '') !== 'receipt')) {
                $data[$k] = $ref;
            }
        }
        $tpl = StudioTemplates::get($type);
        $id = DB::insert('documents', [
            'ref_no' => $ref, 'doc_type' => $type, 'lang' => $lang === 'ar' ? 'ar' : 'en', 'title' => mb_substr(self::title($type, $data), 0, 200),
            'record_type' => $recordType, 'record_id' => $recordId, 'doc_date' => $date, 'letterhead_version' => (int) ($data['_letterhead'] ?? $tpl['letterhead']),
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'verify_token' => bin2hex(random_bytes(16)), 'created_by' => $userId ?? Auth::id(),
        ]);
        Audit::log('create', 'studio', 'document', $id, null, ['ref' => $ref, 'type' => $type, 'record' => $recordType ? $recordType . '#' . $recordId : null], $ref);
        return $id;
    }

    public static function title(string $type, array $d): string
    {
        $subject = match ($type) {
            'profile' => $d['nameEn'] ?? '', 'diet', 'vet' => $d['horse'] ?? '', 'cover' => trim(($d['mare'] ?? '') . ' × ' . ($d['stallion'] ?? ''), ' ×'),
            'board' => $d['ownerEn'] ?? '', 'embryo' => $d['code'] ?? '', 'sal', 'letters', 'offer' => $d['nameEn'] ?? '',
            'po' => trim(($d['no'] ?? '') . ' — ' . ($d['supplier'] ?? ''), ' —'), 'inv' => ($d['mode'] ?? '') === 'receipt' ? ($d['cName'] ?? '') : trim(($d['no'] ?? '') . ' — ' . ($d['cName'] ?? ''), ' —'),
            'pay' => ($d['month'] ?? '') . (count($d['staff'] ?? []) === 1 ? ' — ' . ($d['staff'][0]['name'] ?? '') : ''),
            'fin' => $d['titleEn'] ?? '', 'letter' => $d['subjectEn'] ?? '', default => '',
        };
        $name = __('studio.type_' . $type);
        return $subject !== '' ? $name . ' — ' . $subject : $name;
    }

    /**
     * Creates a document without the editor (payslips when a payroll is approved). The stored data is the
     * template wording plus the record values; the renderer fills the rest from the template defaults.
     */
    public static function generateFromRecord(string $type, int $recordId, ?int $userId = null): ?int
    {
        $kind = self::SOURCES[$type][0] ?? null;
        [$data, $rt, $rid] = self::prefill($type, $kind, $recordId);
        if (!$data || !$rt) {
            return null;
        }
        $data = array_merge(StudioTemplates::get($type)['defaults'], $data, ['lang' => 'en', '_server' => 1]);
        return self::create($type, $data, 'en', $rt, $rid, $userId);
    }
}
