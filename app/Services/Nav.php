<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;

/** Portal menu. Users only see the items they are allowed to use (the server re-checks on every request). */
final class Nav
{
    public static function items(): array
    {
        $isOwner = Auth::isOwner();
        $approver = in_array(Auth::roleSlug(), ['owner', 'general_manager'], true) || Auth::canAny(['hr', 'cms'], 'approve');
        $groups = [
            ['nav.dashboard', 'home', '/portal', true, []],
            ['nav.approvals', 'check', '/portal/approvals', $approver, []],
            ['nav.my_horses', 'horse', '/portal/my-horses', Auth::horseScopeAssigned(), []],
            ['nav.horses', 'horse', '/portal/horses', Auth::can('horses') && !Auth::horseScopeAssigned(), [
                ['nav.all_horses', '/portal/horses', Auth::can('horses')],
                ['nav.breeding', '/portal/breeding-records', Auth::can('horse_breeding')],
                ['nav.health', '/portal/health-records', Auth::can('horse_health')],
                ['nav.diet', '/portal/diet-logs', Auth::can('horse_diet')],
                ['nav.training', '/portal/training-logs', Auth::can('horse_training')],
                ['nav.shows', '/portal/shows', Auth::can('horse_training') || Auth::can('horses')],
                ['nav.qr_sheet', '/portal/horses/qr-sheet', Auth::can('horses', 'print')],
            ]],
            ['nav.embryos', 'embryo', '/portal/embryos', Auth::can('embryos'), []],
            ['nav.hr', 'users', '/portal/employees', Auth::can('hr'), [
                ['nav.employees', '/portal/employees', true],
                ['nav.attendance', '/portal/attendance', true],
                ['nav.leave', '/portal/leave-requests', true],
                ['nav.loans', '/portal/employee-loans', Auth::can('hr', 'sensitive') || Auth::can('payroll')],
            ]],
            ['nav.finance', 'wallet', '/portal/bills', Auth::can('finance'), [
                ['nav.bills', '/portal/bills', true],
                ['nav.payments', '/portal/bill-payments', true],
                ['nav.accounts', '/portal/accounts', true],
                ['nav.parties', '/portal/parties', true],
                ['nav.invoices', '/portal/invoices', true],
                ['nav.purchase_orders', '/portal/purchase-orders', true],
                ['nav.budgets', '/portal/budgets', true],
                ['nav.categories', '/portal/categories', Auth::can('finance', 'edit')],
            ]],
            ['nav.payroll', 'payroll', '/portal/payroll', Auth::can('payroll'), []],
            ['nav.inventory', 'box', '/portal/items', Auth::can('inventory'), [
                ['nav.items', '/portal/items', true],
                ['nav.inventories', '/portal/inventories', true],
                ['nav.movements', '/portal/stock-movements', true],
                ['nav.purchase_orders', '/portal/purchase-orders', !Auth::can('finance')],
            ]],
            ['nav.reports', 'chart', '/portal/reports', Auth::can('reports'), []],
            ['nav.studio', 'doc', '/portal/studio', Auth::can('studio'), []],
            ['nav.website', 'globe', '/portal/cms', Auth::can('cms'), [
                ['nav.cms_home', '/portal/cms', true],
                ['nav.website_horses', '/portal/cms/website-horses', true],
                ['nav.news', '/portal/news', true],
                ['nav.gallery', '/portal/gallery', true],
                ['nav.contact_details', '/portal/cms/contact', Auth::can('cms', 'edit')],
            ]],
            ['nav.inbox', 'mail', '/portal/inbox', Auth::can('inbox'), []],
            ['nav.admin', 'shield', '/portal/users', Auth::canAny(['users', 'settings', 'activity']), [
                ['nav.users', '/portal/users', Auth::can('users')],
                ['nav.roles', '/portal/roles', $isOwner],
                ['nav.sessions', '/portal/sessions', $isOwner],
                ['nav.settings', '/portal/settings', Auth::can('settings')],
                ['nav.lookups', '/portal/lookups', Auth::can('settings', 'edit')],
                ['nav.currencies', '/portal/settings/currencies', Auth::can('settings') || Auth::can('finance', 'edit')],
                ['nav.security', '/portal/settings/security', $isOwner],
                ['nav.backups', '/portal/settings/backups', $isOwner],
                ['nav.trash', '/portal/trash', $isOwner],
                ['nav.activity', '/portal/activity', Auth::can('activity')],
                ['nav.migration_report', '/portal/migration-report', $isOwner],
            ]],
        ];
        $out = [];
        foreach ($groups as [$label, $icon, $url, $allowed, $children]) {
            if (!$allowed) {
                continue;
            }
            $kids = array_values(array_filter($children, fn ($c) => $c[2]));
            $out[] = ['label' => $label, 'icon' => $icon, 'url' => $url, 'children' => $kids];
        }
        return $out;
    }

    public static function isActive(string $url): bool
    {
        $path = current_path();
        return $url === '/portal' ? $path === '/portal' : ($path === $url || str_starts_with($path, $url . '/'));
    }
}
