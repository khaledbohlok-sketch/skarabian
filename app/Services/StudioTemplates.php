<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/**
 * Owner-editable template settings for SK Arabian Studio documents: default wording (terms, clauses,
 * declarations, greetings...) and the letterhead version. The built-in wording is the original Studio's
 * (public/assets/js/studio-render.js defaults()); values saved here override it for new documents.
 */
final class StudioTemplates
{
    public static function seed(): void
    {
        foreach (array_keys(StudioDocs::TYPES) as $type) {
            DB::run('INSERT IGNORE INTO studio_templates (doc_type, defaults, letterhead_version) VALUES (?, NULL, 2)', [$type]);
        }
    }

    public static function get(string $type): array
    {
        $t = DB::row('SELECT * FROM studio_templates WHERE doc_type = ?', [$type]);
        return [
            'defaults' => $t && $t['defaults'] ? (json_decode($t['defaults'], true) ?: []) : [],
            'letterhead' => (int) ($t['letterhead_version'] ?? 2),
        ];
    }

    public static function save(string $type, array $defaults, int $letterhead): void
    {
        DB::run(
            'INSERT INTO studio_templates (doc_type, defaults, letterhead_version, updated_by, updated_at) VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE defaults = VALUES(defaults), letterhead_version = VALUES(letterhead_version), updated_by = VALUES(updated_by), updated_at = NOW()',
            [$type, json_encode($defaults, JSON_UNESCAPED_UNICODE), in_array($letterhead, [1, 2], true) ? $letterhead : 2, Auth::id()]
        );
    }
}
