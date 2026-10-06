<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\FileStore;

/** Files are only ever served through here, after a login + permission check. */
class FilesController extends Controller
{
    private function file(string $id): array
    {
        Auth::requireLogin();
        $f = DB::row('SELECT * FROM files WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$f) {
            Response::notFound();
        }
        if (!FileStore::canView($f)) {
            Auth::deny('files.view');
        }
        return $f;
    }

    public function upload(): void
    {
        Auth::requireLogin();
        $type = (string) Request::post('owner_type');
        $ownerId = (int) Request::post('owner_id');
        $category = (string) Request::post('category', 'document');
        if (!FileStore::canUpload($type)) {
            Auth::deny('files.upload.' . $type);
        }
        if ($type === 'horse' && !Auth::canSeeHorse($ownerId)) {
            Auth::deny('files.upload.horse_scope');
        }
        $files = $_FILES['files'] ?? null;
        $count = 0;
        $errors = [];
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $name) {
                if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $one = ['name' => $name, 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                try {
                    $fid = FileStore::store($one, $type, $ownerId, $category, Request::post('title') ?: null, (bool) Request::post('is_public'));
                    $count++;
                    if ($category === 'photo' && $type === 'horse' && !DB::value('SELECT main_photo_id FROM horses WHERE id = ?', [$ownerId])) {
                        DB::update('horses', ['main_photo_id' => $fid], 'id = :id', ['id' => $ownerId]);
                    }
                    if ($category === 'photo' && $type === 'employee' && !DB::value('SELECT photo_id FROM employees WHERE id = ?', [$ownerId])) {
                        DB::update('employees', ['photo_id' => $fid], 'id = :id', ['id' => $ownerId]);
                    }
                } catch (\DomainException $e) {
                    $errors[] = $name . ': ' . $e->getMessage();
                }
            }
        }
        if (Request::wantsJson()) {
            Response::json(['uploaded' => $count, 'errors' => $errors]);
        }
        if ($count) {
            $this->flash('success', __('files.uploaded', ['n' => $count]));
        }
        foreach ($errors as $err) {
            $this->flash('error', $err);
        }
        if (!$count && !$errors) {
            $this->flash('error', __('files.none_selected'));
        }
        $this->back('/portal');
    }

    public function show(string $id): void
    {
        $f = $this->file($id);
        $download = Request::query('download') === '1';
        if ($f['is_sensitive'] || $download) {
            Audit::log($download ? 'download' : 'view_file', FileStore::OWNERS[$f['owner_type']][0] ?? null, $f['owner_type'], $f['owner_id'], null, ['file_id' => $f['id'], 'name' => $f['original_name']], $f['original_name']);
        }
        Response::file(FileStore::path($f['stored_name']), $f['mime'], $download ? $f['original_name'] : null);
    }

    public function thumb(string $id): void
    {
        $f = $this->file($id);
        $path = FileStore::path($f['thumb_name'] ?: $f['stored_name']);
        Response::file($path, $f['thumb_name'] ? 'image/webp' : $f['mime'], null, true);
    }

    public function delete(string $id): void
    {
        $f = $this->file($id);
        if (!FileStore::canUpload($f['owner_type'])) {
            Auth::deny('files.delete');
        }
        DB::update('files', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $f['id']]);
        if ($f['owner_type'] === 'horse') {
            DB::run('UPDATE horses SET main_photo_id = NULL WHERE main_photo_id = ?', [$f['id']]);
        }
        Audit::log('delete_file', FileStore::OWNERS[$f['owner_type']][0] ?? null, $f['owner_type'], $f['owner_id'], ['file_id' => $f['id'], 'name' => $f['original_name']], null, $f['original_name']);
        $this->flash('success', __('files.deleted'));
        $this->back();
    }

    public function makeMain(string $id): void
    {
        $f = $this->file($id);
        if (!FileStore::canUpload($f['owner_type']) || $f['category'] !== 'photo') {
            Auth::deny('files.main');
        }
        if ($f['owner_type'] === 'horse') {
            DB::update('horses', ['main_photo_id' => $f['id']], 'id = :id', ['id' => $f['owner_id']]);
        } elseif ($f['owner_type'] === 'employee') {
            DB::update('employees', ['photo_id' => $f['id']], 'id = :id', ['id' => $f['owner_id']]);
        }
        Audit::log('update', FileStore::OWNERS[$f['owner_type']][0], $f['owner_type'], $f['owner_id'], null, ['main_photo' => $f['id']], 'Main photo changed');
        $this->back();
    }

    public function togglePublic(string $id): void
    {
        $f = $this->file($id);
        if (!(Auth::can('horses', 'edit') || Auth::can('cms', 'edit')) || $f['is_sensitive']) {
            Auth::deny('files.public');
        }
        DB::update('files', ['is_public' => $f['is_public'] ? 0 : 1], 'id = :id', ['id' => $f['id']]);
        Audit::log('update', 'cms', $f['owner_type'], $f['owner_id'], ['is_public' => $f['is_public']], ['is_public' => $f['is_public'] ? 0 : 1], 'Website visibility of file');
        $this->back();
    }
}
