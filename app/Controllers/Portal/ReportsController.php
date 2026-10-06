<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Lang;
use App\Core\Response;
use App\Core\View;
use App\Services\Pdf;
use App\Services\ReportService;
use App\Services\Xlsx;

class ReportsController extends Controller
{
    public function index(): void
    {
        Auth::requirePerm('reports', 'view');
        $types = array_filter(ReportService::types(), fn ($t) => ($t[2])());
        $this->view('portal/reports/index', ['title' => __('nav.reports'), 'types' => $types]);
    }

    public function show(string $type): void
    {
        Auth::requirePerm('reports', 'view');
        $types = ReportService::types();
        if (!isset($types[$type])) {
            Response::notFound();
        }
        if (!($types[$type][2])()) {
            Auth::deny('reports.' . $type);
        }
        $r = ReportService::build($type, $_GET);
        $export = (string) ($_GET['export'] ?? '');
        if ($export === 'xlsx' || $export === 'pdf') {
            Auth::requirePerm('reports', $export === 'xlsx' ? 'export' : 'print');
            Audit::log($export === 'xlsx' ? 'export' : 'print', 'reports', 'report', null, null, ['report' => $type, 'from' => $r['period']['from'], 'to' => $r['period']['to']], $r['title']);
            $export === 'xlsx' ? $this->xlsx($r) : $this->pdf($r);
        }
        $this->view('portal/reports/show', ['title' => $r['title'], 'r' => $r]);
    }

    /** All sections in one sheet: a title row, the header row, the rows and the totals of each section. */
    private function xlsx(array $r): never
    {
        $width = max(array_map(fn ($s) => count($s['columns']), $r['sections']));
        $out = [];
        $out[] = array_pad([$r['title'] . (empty($r['no_period']) ? ' — ' . $r['period']['from'] . ' → ' . $r['period']['to'] : '')], $width, '');
        foreach ($r['cards'] as [$label, $amount]) {
            $out[] = array_pad([$label, round((float) $amount, 2)], $width, '');
        }
        foreach ($r['sections'] as $s) {
            $out[] = array_fill(0, $width, '');
            $out[] = array_pad([$s['title']], $width, '');
            $out[] = array_pad(array_map(fn ($c) => __($c[0]), array_values($s['columns'])), $width, '');
            foreach (array_merge($s['rows'], [$s['totals']]) as $row) {
                $line = [];
                foreach ($s['columns'] as $k => $c) {
                    $v = $row[$k] ?? '';
                    $line[] = in_array($c[1], ['money', 'num', 'pct'], true) && $v !== '' ? round((float) $v, 2) : (string) $v;
                }
                $out[] = array_pad($line, $width, '');
            }
        }
        $headers = array_shift($out);
        $file = preg_replace('/[^A-Za-z0-9\-]+/', '-', slugify($r['title'])) . '-' . date('Ymd') . '.xlsx';
        Response::download(Xlsx::build($headers, $out, mb_substr($r['title'], 0, 31), Lang::isRtl()), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $file);
    }

    private function pdf(array $r): never
    {
        $html = View::render('portal/reports/print', ['title' => $r['title'], 'r' => $r], 'print');
        Pdf::outputOrPrint($html, preg_replace('/[^A-Za-z0-9\-]+/', '-', slugify($r['title'])) . '-' . date('Ymd') . '.pdf');
    }
}
