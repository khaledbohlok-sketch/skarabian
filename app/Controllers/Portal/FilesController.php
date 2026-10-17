<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\Approvals;
use App\Services\FileStore;
use App\Services\TrashService;

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
        // "Show on the website" needs the same rights as the website switch on an existing file (togglePublic)
        $public = (bool) Request::post('is_public') && (Auth::can('horses', 'edit') || Auth::can('cms', 'edit'));
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
                    $fid = FileStore::store($one, $type, $ownerId, $category, Request::post('title') ?: null, $public);
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

    /**
     * The small picture of a file: its thumbnail (or the photo itself). A PDF, a video, or a file that is not on the
     * server gets a picture of its type instead — an <img> pointing here never shows as a broken image.
     */
    public function thumb(string $id): void
    {
        $f = $this->file($id);
        foreach ([[(string) $f['thumb_name'], 'image/webp'], [(string) $f['stored_name'], (string) $f['mime']]] as [$name, $mime]) {
            if ($name !== '' && str_starts_with($mime, 'image/') && is_file(FileStore::path($name))) {
                Response::file(FileStore::path($name), $mime, null, true);
            }
        }
        $missing = !is_file(FileStore::path((string) $f['stored_name']));
        Response::image(FileStore::typeIcon((string) $f['mime'], (string) $f['original_name'], $missing), 'image/svg+xml');
    }

    /** The Owner deletes at once (to the Trash); anyone else's deletion waits for the Owner's approval, and so does the
     * Owner's own when a second approver is required (as for records, TrashService::delete). */
    public function delete(string $id): void
    {
        $f = $this->file($id);
        if (!FileStore::canUpload($f['owner_type'])) {
            Auth::deny('files.delete');
        }
        if (Auth::isOwner() && !(Approvals::ownerSecond() && Approvals::otherOwner())) {
            TrashService::deleteFile((int) $f['id']);
            $this->flash('success', __('files.deleted'));
        } else {
            TrashService::requestDeletion('file', (int) $f['id'], $f['original_name'],
                ['kind' => 'file', 'module' => FileStore::OWNERS[$f['owner_type']][0] ?? 'documents', 'url' => '/portal/files/' . $f['id']]);
            $this->flash('success', __('approvals.delete_requested'));
        }
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
        if (!$f['is_public'] && !in_array($f['category'], FileStore::PUBLIC_CATEGORIES, true)) {
            $this->flash('error', __('files.public_photo_video_only'));
            $this->back();
        }
        DB::update('files', ['is_public' => $f['is_public'] ? 0 : 1], 'id = :id', ['id' => $f['id']]);
        Audit::log('update', 'cms', $f['owner_type'], $f['owner_id'], ['is_public' => $f['is_public']], ['is_public' => $f['is_public'] ? 0 : 1], 'Website visibility of file');
        $this->back();
    }
}
