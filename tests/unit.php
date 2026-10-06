<?php
/**
 * Unit tests for the rules that matter most (money, payroll, horse categories, security, import matching, scheduler).
 * They only read the database. Run on the server or locally:
 *   php tests/unit.php
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Crypto;
use App\Core\DB;
use App\Services\Cron;
use App\Services\HorseRules;
use App\Services\Money;
use App\Services\OldImport;
use App\Services\Passwords;
use App\Services\PayrollService;
use App\Services\Totp;

$pass = 0;
$fail = 0;
function check(string $name, mixed $got, mixed $want): void
{
    global $pass, $fail;
    if ($got === $want) {
        $pass++;
        return;
    }
    $fail++;
    echo "FAIL  $name\n      got:  " . var_export($got, true) . "\n      want: " . var_export($want, true) . "\n";
}

// ---------------------------------------------------------------- money
check('parse "4,604.32"', Money::parse('4,604.32'), 4604.32);
check('parse Arabic digits', Money::parse('١٢٣٫٥'), 123.5);
check('parse empty', Money::parse(''), null);
check('parse text', Money::parse('abc'), null);
check('QAR rounding to 2 decimals', Money::toQar(1264.92304, 3.64), 4604.32);
check('USD peg 3.64', Money::toQar(100, 3.64), 364.0);

// ---------------------------------------------------------------- horse rules
check('foal under 1 year', HorseRules::category('female', '2026-03-01', '2026-10-06'), 'foal');
check('filly 1-3 years', HorseRules::category('female', '2024-03-01', '2026-10-06'), 'filly');
check('colt 1-3 years', HorseRules::category('male', '2023-03-01', '2026-10-06'), 'colt');
check('stallion 4+', HorseRules::category('male', '2020-01-01', '2026-10-06'), 'stallion');
check('mare 4+', HorseRules::category('female', '2015-01-01', '2026-10-06'), 'mare');
check('gelding stays gelding', HorseRules::category('gelding', '2025-01-01', '2026-10-06'), 'gelding');
check('expected foaling = +340 days', HorseRules::expectedFoaling('2026-01-10'), '2026-12-16');

// ---------------------------------------------------------------- payroll (employee id 0 has no attendance, leave or loans)
$e = ['id' => 0, 'hire_date' => null, 'end_date' => null, 'basic_salary_qar' => 3000, 'housing_allowance' => 500, 'transport_allowance' => 200, 'other_allowance' => 0];
$l = PayrollService::computeLine($e, '2026-09-01', '2026-09-30');
check('full month net', $l['net_qar'], 3700.0);
$l = PayrollService::computeLine(['hire_date' => '2026-09-16'] + $e, '2026-09-01', '2026-09-30');
check('half month basic (joined on the 16th)', $l['basic_qar'], 1500.0);
check('half month allowances', $l['allowances_qar'], 350.0);

// ---------------------------------------------------------------- security
check('short password refused', Passwords::validate('Ab1!'), 'auth.pw_too_short');
check('not mixed refused', Passwords::validate('abcdefghijkl'), 'auth.pw_not_mixed');
check('password with the user name refused', Passwords::validate('Khaled!2026x', ['khaled']), 'auth.pw_contains_name');
check('strong password accepted', Passwords::validate('Stud-Gold!2026'), null);
check('generated password is valid', Passwords::validate(Passwords::generate()), null);
$h = Passwords::hash('Stud-Gold!2026');
check('bcrypt verify', Passwords::verify('Stud-Gold!2026', $h), true);
check('bcrypt wrong password', Passwords::verify('wrong', $h), false);
$secret = Totp::newSecret();
check('TOTP current code verifies', Totp::verify($secret, Totp::code($secret)), true);
check('TOTP old code refused', Totp::verify($secret, Totp::code($secret, time() - 300)), false);
check('TOTP RFC 6238 vector', Totp::code(Totp::base32Encode('12345678901234567890'), 59), '287082');
check('encryption round trip', Crypto::decrypt(Crypto::encrypt('28435600017')), '28435600017');
check('encrypted value is not plain', str_contains((string) Crypto::encrypt('28435600017'), '28435600017'), false);
check('mask shows only the last 4', Crypto::mask('28435600017'), '••••0017');

// ---------------------------------------------------------------- old-data import: duplicate names from the old system
check('AJ RAZNEH = AJ RAZENAH', OldImport::key('AJ RAZNEH') === OldImport::key('AJ RAZENAH'), true);
check('SG SHAMMAH = SG SHAMMA = SG SHAMA', OldImport::key('SG SHAMMAH') === OldImport::key('SG SHAMMA') && OldImport::key('SG SHAMMA') === OldImport::key('SG SHAMA'), true);
check('BDOOR AL BAYHA = BADOOR AL BAHIYA', OldImport::key('BDOOR AL BAYHA') === OldImport::key('BADOOR AL BAHIYA'), true);
check('D Tmouh = D Tumooh', OldImport::key('D Tmouh') === OldImport::key('D TUMOOH'), true);
check('different horses stay apart', OldImport::similar('SAQR', 'SHAHD'), false);
check('different horses stay apart (2)', OldImport::similar('DANA', 'DOMINIC M'), false);

// ---------------------------------------------------------------- scheduler
$t = strtotime('2026-10-06 06:30:00');
$before = DB::value("SELECT COUNT(*) FROM cron_runs WHERE task = 'reminders' AND ok = 1 AND started_at >= '2026-10-06 06:00:00'");
if ((int) $before === 0) {
    check('daily task due after its time', Cron::isDue('reminders', $t), true);
    check('daily task not due before its time', Cron::isDue('reminders', strtotime('2026-10-06 05:59:00')) === false || DB::value("SELECT COUNT(*) FROM cron_runs WHERE task = 'reminders' AND ok = 1 AND started_at >= '2026-10-05 06:00:00'") == 0, true);
}

// ---------------------------------------------------------------- translations
$en = require APP_ROOT . '/lang/en.php';
$ar = require APP_ROOT . '/lang/ar.php';
check('same keys in English and Arabic', array_diff_key($en, $ar) + array_diff_key($ar, $en), []);
check('no empty Arabic labels', array_keys(array_filter($ar, fn ($v) => trim((string) $v) === '')), []);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
