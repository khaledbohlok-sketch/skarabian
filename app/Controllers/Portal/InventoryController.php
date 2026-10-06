<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Resources\Registry;
use App\Services\InventoryService;
use App\Services\Money;
use App\Services\Pickers;

class InventoryController extends Controller
{
    /** Stock in, stock out (optionally used on a horse: the cost goes to that horse) and stock count adjustment. */
    public function stock(string $id): void
    {
        Auth::requirePerm('inventory', 'create');
        $res = Registry::get('items');
        $item = $res->find((int) $id);
        if (!$item) {
            \App\Core\Response::notFound();
        }
        if (Request::isPost()) {
            $dir = (string) Request::post('direction');
            $qty = Money::parse(Request::post('quantity'));
            $price = Money::parse(Request::post('unit_price'));
            $horse = (int) Request::post('horse_id') ?: null;
            $date = (string) Request::post('date');
            $errors = [];
            if (!in_array($dir, ['in', 'out', 'adjust'], true)) {
                $errors['direction'] = __('validation.invalid');
            }
            if ($qty === null || $qty < 0 || ($dir !== 'adjust' && $qty <= 0)) {
                $errors['quantity'] = __('validation.number');
            }
            if ($price !== null && $price < 0) {
                $errors['unit_price'] = __('validation.min', ['min' => 0]);
            }
            if ($horse && ($dir !== 'out' || !Pickers::valid('horses', $horse))) {
                $errors['horse_id'] = __('validation.pick_from_list');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) {
                $errors['date'] = __('validation.date');
            }
            if (!$errors) {
                try {
                    InventoryService::move((int) $item['id'], $dir, (float) $qty, $dir === 'in' ? $price : null, $horse, mb_substr((string) Request::post('note'), 0, 255) ?: null, $date);
                    $this->flash('success', __('items.stock_saved'));
                    $this->redirect('/portal/items/' . $item['id'] . '?tab=movements');
                } catch (\DomainException $e) {
                    $errors['quantity'] = $e->getMessage();
                }
            }
            Session::errors($errors);
            Session::keepOld($_POST);
            $this->flash('error', __('validation.fix_errors'));
            $this->redirect('/portal/items/' . $item['id'] . '/stock');
        }
        $this->view('portal/inventory/stock', ['title' => __('items.stock_move') . ': ' . $item['name_en'], 'item' => $item]);
    }
}
