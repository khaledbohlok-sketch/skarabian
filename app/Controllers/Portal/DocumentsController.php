<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\Cache;
use App\Services\FileStore;

/**
 * Multi-file upload for the Documents & Photos library. Each uploaded file becomes its own
 * media_documents row (metadata) plus a files row (the actual stored file, via FileStore).
 * Everything else (list, filters, export, edit metadata, archive, delete) goes through the
 * generic CrudController, same as every other resource.
 */
class DocumentsController extends Controller
{
    public function uploadForm(): void
    {
        Auth::requireLogin();
        if (!Auth::can('documents', 'create')) {
            Auth::deny('documents.create');
        }
        Response::html(\App\Core\View::render('portal/documents/upload', [
            'title' => __('documents.upload_title'), 'scripts' => ['js/documents-upload.js'],
        ], 'popup'));
    }

    public function upload(): void
    {
        Auth::requireLogin();
        if (!Auth::can('documents', 'create')) {
            Auth::deny('documents.create');
        }
        $categoryId = (int) Request::post('category_id') ?: null;
        if ($categoryId && !DB::value('SELECT 1 FROM document_categories WHERE id = ?', [$categoryId])) {
            $categoryId = null;
        }
        $notes = trim((string) Request::post('notes', ''));
        $docDate = (string) Request::post('doc_date', '');
        $docDate = valid_date($docDate) ? $docDate : null;
        $sharedTitle = trim((string) Request::post('title', ''));
        $maxBytes = (int) Config::get('documents.max_upload_mb', 10) * 1024 * 1024;

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
                    if (($one['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK && $one['size'] > $maxBytes) {
                        throw new \DomainException(__('files.too_big', ['mb' => (int) Config::get('documents.max_upload_mb', 10)]));
                    }
                    $title = $sharedTitle !== '' ? $sharedTitle : pathinfo((string) $name, PATHINFO_FILENAME);
                    DB::transaction(function () use ($one, $title, $categoryId, $notes, $docDate, &$count) {
                        $id = DB::insert('media_documents', [
                            'title' => mb_substr($title, 0, 200), 'category_id' => $categoryId,
                            'notes' => $notes !== '' ? $notes : null, 'doc_date' => $docDate,
                            'created_by' => Auth::id(),
                        ]);
                        $fid = FileStore::store($one, 'document', $id, 'document', null, false);
                        DB::update('media_documents', ['file_id' => $fid], 'id = :id', ['id' => $id]);
                        $row = DB::row('SELECT * FROM media_documents WHERE id = ?', [$id]);
                        Audit::log('create', 'documents', 'media_document', $id, null, $row, $title);
                        $count++;
                    });
                } catch (\DomainException $e) {
                    $errors[] = $name . ': ' . $e->getMessage();
                }
            }
        }
        Cache::bump();
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
        $this->redirect('/portal/documents');
    }
}
