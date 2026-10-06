<?php
declare(strict_types=1);

namespace App\Services;

/**
 * SK Arabian Studio document types — the 18 templates of the original Studio plus the Embryo Transfer Record
 * and the Custom Letter required by the new system.
 * code   = reference code (SKA-<code>-YYYY-NNNN)
 * record = linked record the document is filled from (null = free / period based)
 * module = permission module that must allow "print" in addition to the Studio per-type permission
 * group  = menu group (same groups as the original Studio)
 */
final class StudioDocs
{
    public const TYPES = [
        'fin'      => ['code' => 'FIN', 'record' => null,             'module' => 'reports',        'group' => 'office'],
        'po'       => ['code' => 'PO',  'record' => 'purchase_order', 'module' => 'finance',        'group' => 'office'],
        'inv'      => ['code' => 'INV', 'record' => 'invoice',        'module' => 'finance',        'group' => 'office'],
        'pay'      => ['code' => 'PAY', 'record' => 'payroll',        'module' => 'payroll',        'group' => 'office'],
        'letter'   => ['code' => 'LTR', 'record' => null,             'module' => 'studio',         'group' => 'office'],
        'horses'   => ['code' => 'HRS', 'record' => null,             'module' => 'horses',         'group' => 'horses'],
        'profile'  => ['code' => 'PRF', 'record' => 'horse',          'module' => 'horses',         'group' => 'horses'],
        'diet'     => ['code' => 'DIET','record' => 'horse',          'module' => 'horse_diet',     'group' => 'horses'],
        'vet'      => ['code' => 'VET', 'record' => 'horse',          'module' => 'horse_health',   'group' => 'horses'],
        'cover'    => ['code' => 'COV', 'record' => 'breeding',       'module' => 'horse_breeding', 'group' => 'horses'],
        'transfer' => ['code' => 'OTC', 'record' => 'ownership',      'module' => 'horses',         'group' => 'horses'],
        'board'    => ['code' => 'BRD', 'record' => 'party',          'module' => 'horses',         'group' => 'horses'],
        'embryo'   => ['code' => 'ETR', 'record' => 'embryo',         'module' => 'embryos',        'group' => 'horses'],
        'staff'    => ['code' => 'STF', 'record' => null,             'module' => 'hr',             'group' => 'staff'],
        'idcard'   => ['code' => 'IDC', 'record' => null,             'module' => 'hr',             'group' => 'staff'],
        'sal'      => ['code' => 'SAL', 'record' => 'employee',       'module' => 'hr',             'group' => 'staff'],
        'letters'  => ['code' => 'HRL', 'record' => 'employee',       'module' => 'hr',             'group' => 'staff'],
        'offer'    => ['code' => 'OFR', 'record' => 'employee',       'module' => 'hr',             'group' => 'staff'],
        'rem'      => ['code' => 'REM', 'record' => null,             'module' => 'studio',         'group' => 'tools'],
        'reg'      => ['code' => 'REG', 'record' => null,             'module' => 'studio',         'group' => 'tools'],
    ];

    /** Lists and tools are printed but not archived as numbered documents. */
    public const UNNUMBERED = ['horses', 'staff', 'rem', 'reg'];

    public static function exists(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }
}
