<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/** SK Arabian Studio permissions and archive helpers. */
final class Studio
{
    private static ?array $allowed = null;

    /** Owner may create everything; other roles need Studio "create", the per-type permission and the record module's "print". */
    public static function canCreate(string $type): bool
    {
        if (!StudioDocs::exists($type) || !Auth::check()) {
            return false;
        }
        if (Auth::isOwner()) {
            return true;
        }
        if (!Auth::can('studio', 'create') || !Auth::can(StudioDocs::TYPES[$type]['module'], 'view')) {
            return false;
        }
        if (self::$allowed === null) {
            self::$allowed = DB::column('SELECT doc_type FROM studio_doc_permissions WHERE role_id = ?', [Auth::user()['role_id']]);
        }
        return in_array($type, self::$allowed, true);
    }
}
