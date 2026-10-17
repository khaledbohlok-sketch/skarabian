<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;

/** Portal menu. Users only see the items they are allowed to use (the server re-checks on every request). */
final class Nav
{
    /** Pages without a menu entry of their own, marked as the entry they belong to (address start => entry address). */
    private const BELONGS = [
        '/portal/show-results' => '/portal/shows', '/portal/diet-plans' => '/portal/diet-logs', '/portal/horse-notes' => '/portal/horses',
        '/portal/account-transfers' => '/portal/accounts', '/portal/document-categories' => '/portal/documents',
        '/portal/semen-movements' => '/portal/semen-stock', '/portal/tanks' => '/portal/semen-stock', '/portal/canisters' => '/portal/semen-stock',
        '/portal/straw-colors' => '/portal/semen-stock',
    ];

    /** Menu headings (presentation only): entry label => group. Entries without one (Dashboard, Approvals, My HR, My horses) sit at the top. */
    public const GROUPS = [
        'nav.horses' => 'horses', 'nav.embryos' => 'horses', 'nav.semen_stock' => 'horses',
        'nav.hr' => 'people',
        'nav.finance' => 'finance', 'nav.payroll' => 'finance', 'nav.inventory' => 'finance', 'nav.reports' => 'finance',
        'nav.studio' => 'documents', 'nav.documents' => 'documents', 'nav.website' => 'documents', 'nav.inbox' => 'documents',
        'nav.admin' => 'system',
    ];

    /** Heading label of each group, in the order the menu shows them. */
    public const GROUP_LABELS = ['horses' => 'nav.group_horses', 'people' => 'nav.group_people', 'finance' => 'nav.group_finance', 'documents' => 'nav.group_documents', 'system' => 'nav.group_system'];

    public static function items(): array
    {
        $isOwner = Auth::isOwner();
        $approver = in_array(Auth::roleSlug(), ['owner', 'general_manager'], true) || Auth::canAny(['hr', 'cms'], 'approve');
        $groups = [
            ['nav.dashboard', 'home', '/portal', true, []],
            ['nav.approvals', 'check', '/portal/approvals', $approver, []],
            ['nav.my_hr', 'user', '/portal/my-hr', !empty(Auth::user()['employee_id']), []],
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
            ['nav.semen_stock', 'flask', '/portal/semen-stock', Auth::can('semen_stock'), []],
            ['nav.hr', 'users', '/portal/employees', Auth::can('hr'), [
                ['nav.employees', '/portal/employees', true],
                ['nav.attendance', '/portal/attendance', true],
                ['nav.leave', '/portal/leave-requests', true],
                ['nav.holidays', '/portal/public-holidays', \App\Core\DB::hasColumn('public_holidays', 'holiday_date')], // after database update 020
                ['nav.loans', '/portal/employee-loans', Auth::can('hr', 'sensitive') || Auth::can('payroll')],
            ]],
            ['nav.finance', 'wallet', '/portal/bills', Auth::can('finance'), [
                ['nav.bills', '/portal/bills', true],
                ['nav.recurring_bills', '/portal/recurring-bills', Auth::can('finance') && RecurringBillService::available()],
                ['nav.payments', '/portal/bill-payments', true],
                ['nav.accounts', '/portal/accounts', true],
                ['nav.parties', '/portal/parties', true],
                ['nav.invoices', '/portal/invoices', true],
                ['nav.purchase_orders', '/portal/purchase-orders', true],
                ['nav.budgets', '/portal/budgets', true],
                ['nav.categories', '/portal/categories', Auth::can('finance', 'edit')],
                ['nav.numbers', '/portal/number-register', true],
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
            ['nav.documents', 'folder', '/portal/documents', Auth::can('documents'), []],
            ['nav.website', 'globe', '/portal/cms', Auth::can('cms'), [
                ['nav.cms_home', '/portal/cms', true],
                ['nav.website_horses', '/portal/cms/website-horses', true],
                ['nav.website_visitors', '/portal/cms/visitors', true],
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
                ['nav.checks', '/portal/settings/checks', $isOwner],
                ['nav.lookups', '/portal/lookups', Auth::can('settings', 'edit')],
                ['nav.currencies', '/portal/settings/currencies', Auth::can('settings') || Auth::can('finance', 'edit')],
                ['nav.whatsapp', '/portal/settings/whatsapp', $isOwner],
                ['nav.ai', '/portal/settings/ai', $isOwner],
                ['nav.security', '/portal/settings/security', $isOwner],
                ['nav.backups', '/portal/settings/backups', $isOwner],
                ['nav.errors', '/portal/settings/errors', $isOwner],
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
            // A group opens on its first page the user may use (e.g. Technical Support has Settings but not Users)
            if ($kids && !in_array($url, array_column($kids, 1), true)) {
                $url = $kids[0][1];
            }
            $out[] = ['label' => $label, 'icon' => $icon, 'url' => $url, 'children' => $kids, 'group' => self::GROUPS[$label] ?? null];
        }
        return $out;
    }

    /**
     * The menu entry of the page shown: the group (index in items()) and sub-page whose address matches the longest
     * part of the path. So exactly one group and at most one sub-page are marked: /portal/settings/checks marks
     * "System check" and not "Settings" too, and a record (/portal/bills/12) marks its list ("Bills").
     */
    public static function current(array $items, ?string $path = null): array
    {
        $path ??= current_path();
        foreach (self::BELONGS as $from => $to) {
            if (self::matches($from, $path)) {
                $path = $to;
                break;
            }
        }
        $best = ['group' => null, 'child' => null];
        $len = -1;
        foreach ($items as $i => $item) {
            $kids = array_column($item['children'], 1);
            foreach (array_merge([$item['url']], $kids) as $u) {
                if (self::matches($u, $path) && strlen($u) > $len) {
                    $best = ['group' => $i, 'child' => in_array($u, $kids, true) ? $u : null];
                    $len = strlen($u);
                }
            }
        }
        return $best;
    }

    /** Whether a menu address covers the path: itself, or a page under it (/portal only itself). */
    public static function matches(string $url, string $path): bool
    {
        return $url === '/portal' ? $path === '/portal' : ($path === $url || str_starts_with($path, $url . '/'));
    }

    public static function isActive(string $url): bool
    {
        return self::matches($url, current_path());
    }
}
