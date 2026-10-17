<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;

/**
 * Documents & Photos (المستندات والصور): a standalone library for uploaded PDFs and photos — not
 * attached to a horse, embryo or any other record. The file itself is stored and streamed through
 * the existing files table / FileStore / FilesController (owner_type "document"), so it gets the same
 * random file names, MIME sniffing, WebP re-encoding and thumbnails, and permission-gated streaming
 * as every other upload in the system. This resource only holds the searchable metadata.
 */
class Documents extends Resource
{
    public string $key = 'documents';
    public string $table = 'media_documents';
    public string $module = 'documents';
    public string $recordType = 'media_document';
    public string $title = 'nav.documents';
    public string $singular = 'documents.singular';
    public array $search = ['t.title', 't.notes', 'f.original_name'];
    public string $sort = 't.id DESC';
    public bool $archivable = true;
    public ?string $indexView = 'portal/documents/index';

    public static function categoryOptions(): array
    {
        $o = [];
        foreach (DB::all('SELECT id, name_en, name_ar FROM document_categories WHERE active = 1 ORDER BY sort, name_en') as $c) {
            $o[$c['id']] = loc($c, 'name');
        }
        return $o;
    }

    public function fields(): array
    {
        return [
            'title'       => ['type' => 'text', 'label' => 'documents.doc_title', 'required' => true, 'max' => 200, 'col' => 8],
            'category_id' => ['type' => 'select', 'label' => 'documents.category', 'options' => fn () => self::categoryOptions(), 'col' => 4],
            'doc_date'    => ['type' => 'date', 'label' => 'documents.doc_date', 'col' => 4],
            'notes'       => ['type' => 'textarea', 'label' => 'common.notes', 'rows' => 3, 'col' => 12],
        ];
    }

    public function from(): string
    {
        return '`media_documents` t LEFT JOIN document_categories c ON c.id = t.category_id LEFT JOIN files f ON f.id = t.file_id LEFT JOIN users u ON u.id = t.created_by';
    }

    public function columns(): array
    {
        return [
            'thumb'    => ['label' => 'documents.file', 'sql' => 't.file_id', 'fmt' => 'thumb'],
            'title'    => ['label' => 'documents.doc_title', 'sql' => 't.title', 'sort' => true],
            'category' => ['label' => 'documents.category', 'sql' => 'COALESCE(' . self::nameSql('c') . ', \'\')'], // the category in the user's language
            'type'     => ['label' => 'documents.file_type', 'sql' => "CASE WHEN f.mime = 'application/pdf' THEN 'documents.type_pdf' WHEN f.mime LIKE 'image/%' THEN 'documents.type_image' ELSE '' END", 'fmt' => 'lang'],
            'doc_date' => ['label' => 'documents.doc_date', 'sql' => 't.doc_date', 'fmt' => 'date', 'sort' => true],
            'uploader' => ['label' => 'documents.uploaded_by', 'sql' => 'u.name'],
            'created'  => ['label' => 'common.uploaded_at', 'sql' => 't.created_at', 'fmt' => 'date', 'sort' => true],
        ];
    }

    public function filters(): array
    {
        return [
            'category_id' => ['type' => 'select', 'label' => 'documents.category', 'sql' => 't.category_id', 'options' => fn () => self::categoryOptions()],
            'file_type'   => ['type' => 'raw', 'label' => 'documents.file_type', 'options' => ['pdf' => 'documents.type_pdf', 'image' => 'documents.type_image'], 'sqlFor' => ['pdf' => "f.mime = 'application/pdf'", 'image' => "f.mime LIKE 'image/%'"]],
            'uploaded_by' => ['type' => 'picker', 'source' => 'users', 'label' => 'documents.uploaded_by', 'sql' => 't.created_by'],
            'from'        => ['type' => 'date_from', 'label' => 'common.from', 'sql' => 't.doc_date'],
            'to'          => ['type' => 'date_to', 'label' => 'common.to', 'sql' => 't.doc_date'],
            'archived'    => ['type' => 'archived', 'label' => 'common.show_archived'],
        ];
    }

    public function label(array $row): string
    {
        return (string) $row['title'];
    }

    public function links(): array
    {
        return [];
    }

    public function prepare(array $data, ?array $old): array
    {
        $data['category_id'] = ($data['category_id'] ?? '') !== '' ? (int) $data['category_id'] : null;
        return $data;
    }

    /** Creation only happens through the multi-file upload endpoint (DocumentsController::upload). */
    public function canCreate(): bool
    {
        return false;
    }

    public function listActions(): array
    {
        return Auth::can('documents', 'edit') ? [['document_categories.manage', '/portal/document-categories', '']] : [];
    }

    public function afterDetails(array $row): string
    {
        if (empty($row['file_id'])) {
            return '';
        }
        $f = DB::row('SELECT mime, original_name, size_bytes FROM files WHERE id = ?', [$row['file_id']]);
        if (!$f) {
            return '';
        }
        // the photo (or a picture of the file's type), what the file is, and Open / Download
        $url = url('/portal/files/' . (int) $row['file_id']);
        $image = str_starts_with((string) $f['mime'], 'image/');
        $size = (int) $f['size_bytes'];
        $meta = (string) $f['original_name'] . ($size ? ' · ' . ($size >= 1048576 ? number_format($size / 1048576, 1) . ' MB' : max(1, (int) round($size / 1024)) . ' KB') : '');
        return '<section class="card doc-file"><a class="doc-file-preview' . ($image ? ' is-image' : '') . '" href="' . e($url) . '" target="_blank" rel="noopener"><img src="' . e($image ? $url : $url . '/thumb') . '" alt=""></a>'
            . '<div class="doc-file-meta"><p class="muted small" dir="auto">' . e($meta) . '</p><div class="actions"><a class="btn btn-primary" href="' . e($url) . '" target="_blank" rel="noopener">' . icon('doc') . ' ' . e(__('documents.open_file')) . '</a>'
            . '<a class="btn" href="' . e($url . '?download=1') . '">' . icon('download') . ' ' . e(__('common.download')) . '</a></div></div></section>';
    }
}
