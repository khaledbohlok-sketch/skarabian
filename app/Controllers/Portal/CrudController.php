<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\View;
use App\Resources\ListQuery;
use App\Resources\Registry;
use App\Resources\Resource;
use App\Services\Cache;
use App\Services\Exporter;
use App\Services\Pickers;
use App\Services\TrashService;

/**
 * Generic list / create / show / edit / delete / export for every Resource.
 * Permissions are checked here on the server for every action.
 */
class CrudController extends Controller
{
    protected function resource(string $key): Resource
    {
        Auth::requireLogin();
        $res = Registry::get($key);
        if (!$res) {
            Response::notFound();
        }
        return $res;
    }

    protected function load(Resource $res, int $id): array
    {
        $row = $res->find($id);
        if (!$row) {
            // Exists but outside the user's record scope (e.g. a horse not assigned to this groom): deny and log
            $raw = $res->find($id, true);
            if ($raw && ($raw['deleted_at'] ?? null) === null) {
                Auth::deny($res->module . '.scope');
            }
            Response::notFound();
        }
        if (!$res->canView($row)) {
            Auth::deny($res->module . '.view');
        }
        return $row;
    }

    public function index(string $key): void
    {
        $res = $this->resource($key);
        if (!$res->canList()) {
            Auth::deny($res->module . '.view');
        }
        $q = new ListQuery($res, $_GET);
        $export = Request::query('export');
        if ($export === 'xlsx' || $export === 'pdf') {
            Exporter::resourceList($res, $q, $export);
        }
        $total = $q->count();
        $rows = $q->rows();
        $this->view('portal/crud/index', [
            'res' => $res, 'rows' => $rows, 'total' => $total, 'q' => $q,
            'title' => __($res->title),
        ]);
    }

    public function create(string $key): void
    {
        $res = $this->resource($key);
        if (!$res->canCreate()) {
            Auth::deny($res->module . '.create');
        }
        $this->renderForm($res, null);
    }

    public function store(string $key): void
    {
        $res = $this->resource($key);
        if (!$res->canCreate()) {
            Auth::deny($res->module . '.create');
        }
        try {
            $id = $this->save($res, null);
        } catch (ValidationException $e) {
            Session::keepOld($_POST);
            Session::errors($e->errors);
            $this->flash('error', __('validation.fix_errors'));
            Response::redirect($res->url(null, '/create') . ($_GET ? '?' . http_build_query($_GET) : ''));
        } catch (\DomainException $e) {
            Session::keepOld($_POST);
            $this->flash('error', $e->getMessage());
            Response::redirect($res->url(null, '/create') . ($_GET ? '?' . http_build_query($_GET) : ''));
        }
        if (Request::query('popup')) {
            $row = $res->find($id);
            Response::html(View::render('portal/crud/popup_done', ['id' => $id, 'label' => $res->label($row ?? ['id' => $id])], 'popup'));
        }
        $this->flash('success', __('common.saved'));
        $saved = $res->find($id) ?? [];
        Response::redirect($res->redirectAfterSave($id, $saved));
    }

    public function show(string $key, string $id): void
    {
        $res = $this->resource($key);
        $row = $this->load($res, (int) $id);
        $sensitive = array_filter($res->fields(), fn ($f) => !empty($f['sensitive']));
        if ($sensitive && $res->canSensitive()) {
            $shown = array_filter(array_intersect_key($row, $sensitive), fn ($v) => $v !== null && $v !== '');
            if ($shown) {
                Audit::log('view_sensitive', $res->module, $res->recordType, $row['id'], null, ['fields' => array_keys($shown)], $res->label($row));
            }
        }
        $tabs = $res->tabs($row);
        $tab = (string) Request::query('tab', 'details');
        $this->view($res->showView ?? 'portal/crud/show', [
            'res' => $res, 'row' => $row, 'tabs' => $tabs, 'tab' => $tab, 'title' => $res->label($row),
        ]);
    }

    public function edit(string $key, string $id): void
    {
        $res = $this->resource($key);
        $row = $this->load($res, (int) $id);
        if (!$res->canEdit($row)) {
            Auth::deny($res->module . '.edit');
        }
        $this->renderForm($res, $row);
    }

