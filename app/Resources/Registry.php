<?php
declare(strict_types=1);

namespace App\Resources;

/** URL key => Resource class. */
final class Registry
{
    private const MAP = [
        'horses'           => Horses::class,
        'health-records'   => HealthRecords::class,
        'diet-plans'       => DietPlans::class,
        'diet-logs'        => DietLogs::class,
        'training-logs'    => TrainingLogs::class,
        'shows'            => Shows::class,
        'show-results'     => ShowResults::class,
        'breeding-records' => BreedingRecords::class,
        'horse-notes'      => HorseNotes::class,
        'embryos'          => Embryos::class,
        'employees'        => Employees::class,
        'attendance'       => Attendance::class,
        'leave-requests'   => LeaveRequests::class,
        'public-holidays'  => PublicHolidays::class,
        'employee-loans'   => EmployeeLoans::class,
        'parties'          => Parties::class,
        'accounts'         => Accounts::class,
        'account-transfers'=> AccountTransfers::class,
        'categories'       => Categories::class,
        'bills'            => Bills::class,
        'bill-payments'    => BillPayments::class,
        'recurring-bills'  => RecurringBills::class,
        'invoices'         => Invoices::class,
        'purchase-orders'  => PurchaseOrders::class,
        'budgets'          => Budgets::class,
        'inventories'      => Inventories::class,
        'items'            => Items::class,
        'stock-movements'  => StockMovements::class,
        'users'            => Users::class,
        'news'             => News::class,
        'gallery'          => Gallery::class,
        'lookups'          => Lookups::class,
        'documents'        => Documents::class,
        'document-categories' => DocumentCategories::class,
        'semen-stock'      => SemenStock::class,
        'semen-movements'  => SemenMovements::class,
        'tanks'            => Tanks::class,
        'canisters'        => Canisters::class,
        'straw-colors'     => StrawColors::class,
    ];

    private static array $instances = [];

    public static function get(string $key): ?Resource
    {
        if (!isset(self::MAP[$key]) || !class_exists(self::MAP[$key])) {
            return null;
        }
        return self::$instances[$key] ??= new (self::MAP[$key])();
    }

    public static function keys(): array
    {
        return array_keys(self::MAP);
    }

    public static function forRecordType(string $type): ?Resource
    {
        foreach (self::keys() as $k) {
            $r = self::get($k);
            if ($r && $r->recordType === $type) {
                return $r;
            }
        }
        return null;
    }
}
