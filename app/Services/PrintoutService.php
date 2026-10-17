<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Crypto;
use App\Core\DB;
use App\Core\Lang;
use App\Core\Request;
use App\Core\View;
use App\Resources\ListQuery;
use App\Resources\Resource;

/**
 * Printed lists and reports (the "PDF" buttons). Each printout gets a reference number (SKA-PRT-2026-0001) and a QR
 * code on every page; scanning it opens /verify/{token}, which confirms the printout is genuine, and staff allowed to
 * see the module can open the original copy in the portal (/portal/printouts/{token}). What was printed is kept
 * compressed with an HMAC fingerprint, so the kept copy can be shown to be unchanged.
 *
 * A printout's content ("doc") is plain data, the same for the first print and for the kept copy:
 *   title, period?, meta [[label, value]], cards [[label, value, negative]], count, capped, confidential, by, at,
 *   sections [[title?, note?, cols [[label, align s|e, kind]], rows [[cell]], totals?]]
 * A cell is a string, or ['v' => text, 'c' => css class], ['img' => file id] (photo) or ['sw' => '#hex'] (colour).
 */
final class PrintoutService
{
    /** Kept copies larger than this (compressed) are not stored; the reference and fingerprint still are. */
    private const MAX_SNAPSHOT = 2 * 1024 * 1024;

    /** True once the database update that adds printouts (migration 016) has been applied. */
    public static function available(): bool
    {
        static $ok = null;
        return $ok ??= (bool) DB::value("SHOW TABLES LIKE 'printouts'");
    }

    /** True once printouts can be voided (migration 017). */
    public static function hasVoid(): bool
    {
        static $ok = null;
        return $ok ??= self::available() && DB::hasColumn('printouts', 'is_void');
    }

    /**
     * SQL conditions (table alias p) for the printouts this user may see and open — the same rule as canOpen():
     * their own, or what printing it needed (plus "view sensitive" when it had such columns).
     */
    public static function visibleWhere(array &$params): array
    {
        if (Auth::isOwner()) {
            return ['1 = 1'];
        }
        // only the placeholders the SQL uses: an unused one makes the query fail (HY093)
        $params['pr_me1'] = (int) Auth::id();
        if (Auth::horseScopeAssigned()) {
            return ['p.printed_by = :pr_me1'];
        }
        $params['pr_me2'] = (int) Auth::id();
        $or = ['p.printed_by = :pr_me1'];
        $export = array_values(array_filter(Auth::MODULES, fn ($m) => Auth::can($m, 'export')));
        if ($export) {
            $or[] = "(p.kind = 'list' AND p.module IN " . DB::in($export, 'pr_ex', $params) . ')';
        }
        if (Auth::can('reports', 'print')) {
            $or[] = "p.kind = 'report'";
        }
        if (Auth::can('semen_stock', 'print') || Auth::can('semen_stock', 'export')) {
            $or[] = "p.kind = 'semen_report'";
        }
        $sensitive = array_values(array_filter(Auth::MODULES, fn ($m) => Auth::can($m, 'sensitive')));
        return ['(' . implode(' OR ', $or) . ')',
            '(p.has_sensitive = 0 OR p.printed_by = :pr_me2' . ($sensitive ? ' OR p.module IN ' . DB::in($sensitive, 'pr_se', $params) : '') . ')'];
    }

    /**
     * Records the printout; returns its stamp (reference, QR...) for the pages, or null before the database update
     * (the printout is then made without a reference, as before).
     */
    public static function issue(string $kind, string $module, array $doc, bool $sensitive, ?string $sourceUrl): ?array
    {
        if (!self::available()) {
            return null;
        }
        $json = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $gz = gzcompress($json, 6);
        $token = bin2hex(random_bytes(16));
        $ref = Sequence::document('PRT', substr($doc['at'], 0, 10));
        DB::insert('printouts', [
            'ref_no' => $ref, 'verify_token' => $token, 'kind' => $kind, 'module' => $module, 'title' => mb_substr($doc['title'], 0, 200),
            'source_url' => $sourceUrl !== null ? mb_substr($sourceUrl, 0, 1000) : null, 'row_count' => (int) $doc['count'],
            'fingerprint' => Crypto::hmac($json), 'snapshot' => strlen($gz) <= self::MAX_SNAPSHOT ? $gz : null,
            'has_sensitive' => $sensitive ? 1 : 0, 'lang' => Lang::current(), 'printed_by' => Auth::id(), 'printed_at' => $doc['at'],
        ]);
        return self::stamp($ref, $token, $doc['at']);
    }

