<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\DB;
use App\Core\ValidationException;
use App\Services\FileStore;
use App\Services\HorseService;

/** News & events shown on the website (show participations and results). */
class News extends Resource
{
    public string $key = 'news';
    public string $table = 'news';
    public string $module = 'cms';
    public string $recordType = 'news';
    public string $title = 'nav.news';
    public string $singular = 'news.singular';
    public array $search = ['t.title_en', 't.title_ar', 't.summary_en'];
    public string $sort = 't.published_at DESC, t.id DESC';

    public function fields(): array
    {
        return [
            'title_en'     => ['type' => 'text', 'label' => 'news.title_en', 'required' => true, 'max' => 200, 'col' => 6],
            'title_ar'     => ['type' => 'text', 'label' => 'news.title_ar', 'max' => 200, 'col' => 6, 'attrs' => ['dir' => 'rtl']],
            'summary_en'   => ['type' => 'textarea', 'label' => 'news.summary_en', 'max' => 400, 'rows' => 2, 'col' => 6],
            'summary_ar'   => ['type' => 'textarea', 'label' => 'news.summary_ar', 'max' => 400, 'rows' => 2, 'col' => 6, 'attrs' => ['dir' => 'rtl']],
            'body_en'      => ['type' => 'textarea', 'label' => 'news.body_en', 'rows' => 8, 'col' => 6, 'max' => 20000],
            'body_ar'      => ['type' => 'textarea', 'label' => 'news.body_ar', 'rows' => 8, 'col' => 6, 'max' => 20000, 'attrs' => ['dir' => 'rtl']],
            'photo'        => ['type' => 'file', 'label' => 'news.photo', 'accept' => 'image/*', 'col' => 6, 'help' => 'news.photo_help'],
            'show_id'      => ['type' => 'picker', 'source' => 'shows', 'label' => 'news.show', 'col' => 6],
            'published'    => ['type' => 'checkbox', 'label' => 'news.published', 'col' => 6],
            'published_at' => ['type' => 'date', 'label' => 'news.published_at', 'col' => 6, 'default' => fn () => date('Y-m-d'), 'help' => 'news.published_at_help'],
        ];
    }

    public function formValue(string $name, array $f, ?array $row): mixed
    {
        $v = parent::formValue($name, $f, $row);
        return $name === 'published_at' && is_string($v) ? substr($v, 0, 10) : $v;
    }

    public function columns(): array
    {
        return [
            'title'     => ['label' => 'news.title_en', 'sql' => 't.title_en', 'sort' => true],
            'date'      => ['label' => 'news.published_at', 'sql' => 't.published_at', 'fmt' => 'date', 'sort' => true],
            'published' => ['label' => 'news.published', 'sql' => 't.published', 'fmt' => 'bool'],
        ];
    }

    public function filters(): array
    {
        return ['published' => ['type' => 'bool', 'label' => 'news.published', 'sql' => 't.published']];
    }

    public function label(array $row): string
    {
        return (string) $row['title_en'];
    }

    public function prepare(array $data, ?array $old): array
    {
        if (!empty($data['published_at']) && strlen((string) $data['published_at']) === 10) {
            $data['published_at'] .= ' 09:00:00';
        }
        if ($data['published'] && empty($data['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }
        if (!$old || $old['title_en'] !== $data['title_en']) {
            $base = slugify($data['title_en']) ?: 'news';
            $slug = $base;
            for ($i = 2; DB::value('SELECT id FROM news WHERE slug = ? AND id <> ?', [$slug, $old['id'] ?? 0]); $i++) {
                $slug = $base . '-' . $i;
            }
            $data['slug'] = $slug;
        }
        return $data;
    }

    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        if (!empty($_FILES['photo']['name']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $fid = FileStore::store($_FILES['photo'], 'news', $id, 'photo', $data['title_en'], true);
            DB::update('news', ['image_id' => $fid], 'id = :id', ['id' => $id]);
        }
        \App\Services\Cache::bump();
    }

    public function afterDetails(array $row): string
    {
        $img = $row['image_id'] ? '<img src="' . e(url('/portal/files/' . (int) $row['image_id'] . '/thumb')) . '" alt="" style="max-width:320px;border-radius:10px">' : '';
        $link = $row['published'] ? ' <a class="btn btn-sm" target="_blank" href="' . e(site_url('news/' . $row['slug'])) . '">' . e(__('cms.view_on_site')) . '</a>' : '';
        return '<section class="card">' . $img . $link . '</section>';
    }

    public function tabs(array $row): array
    {
        return array_intersect_key($this->standardTabs($row), ['history' => 1]);
    }
}
