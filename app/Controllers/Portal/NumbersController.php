<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Services\NumberRegister;

/** Number register: every document number issued, and what it is now (read-only). */
class NumbersController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        if (!NumberRegister::visibleTypes()) {
            Auth::deny('numbers.view');
        }
        $seq = (string) Request::query('seq', '');
        $period = (string) Request::query('period', '');
        $counters = NumberRegister::counters();
        $rows = [];
        foreach ($counters as $c) {
            $nums = NumberRegister::numbers($c['seq'], $c['period']);
            $count = array_count_values(array_column($nums, 'state'));
            $rows[] = $c + ['count' => $count, 'first' => $nums[0]['no'] ?? '', 'last_no' => $nums ? $nums[count($nums) - 1]['no'] : ''];
        }
        $detail = $seq !== '' ? NumberRegister::numbers($seq, $period) : null;
        $this->view('portal/finance/numbers', [
            'title' => __('nav.numbers'), 'rows' => $rows, 'detail' => $detail, 'seq' => $seq, 'period' => $period,
            'duplicates' => NumberRegister::duplicates(),
        ]);
    }
}
