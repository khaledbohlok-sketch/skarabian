<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\DB;
use App\Core\ValidationException;
use App\Services\FileStore;

/** Website gallery: photos (uploaded here) or videos (YouTube / Instagram link), optionally linked to a horse. */
class Gallery extends Resource
{
    public string $key = 'gallery';
    public string $table = 'gallery_items';
    public string $module = 'cms';
    public string $recordType = 'gallery';
    public string $title = 'nav.gallery';
    public string $singular = 'gallery.singular';
    public array $search = ['t.caption_en', 't.caption_ar', 'h.name_en'];
    public string $sort = 't.sort, t.id DESC';

    public function fields(): array
    {
        return [
            'photo'      => ['type' => 'file', 'label' => 'gallery.photo', 'accept' => 'image/*', 'col' => 6, 'help' => 'gallery.photo_help'],
            'video_url'  => ['type' => 'url', 'label' => 'gallery.video_url', 'col' => 6, 'help' => 'gallery.video_help'],
            'horse_id'   => ['type' => 'picker', 'source' => 'horses', 'label' => 'bills.horse', 'col' => 6],
            'sort'       => ['type' => 'int', 'label' => 'categories.sort', 'col' => 3, 'empty' => 0],
            'published'  => ['type' => 'checkbox', 'label' => 'news.published', 'default' => 1, 'col' => 3],
            'caption_en' => ['type' => 'text', 'label' => 'gallery.caption_en', 'max' => 200, 'col' => 6],
            'caption_ar' => ['type' => 'text', 'label' => 'gallery.caption_ar', 'max' => 200, 'col' => 6, 'attrs' => ['dir' => 'rtl']],
        ];
    }

    public function from(): string
    {
        return '`gallery_items` t LEFT JOIN horses h ON h.id = t.horse_id';
    }

    public function columns(): array
    {
        return [
            'thumb'     => ['label' => 'gallery.photo', 'sql' => 't.file_id', 'fmt' => 'thumb'],
            'caption'   => ['label' => 'gallery.caption_en', 'sql' => "COALESCE(t.caption_en, t.video_url)"],
            'horse'     => ['label' => 'bills.horse', 'sql' => 'h.name_en'],
            'sort'      => ['label' => 'categories.sort', 'sql' => 't.sort', 'fmt' => 'num', 'sort' => true],
            'published' => ['label' => 'news.published', 'sql' => 't.published', 'fmt' => 'bool'],
        ];
    }

    public function label(array $row): string
    {
        return (string) ($row['caption_en'] ?: __('gallery.singular') . ' #' . $row['id']);
    }

    public function prepare(array $data, ?array $old): array
    {
        $hasFile = !empty($_FILES['photo']['name']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if (!$hasFile && empty($data['video_url']) && empty($old['file_id'])) {
            throw ValidationException::one('photo', 'gallery.need_media');
        }
        if (!empty($data['video_url']) && !preg_match('#^https://(www\.)?(youtube\.com|youtu\.be|instagram\.com|vimeo\.com)/#i', $data['video_url'])) {
            throw ValidationException::one('video_url', 'gallery.video_sites');
        }
        return $data;
    }

    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        if (!empty($_FILES['photo']['name']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $fid = FileStore::store($_FILES['photo'], 'cms', $id, 'photo', $data['caption_en'] ?? null, true);
            DB::update('gallery_items', ['file_id' => $fid], 'id = :id', ['id' => $id]);
        }
        \App\Services\Cache::bump();
    }

    public function afterDetails(array $row): string
    {
        return $row['file_id'] ? '<section class="card"><img src="' . e(url('/portal/files/' . (int) $row['file_id'])) . '" alt="" style="max-width:100%;max-height:420px;border-radius:10px"></section>' : '';
    }
}
