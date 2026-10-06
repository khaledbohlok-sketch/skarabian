<?php
declare(strict_types=1);

namespace App\Services;

/**
 * SK Arabian Studio document types.
 * code   = reference number code (SKA-<code>-YYYY-NNNN)
 * record = which record the document is generated from (fields fill automatically)
 * module = permission module that must also allow "print" (in addition to the Studio per-type permission)
 */
final class StudioDocs
{
    public const TYPES = [
        'financial_report'   => ['code' => 'FIN', 'record' => null,              'module' => 'reports'],
        'diet_log'           => ['code' => 'DIET', 'record' => 'horse',          'module' => 'horse_diet'],
        'purchase_order'     => ['code' => 'PO',  'record' => 'purchase_order',  'module' => 'finance'],
        'salary_certificate' => ['code' => 'SAL', 'record' => 'employee',        'module' => 'hr'],
        'offer_letter'       => ['code' => 'OFR', 'record' => 'employee',        'module' => 'hr'],
        'experience_letter'  => ['code' => 'EXP', 'record' => 'employee',        'module' => 'hr'],
        'payslip'            => ['code' => 'PAY', 'record' => 'payroll_line',    'module' => 'payroll'],
        'invoice'            => ['code' => 'INV', 'record' => 'invoice',         'module' => 'finance'],
        'receipt'            => ['code' => 'RCT', 'record' => 'bill_payment',    'module' => 'finance'],
        'ownership_transfer' => ['code' => 'OTC', 'record' => 'ownership',       'module' => 'horses'],
        'embryo_transfer'    => ['code' => 'ETR', 'record' => 'embryo',          'module' => 'embryos'],
        'leave_approval'     => ['code' => 'LVA', 'record' => 'leave_request',   'module' => 'hr'],
        'custom_letter'      => ['code' => 'LTR', 'record' => null,              'module' => 'studio'],
    ];

    public static function exists(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }
}
