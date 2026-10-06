<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\HrService;
use App\Services\Sequence;

/**
 * Employees. Position comes from a list (phone numbers can never be saved there, as happened in the old system);
 * QID/passport/visa/health-card expiry have their own validated fields; numbers and bank details are encrypted and
 * shown only to roles with "view sensitive" on HR.
 */
class Employees extends Resource
{
    public string $key = 'employees';
    public string $table = 'employees';
    public string $module = 'hr';
    public string $recordType = 'employee';
    public string $title = 'nav.employees';
    public string $singular = 'employees.singular';
    public array $search = ['t.name_en', 't.name_ar', 't.emp_no', 't.phone', 't.email'];
    public string $sort = "FIELD(t.status,'active','on_leave','suspended','left'), t.name_en";
    public bool $archivable = true;

    public const STATUSES = ['active' => 'status.active', 'on_leave' => 'status.on_leave', 'suspended' => 'status.suspended', 'left' => 'status.left'];

    public function sections(): array
    {
        return ['main' => 'employees.sec_personal', 'docs' => 'employees.sec_documents', 'pay' => 'employees.sec_pay', 'leave' => 'employees.sec_leave', 'web' => 'horses.sec_website'];
    }

    public function fields(): array
    {
        return [
            'emp_no'         => ['type' => 'text', 'label' => 'employees.emp_no', 'max' => 20, 'col' => 3, 'help' => 'employees.emp_no_help'],
            'name_en'        => ['type' => 'text', 'label' => 'employees.name_en', 'required' => true, 'max' => 120, 'no_phone' => true, 'col' => 5],
            'name_ar'        => ['type' => 'text', 'label' => 'employees.name_ar', 'max' => 120, 'no_phone' => true, 'col' => 4, 'attrs' => ['dir' => 'rtl']],
            'gender'         => ['type' => 'select', 'label' => 'employees.gender', 'options' => ['m' => 'employees.male', 'f' => 'employees.female'], 'default' => 'm', 'required' => true, 'col' => 3],
            'nationality_id' => ['type' => 'lookup', 'lookup' => 'nationality', 'label' => 'employees.nationality', 'col' => 3],
            'position_id'    => ['type' => 'lookup', 'lookup' => 'position', 'label' => 'employees.position', 'required' => true, 'col' => 3, 'help' => 'employees.position_help'],
            'department_id'  => ['type' => 'lookup', 'lookup' => 'department', 'label' => 'employees.department', 'col' => 3],
            'phone'          => ['type' => 'phone', 'label' => 'common.phone', 'col' => 4],
            'email'          => ['type' => 'email', 'label' => 'common.email', 'col' => 4],
            'hire_date'      => ['type' => 'date', 'label' => 'employees.hire_date', 'col' => 4],
            'status'         => ['type' => 'select', 'label' => 'common.status', 'options' => self::STATUSES, 'default' => 'active', 'required' => true, 'col' => 4, 'help' => 'employees.status_help'],
            'end_date'       => ['type' => 'date', 'label' => 'employees.end_date', 'col' => 4],
            'emergency_contact' => ['type' => 'text', 'label' => 'employees.emergency', 'max' => 150, 'col' => 4],

            'qid_no'          => ['type' => 'encrypted', 'label' => 'employees.qid_no', 'section' => 'docs', 'sensitive' => true, 'col' => 4, 'pattern' => '/^\d{11}$/', 'pattern_msg' => 'employees.qid_format'],
            'qid_expiry'      => ['type' => 'date', 'label' => 'employees.qid_expiry', 'section' => 'docs', 'col' => 4, 'expiry' => true],
            'blood_group'     => ['type' => 'select', 'label' => 'employees.blood', 'section' => 'docs', 'col' => 4, 'options' => array_combine(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'passport_no'     => ['type' => 'encrypted', 'label' => 'employees.passport_no', 'section' => 'docs', 'sensitive' => true, 'col' => 4, 'pattern' => '/^[A-Za-z0-9]{5,20}$/', 'pattern_msg' => 'employees.passport_format'],
            'passport_expiry' => ['type' => 'date', 'label' => 'employees.passport_expiry', 'section' => 'docs', 'col' => 4, 'expiry' => true],
            'visa_expiry'     => ['type' => 'date', 'label' => 'employees.visa_expiry', 'section' => 'docs', 'col' => 4, 'expiry' => true],
            'health_card_expiry' => ['type' => 'date', 'label' => 'employees.health_card_expiry', 'section' => 'docs', 'col' => 4, 'expiry' => true],

            'basic_salary_qar'    => ['type' => 'money', 'label' => 'employees.basic', 'section' => 'pay', 'sensitive' => true, 'min' => 0, 'col' => 3],
            'housing_allowance'   => ['type' => 'money', 'label' => 'employees.housing', 'section' => 'pay', 'sensitive' => true, 'min' => 0, 'col' => 3],
            'transport_allowance' => ['type' => 'money', 'label' => 'employees.transport', 'section' => 'pay', 'sensitive' => true, 'min' => 0, 'col' => 3],
            'other_allowance'     => ['type' => 'money', 'label' => 'employees.other_allow', 'section' => 'pay', 'sensitive' => true, 'min' => 0, 'col' => 3],
            'bank_name'           => ['type' => 'encrypted', 'label' => 'employees.bank_name', 'section' => 'pay', 'sensitive' => true, 'max' => 120, 'col' => 4],
            'bank_iban'           => ['type' => 'encrypted', 'label' => 'employees.iban', 'section' => 'pay', 'sensitive' => true, 'max' => 34, 'col' => 8, 'pattern' => '/^[A-Za-z]{2}\d{2}[A-Za-z0-9 ]{10,30}$/', 'pattern_msg' => 'employees.iban_format'],

            'annual_leave_days' => ['type' => 'decimal', 'label' => 'employees.annual_leave', 'section' => 'leave', 'min' => 0, 'default' => 30, 'col' => 6],
            'leave_balance'     => ['type' => 'decimal', 'label' => 'employees.leave_balance', 'section' => 'leave', 'col' => 6, 'default' => 0],

            'show_on_website' => ['type' => 'checkbox', 'label' => 'employees.show_on_website', 'section' => 'web', 'col' => 8],
            'website_sort'    => ['type' => 'int', 'label' => 'employees.website_sort', 'section' => 'web', 'col' => 4],
            'public_title_en' => ['type' => 'text', 'label' => 'employees.public_title_en', 'section' => 'web', 'max' => 120],
            'public_title_ar' => ['type' => 'text', 'label' => 'employees.public_title_ar', 'section' => 'web', 'max' => 120, 'attrs' => ['dir' => 'rtl']],
            'specialties_en'  => ['type' => 'text', 'label' => 'employees.specialties_en', 'section' => 'web', 'max' => 255],
            'specialties_ar'  => ['type' => 'text', 'label' => 'employees.specialties_ar', 'section' => 'web', 'max' => 255, 'attrs' => ['dir' => 'rtl']],
            'public_bio_en'   => ['type' => 'textarea', 'label' => 'employees.bio_en', 'section' => 'web', 'col' => 6, 'rows' => 3],
            'public_bio_ar'   => ['type' => 'textarea', 'label' => 'employees.bio_ar', 'section' => 'web', 'col' => 6, 'rows' => 3, 'attrs' => ['dir' => 'rtl']],
            'notes'           => ['type' => 'textarea', 'label' => 'common.notes', 'section' => 'web', 'rows' => 3],
        ];
    }

    public function from(): string
    {
        return '`employees` t LEFT JOIN lookups p ON p.id = t.position_id LEFT JOIN lookups dp ON dp.id = t.department_id LEFT JOIN lookups n ON n.id = t.nationality_id';
    }

    public function columns(): array
    {
        $ar = \App\Core\Lang::isRtl();
        return [
            'photo'    => ['label' => 'horses.photo', 'sql' => 't.photo_id', 'fmt' => 'thumb'],
            'name_en'  => ['label' => 'common.name', 'sql' => $ar ? 'COALESCE(t.name_ar, t.name_en)' : 't.name_en', 'sort' => true],
            'emp_no'   => ['label' => 'employees.emp_no', 'sql' => 't.emp_no', 'sort' => true],
            'position' => ['label' => 'employees.position', 'sql' => $ar ? 'COALESCE(p.value_ar, p.value_en)' : 'p.value_en', 'sort' => true],
            'dept'     => ['label' => 'employees.department', 'sql' => $ar ? 'COALESCE(dp.value_ar, dp.value_en)' : 'dp.value_en'],
            'nat'      => ['label' => 'employees.nationality', 'sql' => $ar ? 'COALESCE(n.value_ar, n.value_en)' : 'n.value_en'],
            'qid'      => ['label' => 'employees.qid_expiry', 'sql' => 't.qid_expiry', 'fmt' => 'expiry', 'sort' => true],
            'passport' => ['label' => 'employees.passport_expiry', 'sql' => 't.passport_expiry', 'fmt' => 'expiry', 'sort' => true],
            'visa'     => ['label' => 'employees.visa_expiry', 'sql' => 't.visa_expiry', 'fmt' => 'expiry', 'sort' => true],
            'status'   => ['label' => 'common.status', 'sql' => 't.status', 'fmt' => 'badge'],
        ];
    }

    public function filters(): array
    {
        return [
            'status'     => ['type' => 'select', 'label' => 'common.status', 'sql' => 't.status', 'options' => self::STATUSES],
            'department' => ['type' => 'lookup', 'lookup' => 'department', 'label' => 'employees.department', 'sql' => 't.department_id'],
            'position'   => ['type' => 'lookup', 'lookup' => 'position', 'label' => 'employees.position', 'sql' => 't.position_id'],
            'docs'       => ['type' => 'raw', 'label' => 'employees.documents_filter', 'options' => ['expired' => 'dashboard.expired', 'soon' => 'dashboard.expiring_60'],
                'sqlFor' => [
                    'expired' => "(t.qid_expiry < CURDATE() OR t.passport_expiry < CURDATE() OR t.visa_expiry < CURDATE() OR t.health_card_expiry < CURDATE())",
                    'soon'    => "(t.qid_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) OR t.passport_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) OR t.visa_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) OR t.health_card_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY))",
                ]],
            'show_on_website' => ['type' => 'bool', 'label' => 'common.on_website', 'sql' => 't.show_on_website'],
            'archived'   => ['type' => 'archived', 'label' => 'common.show_archived'],
        ];
    }

    public function rowClass(array $row): string
    {
        return $row['status'] === 'left' ? 'void' : '';
    }

    public function label(array $row): string
    {
        return (string) $row['name_en'];
    }

    public function links(): array
    {
        return [['payroll_lines', 'employee_id', 'nav.payroll'], ['bills', 'employee_id', 'nav.bills'], ['attendance', 'employee_id', 'nav.attendance'],
                ['leave_requests', 'employee_id', 'nav.leave'], ['employee_loans', 'employee_id', 'nav.loans'], ['horse_assignments', 'employee_id', 'horses.assigned_staff'], ['users', 'employee_id', 'nav.users']];
    }

    public function prepare(array $data, ?array $old): array
    {
        $errors = [];
        if (!empty($data['end_date']) && !empty($data['hire_date']) && $data['end_date'] < $data['hire_date']) {
            $errors['end_date'] = __('employees.end_before_hire');
        }
        if (($data['status'] ?? '') === 'left' && empty($data['end_date'])) {
            $data['end_date'] = date('Y-m-d');
        }
        if (isset($data['bank_iban'])) {
            // already encrypted by collect(); the pattern was checked on the plain value
        }
        $no = $data['emp_no'] ?? null;
        if ($no && DB::value('SELECT id FROM employees WHERE emp_no = ? AND id <> ?', [$no, $old['id'] ?? 0])) {
            $errors['emp_no'] = __('employees.emp_no_taken');
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        if (!$no && !$old) {
            $data['emp_no'] = HrService::nextEmpNo();
        }
        return $data;
    }

    /** Deactivating the employee (status Left) deactivates their login and ends their sessions. */
    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        if (($data['status'] ?? '') === 'left' && (!$old || $old['status'] !== 'left')) {
            HrService::deactivateLogin($id);
            DB::run('DELETE FROM horse_assignments WHERE employee_id = ?', [$id]);
        }
    }

    public function beforeTabs(array $row): string
    {
        return View::partial('portal/hr/header', ['emp' => $row]);
    }

    public function actions(array $row): array
    {
        $a = [];
        $user = DB::row('SELECT id, status FROM users WHERE employee_id = ? AND deleted_at IS NULL', [$row['id']]);
        if ($user && $user['status'] === 'active' && (Auth::can('hr', 'edit') || Auth::can('users', 'edit'))) {
            $a[] = ['label' => 'employees.deactivate_login', 'url' => '/portal/employees/' . $row['id'] . '/deactivate-login', 'method' => 'post', 'class' => 'btn-danger', 'confirm' => 'employees.deactivate_confirm'];
        }
        return $a;
    }

    public function tabs(array $row): array
    {
        $id = (int) $row['id'];
        $t = [
            'horses' => ['label' => 'employees.assigned_horses', 'render' => fn ($r) => View::partial('portal/hr/tab_horses', ['emp' => $r])],
            'attendance' => ['label' => 'nav.attendance', 'related' => 'attendance', 'filter' => ['employee_id' => $id]],
            'leave-requests' => ['label' => 'nav.leave', 'related' => 'leave-requests', 'filter' => ['employee_id' => $id]],
        ];
        if (Auth::can('payroll')) {
            $t['employee-loans'] = ['label' => 'nav.loans', 'related' => 'employee-loans', 'filter' => ['employee_id' => $id]];
            $t['payroll'] = ['label' => 'nav.payroll', 'render' => fn ($r) => View::partial('portal/hr/tab_payroll', ['emp' => $r])];
        }
        if (Auth::can('finance')) {
            $t['bills'] = ['label' => 'nav.bills', 'related' => 'bills', 'filter' => ['employee_id' => $id]];
        }
        return $t + $this->standardTabs($row, ['studio' => ['sal', 'letters', 'offer', 'idcard'], 'categories' => ['photo', 'document', 'contract', 'id_copy']]);
    }
}
