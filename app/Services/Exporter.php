<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Lang;
use App\Core\Response;
use App\Resources\ListQuery;
use App\Resources\Resource;

/**
 * Excel and PDF export of any list (only for roles with "export"). Sensitive columns are dropped unless the role
 * has "view sensitive". Every export is written to the Activity Log with the filters and row count; a printed list
 * gets a reference number and verification QR code (PrintoutService).
 */
final class Exporter
{
    public const MAX_ROWS = 20000;

    public static function resourceList(Resource $res, ListQuery $q, string $format): never
    {
        if (!Auth::can($res->module, 'export')) {
            Auth::deny($res->module . '.export');
        }
        $rows = $q->rows(self::MAX_ROWS, 0);
        $cols = array_filter($res->visibleColumns(), fn ($c) => empty($c['no_export'])); // helper columns of the page itself stay out
        $title = __($res->title);
        if ($format === 'xlsx') {
            $cols = array_filter($cols, fn ($c) => ($c['fmt'] ?? '') !== 'thumb'); // a photo has no place in a cell
            $headers = array_map(fn ($c) => __($c['label']), array_values($cols));
            $data = [];
            foreach ($rows as $r) {
                $line = [];
                foreach ($cols as $key => $c) {
                    $line[] = self::plain($r[$key] ?? null, $c);
                }
                $data[] = $line;
            }
            $totals = [];
            if ($rows && ($t = $res->listTotals($q))) {
                foreach (array_keys($cols) as $i => $key) {
                    $totals[] = isset($t[$key]) ? round((float) $t[$key], 2) : ($i === 0 ? __('common.total') : '');
                }
            }
            Audit::log('export', $res->module, $res->recordType, null, null, ['format' => 'xlsx', 'rows' => count($data), 'filters' => $q->activeFilters], $title . ' (' . count($data) . ')');
            self::output($title, $headers, $data, 'xlsx', [], $totals);
        }
        $doc = PrintoutService::fromList($res, $q, $cols, $rows, self::MAX_ROWS);
        $stamp = PrintoutService::issue('list', $res->module, $doc, $doc['confidential'], PrintoutService::sourceUrl());
        Audit::log('export', $res->module, $res->recordType, null, null, ['format' => 'pdf', 'rows' => $doc['count'], 'filters' => $q->activeFilters, 'ref' => $stamp['ref'] ?? null],
            $title . ' (' . $doc['count'] . ')' . ($stamp ? ' — ' . $stamp['ref'] : ''));
        PrintoutService::output($doc, $stamp);
    }

    /** Sends a table as .xlsx, or as a printable page on letterhead (without a reference: no module to verify against). */
    public static function output(string $title, array $headers, array $rows, string $format, array $filters = [], array $totals = []): never
    {
        if ($format === 'xlsx') {
            if ($totals) {
                $rows[] = $totals;
            }
            $file = preg_replace('/[^A-Za-z0-9\-]+/', '-', slugify($title)) . '-' . date('Ymd-His');
            Response::download(Xlsx::build($headers, $rows, mb_substr($title, 0, 31), Lang::isRtl()), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $file . '.xlsx');
        }
        $cell = fn ($v) => is_float($v) ? ['v' => number_format($v, 2), 'c' => $v < 0 ? 'neg' : ''] : (string) $v;
        $doc = PrintoutService::doc($title);
        $doc['meta'] = array_map(fn ($k, $v) => [(string) $k, is_scalar($v) ? (string) $v : ''], array_keys($filters), $filters);
        $doc['sections'][] = ['title' => null, 'note' => null, 'cols' => array_map(fn ($h) => [(string) $h, 's', ''], $headers),
            'rows' => array_map(fn ($r) => array_map($cell, array_values($r)), $rows), 'totals' => $totals ? array_map($cell, $totals) : null];
        $doc['count'] = count($rows);
        PrintoutService::output($doc, null);
    }

    /** A cell for Excel: numbers stay numbers, dates stay sortable (YYYY-MM-DD), labels in the user's language. */
    public static function plain(mixed $v, array $c): string|float
    {
        if ($v === null) {
            return '';
        }
        return match ($c['fmt'] ?? 'text') {
            'money'  => round((float) $v, 2),
            'num'    => !is_numeric($v) ? (string) $v : ((float) $v == (int) $v ? (string) (int) $v : (float) $v), // whole numbers stay whole
            'bool'   => $v ? __('common.yes') : __('common.no'),
            'badge', 'enum' => enum_label($c['prefix'] ?? 'status', (string) $v),
            'date', 'expiry' => (string) $v,
            'lang'   => __((string) $v),
            'lookup' => lookup_label($c['lookup'] ?? '', (string) $v),
            'age'    => horse_age((string) $v),
            default  => (string) $v,
        };
    }
}
