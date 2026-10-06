<?php
use App\Core\View;

if (isset($t['render'])) {
    echo ($t['render'])($row);
} elseif (isset($t['related'])) {
    echo View::partial('portal/crud/related', ['parent' => $res, 'row' => $row, 'relKey' => $t['related'], 'filter' => $t['filter'] ?? [], 'note' => $t['note'] ?? null]);
} elseif (($t['type'] ?? '') === 'documents') {
    echo View::partial('portal/partials/documents', ['recordType' => $res->recordType, 'recordId' => (int) $row['id'], 'module' => $res->module, 'categories' => $t['categories'] ?? null, 'canUpload' => $t['canUpload'] ?? null, 'studio' => $t['studio'] ?? []]);
} elseif (($t['type'] ?? '') === 'timeline') {
    echo View::partial('portal/partials/timeline', ['items' => \App\Services\Timeline::for($res->recordType, (int) $row['id'])]);
} elseif (($t['type'] ?? '') === 'history') {
    echo View::partial('portal/partials/history', ['recordType' => $res->recordType, 'recordId' => (int) $row['id']]);
}
