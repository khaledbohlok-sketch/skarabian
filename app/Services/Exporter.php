<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Lang;
use App\Core\Response;
use App\Core\View;
use App\Resources\ListQuery;
use App\Resources\Resource;

/**
 * Excel and PDF export of any list (only for roles with "export"). Sensitive columns are dropped unless the role
 * has "view sensitive". Every export is written to the Activity Log with the filters and row count.
 */
final class Exporter
{
    public const MAX_ROWS = 20000;

    public static function resourceList(Resource $res, ListQuery $q, string $format): never
    {
        if (!Auth::can($res->module, 'export')) {
            Auth::deny($res->module . '.export');
        }
        $cols = $res->visibleColumns();
        $rows = $q->rows(self::MAX_ROWS, 0);
        $headers = array_map(fn ($c) => __($c['label']), array_values($cols));
        $data = [];
        foreach ($rows as $r) {
            $line = [];
            foreach ($cols as $key => $c) {
                $line[] = self::plain($r[$key] ?? null, $c);
            }
            $data[] = $line;
        }
        $title = __($res->title);
        Audit::log('export', $res->module, $res->recordType, null, null, ['format' => $format, 'rows' => count($data), 'filters' => $q->activeFilters], $title . ' (' . count($data) . ')');
        self::output($title, $headers, $data, $format, $q->activeFilters);
    }

    /** Sends a table as .xlsx or as a printable/PDF page on letterhead. */
    public static function output(string $title, array $headers, array $rows, string $format, array $filters = [], array $totals = []): never
    {
        $file = preg_replace('/[^A-Za-z0-9\-]+/', '-', slugify($title)) . '-' . date('Ymd-His');
        if ($format === 'xlsx') {
            if ($totals) {
                $rows[] = $totals;
            }
            Response::download(Xlsx::build($headers, $rows, mb_substr($title, 0, 31), Lang::isRtl()), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $file . '.xlsx');
        }
        $html = View::render('portal/print/table', ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'filters' => $filters, 'totals' => $totals], 'print');
        Pdf::outputOrPrint($html, $file . '.pdf');
    }

    public static function plain(mixed $v, array $c): string|float
    {
        if ($v === null) {
            return '';
        }
        return match ($c['fmt'] ?? 'text') {
            'money'  => round((float) $v, 2),
            'bool'   => $v ? __('common.yes') : __('common.no'),
            'badge', 'enum' => __(($c['prefix'] ?? 'status') . '.' . $v),
            'date', 'expiry' => (string) $v,
            'lang'   => __((string) $v),
            default  => (string) $v,
        };
    }
}
