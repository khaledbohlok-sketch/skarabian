<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Default roles from the specification (Section 4). The Owner can edit them or add custom roles in
 * Users & Roles; "Reset to default" re-applies this matrix.
 */
final class DefaultRoles
{
    private const ALL = ['view', 'create', 'edit', 'delete', 'approve', 'export', 'print', 'sensitive'];
    private const RW  = ['view', 'create', 'edit', 'export', 'print'];
    private const RO  = ['view'];

    public static function definitions(): array
    {
        $allModules = \App\Core\Auth::MODULES;
        $gm = [];
        foreach ($allModules as $m) {
            if ($m === 'activity') {
                continue; // full Activity Log is Owner-only
            }
            $gm[$m] = self::ALL;
        }
        $gm['users'] = ['view', 'create', 'edit', 'approve'];
        $gm['settings'] = ['view', 'edit'];

        return [
            'owner' => [
                'name_en' => 'Owner', 'name_ar' => 'المالك', 'require_2fa' => 1, 'horse_scope' => 'all', 'edit_window' => 'any',
                'perms' => array_fill_keys($allModules, self::ALL),
            ],
            'general_manager' => [
                'name_en' => 'General Manager', 'name_ar' => 'المدير العام', 'require_2fa' => 1, 'horse_scope' => 'all', 'edit_window' => 'any',
                'perms' => $gm,
            ],
            'accountant' => [
                'name_en' => 'Accountant', 'name_ar' => 'المحاسب', 'require_2fa' => 1, 'horse_scope' => 'all', 'edit_window' => 'any',
                'perms' => [
                    'finance'   => ['view', 'create', 'edit', 'delete', 'export', 'print', 'sensitive'],
                    'payroll'   => ['view', 'create', 'edit', 'export', 'print', 'sensitive'],
                    'reports'   => ['view', 'export', 'print'],
                    'studio'    => ['view', 'create', 'print'],
                    'horses'    => self::RO,
                    'embryos'   => self::RO,
                    'hr'        => self::RO,          // read-only, no HR sensitive fields
                    'inventory' => ['view', 'create', 'edit', 'export'],
                ],
            ],
            'hr_officer' => [
                'name_en' => 'HR Officer', 'name_ar' => 'مسؤول الموارد البشرية', 'require_2fa' => 1, 'horse_scope' => 'all', 'edit_window' => 'any',
                'perms' => [
                    'hr'      => ['view', 'create', 'edit', 'delete', 'approve', 'export', 'print', 'sensitive'],
                    'payroll' => ['view', 'sensitive'],
                    'studio'  => ['view', 'create', 'print'],
                ],
            ],
            'veterinarian' => [
                'name_en' => 'Veterinarian', 'name_ar' => 'الطبيب البيطري', 'require_2fa' => 0, 'horse_scope' => 'all', 'edit_window' => 'own_24h',
                'perms' => [
                    'horses'         => ['view', 'print'],
                    'horse_health'   => self::RW,
                    'horse_breeding' => self::RW,
                    'horse_diet'     => self::RW,
                    'horse_notes'    => ['view', 'create'],
                    'horse_training' => self::RO,
                    'embryos'        => self::RW,
                    'inventory'      => ['view', 'create', 'edit'],
                    'studio'         => ['view', 'create', 'print'],
                ],
            ],
            'trainer' => [
                'name_en' => 'Horse Trainer', 'name_ar' => 'مدرب الخيل', 'require_2fa' => 0, 'horse_scope' => 'all', 'edit_window' => 'own_24h',
                'perms' => [
                    'horses'         => ['view', 'print'],
                    'horse_training' => self::RW,
                    'horse_diet'     => self::RW,
                    'horse_health'   => self::RO,
                    'horse_notes'    => ['view', 'create'],
                    'embryos'        => self::RO,
                    'studio'         => ['view', 'create', 'print'],
                ],
            ],
            'groom' => [
                'name_en' => 'Groom / Stable Staff', 'name_ar' => 'سائس / عامل إسطبل', 'require_2fa' => 0, 'horse_scope' => 'assigned', 'edit_window' => 'own_24h',
                'perms' => [
                    'horses'      => self::RO,
                    'horse_diet'  => ['view', 'create'],
                    'horse_notes' => ['view', 'create'],
                ],
            ],
            'website_editor' => [
                'name_en' => 'Website Editor', 'name_ar' => 'محرر الموقع', 'require_2fa' => 0, 'horse_scope' => 'all', 'edit_window' => 'any',
                'perms' => [
                    'cms' => ['view', 'create', 'edit', 'delete'],
                ],
            ],
            'tech_support' => [
                'name_en' => 'Technical Support', 'name_ar' => 'الدعم الفني', 'require_2fa' => 0, 'horse_scope' => 'all', 'edit_window' => 'any',
                'perms' => [
                    'settings' => ['view', 'edit'],
                    'activity' => ['view'],   // system/security events only; the full log is Owner-only
                ],
            ],
        ];
    }

    /** Document types each default role may create in SK Arabian Studio. */
    public static function studioDocs(): array
    {
        return [
            'general_manager' => array_keys(StudioDocs::TYPES),
            'accountant'      => ['fin', 'po', 'inv', 'pay', 'letter', 'rem', 'reg'],
            'hr_officer'      => ['staff', 'idcard', 'sal', 'letters', 'offer', 'pay', 'letter', 'rem', 'reg'],
            'veterinarian'    => ['horses', 'profile', 'diet', 'vet', 'cover', 'embryo', 'rem'],
            'trainer'         => ['horses', 'profile', 'diet'],
        ];
    }
}
