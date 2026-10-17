<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;

/** Manageable categories for the Documents & Photos library (Certificates, Vet reports, Contracts...). */
class DocumentCategories extends Resource
{
    public string $key = 'document-categories';
    public string $table = 'document_categories';
    public string $module = 'documents';
    public string $recordType = 'document_category';
    public string $title = 'document_categories.title';
    public string $singular = 'document_categories.singular';
    public array $search = ['t.name_en', 't.name_ar'];
    public string $sort = 't.sort, t.name_en';
    public int $perPage = 100;
    public bool $hasCreatedBy = false;

    public function fields(): array
    {
        return [
            'name_en' => ['type' => 'text', 'label' => 'categories.name_en', 'required' => true, 'max' => 100, 'col' => 6, 'no_phone' => true],
            'name_ar' => ['type' => 'text', 'label' => 'categories.name_ar', 'max' => 100, 'col' => 6, 'attrs' => ['dir' => 'rtl']],
            'sort'    => ['type' => 'int', 'label' => 'categories.sort', 'col' => 3, 'empty' => 0],
            'active'  => ['type' => 'checkbox', 'label' => 'common.active', 'default' => 1, 'col' => 3],
        ];
    }

    public function columns(): array
    {
        return [
            'name_en' => ['label' => 'categories.name_en', 'sql' => 't.name_en', 'sort' => true],
            'name_ar' => ['label' => 'categories.name_ar', 'sql' => 't.name_ar'],
            'docs'    => ['label' => 'document_categories.title', 'sql' => '(SELECT COUNT(*) FROM media_documents m WHERE m.category_id = t.id AND m.deleted_at IS NULL)', 'fmt' => 'num'],
            'active'  => ['label' => 'common.active', 'sql' => 't.active', 'fmt' => 'bool'],
        ];
    }

    public function label(array $row): string
    {
        return (string) $row['name_en'];
    }

    public function links(): array
    {
        return [['media_documents', 'category_id', 'document_categories.title']];
    }

    public function canCreate(): bool
    {
        return Auth::can('documents', 'edit');
    }

    public function canEdit(array $row): bool
    {
        return Auth::can('documents', 'edit');
    }

    public function canDelete(array $row): bool
    {
        return Auth::can('documents', 'edit');
    }

    public function prepare(array $data, ?array $old): array
    {
        $data['name_en'] = preg_replace('/\s+/', ' ', trim($data['name_en']));
        return $data;
    }
}