    public function update(string $key, string $id): void
    {
        $res = $this->resource($key);
        $row = $this->load($res, (int) $id);
        if (!$res->canEdit($row)) {
            Auth::deny($res->module . '.edit');
        }
        try {
            $this->save($res, $row);
        } catch (ValidationException $e) {
            Session::keepOld($_POST);
            Session::errors($e->errors);
            $this->flash('error', __('validation.fix_errors'));
            Response::redirect($res->url((int) $id, '/edit'));
        } catch (\DomainException $e) {
            Session::keepOld($_POST);
            $this->flash('error', $e->getMessage());
            Response::redirect($res->url((int) $id, '/edit'));
        }
        $this->flash('success', __('common.saved'));
        Response::redirect($res->redirectAfterSave((int) $id, $res->find((int) $id) ?? $row));
    }

    public function delete(string $key, string $id): void
    {
        $res = $this->resource($key);
        $row = $this->load($res, (int) $id);
        if (!$res->canDelete($row)) {
            Auth::deny($res->module . '.delete');
        }
        try {
            $result = TrashService::delete($res, $row);
            $this->flash('success', __($result === 'approval' ? 'approvals.delete_requested' : 'common.moved_to_trash'));
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
            Response::redirect($res->url((int) $id));
        }
        if ($res->parentField && !empty($row[$res->parentField]) && $res->parentResource) {
            Response::redirect('/portal/' . $res->parentResource . '/' . $row[$res->parentField] . '?tab=' . $res->key);
        }
        Response::redirect($res->url());
    }

    public function archive(string $key, string $id): void
    {
        $res = $this->resource($key);
        $row = $this->load($res, (int) $id);
        if (!$res->archivable || !Auth::can($res->module, 'delete')) {
            Auth::deny($res->module . '.delete');
        }
        $value = $row['archived_at'] ? null : date('Y-m-d H:i:s');
        DB::update($res->table, ['archived_at' => $value], 'id = :id', ['id' => $row['id']]);
        Audit::log($value ? 'archive' : 'unarchive', $res->module, $res->recordType, $row['id'], null, null, $res->label($row));
        Cache::bump();
        $this->flash('success', __($value ? 'common.archived' : 'common.unarchived'));
        Response::redirect($res->url((int) $id));
    }

    protected function renderForm(Resource $res, ?array $row): never
    {
        $layout = Request::query('popup') ? 'popup' : 'portal';
        Response::html(View::render($res->formView ?? 'portal/crud/form', [
            'res' => $res, 'row' => $row,
            'title' => $row ? __('common.edit') . ': ' . $res->label($row) : __('common.new') . ' ' . __($res->singular),
        ], $layout));
    }

    /** Validates, stores, logs. Returns the record id. */
    protected function save(Resource $res, ?array $old): int
    {
        $data = $res->collect($_POST, $old);
        $data = $res->prepare($data, $old);
        return DB::transaction(function () use ($res, $data, $old) {
            $now = date('Y-m-d H:i:s');
            if ($old === null) {
                if ($res->hasCreatedBy) {
                    $data['created_by'] = Auth::id();
                }
                $id = DB::insert($res->table, $data);
                $res->afterSave($id, $data, null, true);
                $new = DB::row('SELECT * FROM `' . $res->table . '` WHERE id = ?', [$id]) ?? $data;
                Audit::log('create', $res->module, $res->recordType, $id, null, $new, $res->label($new));
            } else {
                $id = (int) $old['id'];
                if (DB::hasColumn($res->table, 'updated_at')) {
                    $data['updated_at'] = $now;
                }
                DB::update($res->table, $data, 'id = :id', ['id' => $id]);
                $res->afterSave($id, $data, $old, false);
                [$o, $n] = \App\Core\Audit::diff($old, $data);
                if ($n) {
                    Audit::log('update', $res->module, $res->recordType, $id, $o, $n, $res->label(array_merge($old, $data)));
                }
            }
            Cache::bump();
            return $id;
        });
    }

    /** GET /portal/api/picker/{source}?q= — searchable dropdown data. */
    public function picker(string $source): void
    {
        Auth::requireLogin();
        if (!Pickers::allowed($source)) {
            Auth::deny('picker.' . $source);
        }
        $filters = array_diff_key($_GET, ['q' => 1]);
        Response::json(['results' => Pickers::search($source, (string) Request::query('q', ''), $filters)]);
    }
}