    /** What every page shows: the reference, when it was printed and the QR code of the verification address. */
    public static function stamp(string $ref, string $token, string $at): array
    {
        $verify = absolute_url('/verify/' . $token);
        return ['ref' => $ref, 'token' => $token, 'verifyUrl' => $verify, 'qr' => QrCode::svg($verify, 4, '#1d2433'), 'at' => $at];
    }

    public static function find(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token) || !self::available()) {
            return null;
        }
        return DB::row('SELECT p.*, u.name AS printed_by_name FROM printouts p LEFT JOIN users u ON u.id = p.printed_by WHERE p.verify_token = ?', [$token]);
    }

    /** The kept copy and whether it still matches its fingerprint: [doc|null, intact]. */
    public static function content(array $p): array
    {
        if ($p['snapshot'] === null) {
            return [null, false];
        }
        $json = @gzuncompress((string) $p['snapshot']);
        if ($json === false) {
            return [null, false];
        }
        return [json_decode($json, true) ?: null, hash_equals((string) $p['fingerprint'], Crypto::hmac($json))];
    }

    /** Who may open the kept copy: whoever printed it, or anyone with the permission printing it needed (and
     * "view sensitive" when it had such columns). Staff limited to their assigned horses only see their own. */
    public static function canOpen(array $p): bool
    {
        if (!Auth::check()) {
            return false;
        }
        if ((int) $p['printed_by'] === Auth::id()) {
            return true;
        }
        if (Auth::horseScopeAssigned()) {
            return false;
        }
        $ok = match ($p['kind']) {
            'report' => Auth::can('reports', 'print'),
            'semen_report' => Auth::can('semen_stock', 'print') || Auth::can('semen_stock', 'export'),
            default => Auth::can($p['module'], 'export'),
        };
        return $ok && (!$p['has_sensitive'] || Auth::can($p['module'], 'sensitive'));
    }

    /** Prints the doc on letterhead pages (or a real PDF when mPDF is installed). */
    public static function output(array $doc, ?array $stamp, array $extra = []): never
    {
        $html = View::render('portal/print/doc', ['title' => ($stamp ? $stamp['ref'] . ' — ' : '') . $doc['title'], 'doc' => $doc, 'stamp' => $stamp] + $extra, 'print');
        Pdf::outputOrPrint($html, preg_replace('/[^A-Za-z0-9\-]+/', '-', ($stamp['ref'] ?? '') . '-' . slugify($doc['title'])) . '.pdf');
    }

    /** A new, empty printout content. */
    public static function doc(string $title): array
    {
        return ['title' => $title, 'period' => null, 'meta' => [], 'cards' => [], 'sections' => [], 'count' => 0, 'capped' => false,
            'confidential' => false, 'by' => (string) (Auth::user()['name'] ?? ''), 'at' => date('Y-m-d H:i:s')];
    }

    /** The page it was printed from, without the print request itself (to open the list again later). */
    public static function sourceUrl(): string
    {
        $qs = $_GET;
        unset($qs['export'], $qs['page'], $qs['format'], $qs['autoprint']);
        return Request::path() . ($qs ? '?' . http_build_query($qs) : '');
    }

    // ------------------------------------------------------------------ lists

    /** A list (any Resource) as printed: its visible columns, the filtered rows, its totals and its filters in words. */
    public static function fromList(Resource $res, ListQuery $q, array $cols, array $rows, int $max): array
    {
        $doc = self::doc(__($res->title));
        $doc['meta'] = self::filtersMeta($res, $q, $cols);
        $doc['confidential'] = (bool) array_filter($cols, fn ($c) => !empty($c['sensitive']));
        $totals = $res->listTotals($q);
        $sec = ['title' => null, 'note' => null, 'cols' => [], 'rows' => [], 'totals' => null];
        foreach ($cols as $c) {
            $fmt = $c['fmt'] ?? 'text';
            $label = __($c['label']);
            if ($fmt === 'money' && !self::saysCurrency($label)) {
                $label .= ' (QAR)';
            }
            $sec['cols'][] = [$label, in_array($fmt, ['money', 'num'], true) ? 'e' : 's', match ($fmt) { 'thumb' => 'photo', 'swatch' => 'swatch', default => '' }];
        }
        // photo columns: only pictures can be printed (a PDF's "thumbnail" is the PDF itself)
        $ids = [];
        foreach ($cols as $key => $c) {
            if (($c['fmt'] ?? '') === 'thumb') {
                foreach ($rows as $r) {
                    if (!empty($r[$key])) {
                        $ids[(int) $r[$key]] = true;
                    }
                }
            }
        }
        $pictures = [];
        if ($ids) {
            $params = [];
            $pictures = array_flip(DB::column("SELECT id FROM files WHERE (thumb_name IS NOT NULL OR mime LIKE 'image/%') AND id IN " . DB::in(array_keys($ids), 'f', $params), $params));
        }
        foreach ($rows as $r) {
            $line = [];
            foreach ($cols as $key => $c) {
                $v = $r[$key] ?? null;
                $line[] = ($c['fmt'] ?? '') === 'thumb' && !isset($pictures[(int) $v]) ? '' : self::listCell($v, $c);
            }
            $sec['rows'][] = $line;
        }
        if ($totals && $rows) {
            $line = [];
            $i = 0;
            foreach ($cols as $key => $c) {
                $line[] = isset($totals[$key]) ? self::listCell($totals[$key], ['fmt' => ($c['fmt'] ?? '') === 'num' ? 'num' : 'money']) : ($i === 0 ? __('common.total') : '');
                $i++;
            }
            $sec['totals'] = $line;
        }
        $doc['sections'][] = $sec;
        $doc['count'] = count($rows);
        $doc['capped'] = count($rows) >= $max;
        return $doc;
    }

    /** One list cell as printed: like the screen (dates, ages, labels, expiry notes), photos as small pictures. */
    public static function listCell(mixed $v, array $c): string|array
    {
        $fmt = $c['fmt'] ?? 'text';
        if (($v === null || $v === '') && $fmt !== 'bool') {
            return '';
        }
        return match ($fmt) {
            'money'    => ['v' => number_format((float) $v, 2), 'c' => (float) $v < 0 ? 'neg' : ''],
            'num'      => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.'),
            'date'     => fmt_date((string) $v),
            'datetime' => fmt_date((string) $v, true),
            'expiry'   => self::expiry((string) $v),
            'bool'     => $v ? '✓' : '',
            'badge', 'enum' => enum_label($c['prefix'] ?? 'status', (string) $v),
            'lang'     => __((string) $v),
            'lookup'   => lookup_label($c['lookup'] ?? '', (string) $v),
            'thumb'    => ['img' => (int) $v],
            'age'      => horse_age((string) $v),
            'swatch'   => ['sw' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $v) ? (string) $v : '#8a8f98'],
            default    => mb_strimwidth((string) $v, 0, 400, '…'),
        };
    }

    private static function expiry(string $date): string|array
    {
        $d = days_until($date);
        $txt = fmt_date($date);
        if ($d !== null && $d < 0) {
            return ['v' => $txt . ' (' . __('common.expired') . ')', 'c' => 'exp-bad'];
        }
        if ($d !== null && $d <= 60) {
            return ['v' => $txt . ' (' . __('common.days_left', ['n' => $d]) . ')', 'c' => 'exp-warn'];
        }
        return $txt;
    }

    /** The search, filters and sort order of a list in words ("Category: Mare", "Owner: SK horses"...). */
    public static function filtersMeta(Resource $res, ListQuery $q, array $cols): array
    {
        $out = [];
        $filters = $res->filters();
        foreach ($q->activeFilters as $key => $v) {
            if ($key === 'q') {
                $out[] = [__('common.search'), (string) $v];
                continue;
            }
            $f = $filters[$key] ?? null;
            if (!$f || is_array($v)) {
                continue;
            }
            $v = (string) $v;
            $value = match ($f['type']) {
                'bool' => __($v === '1' ? 'common.yes' : 'common.no'),
                'archived', 'trashed' => __('common.yes'),
                'date_from', 'date_to' => fmt_date($v),
                'lookup' => loc(DB::row('SELECT value_en, value_ar FROM lookups WHERE id = ?', [$v]), 'value') ?: $v,
                'picker' => Pickers::label($f['source'], $v) ?? $v,
                'text' => $v,
                default => opt_label((is_callable($f['options'] ?? null) ? ($f['options'])() : ($f['options'] ?? []))[$v] ?? $v),
            };
            $out[] = [__($f['label']), $value];
        }
        $sort = (string) ($q->input['sort'] ?? '');
        if ($sort !== '' && isset($cols[$sort]) && !empty($cols[$sort]['sort'])) {
            $out[] = [__('print.sorted_by'), __($cols[$sort]['label']) . (strtolower((string) ($q->input['dir'] ?? 'asc')) === 'desc' ? ' ↓' : ' ↑')];
        }
        return $out;
    }

    // ------------------------------------------------------------------ reports

    /** A report (ReportService::build) as printed: the period, the summary figures and each section. */
    public static function fromReport(array $r): array
    {
        $doc = self::doc($r['title']);
        $doc['hide_count'] = true; // a report's lines are not "records"
        if (empty($r['no_period'])) {
            $doc['period'] = fmt_date($r['period']['from']) . ' – ' . fmt_date($r['period']['to']);
        }
        foreach ($r['cards'] as $card) {
            $doc['cards'][] = [(string) $card[0], number_format((float) $card[1], 2) . ' QAR', (float) $card[1] < 0];
        }
        foreach ($r['sections'] as $s) {
            $sec = ['title' => $s['title'] ?? null, 'note' => $s['note'] ?? null, 'cols' => [], 'rows' => [], 'totals' => null];
            foreach ($s['columns'] as [$label, $fmt]) {
                $label = __($label);
                if ($fmt === 'money' && !self::saysCurrency($label)) {
                    $label .= ' (QAR)';
                }
                $sec['cols'][] = [$label, $fmt === 'text' ? 's' : 'e', ''];
            }
            foreach ($s['rows'] as $row) {
                $line = [];
                foreach ($s['columns'] as $k => [, $fmt]) {
                    $line[] = self::reportCell($row[$k] ?? '', $fmt);
                }
                $sec['rows'][] = $line;
            }
            if ($s['rows'] && !empty($s['totals'])) {
                $line = [];
                foreach ($s['columns'] as $k => [, $fmt]) {
                    $line[] = self::reportCell($s['totals'][$k] ?? '', $fmt);
                }
                $sec['totals'] = $line;
            }
            $doc['count'] += count($s['rows']);
            $doc['sections'][] = $sec;
        }
        return $doc;
    }

    /** Whether a money column's heading already names the currency ("Amount (QAR)", "المبلغ (ريال)"). */
    private static function saysCurrency(string $label): bool
    {
        return (bool) preg_match('/QAR|ريال|ر\.?\s?ق/u', $label);
    }

    public static function reportCell(mixed $v, string $fmt): string|array
    {
        if ($v === null || $v === '') {
            return '';
        }
        return match ($fmt) {
            'money' => ['v' => number_format((float) $v, 2), 'c' => (float) $v < 0 ? 'neg' : ''],
            'num'   => rtrim(rtrim(number_format((float) $v, 2), '0'), '.'),
            'pct'   => number_format((float) $v, 1) . '%',
            default => (string) $v,
        };
    }
}
